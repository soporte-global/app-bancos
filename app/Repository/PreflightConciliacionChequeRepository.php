<?php
namespace AppBancos\Repository;

use AppBancos\Application\MovimientoNoEncontradoException;
use AppBancos\Application\TransicionMovimientoException;
use AppBancos\Infrastructure\EsquemaBancos;
use PDO;

final class PreflightConciliacionChequeRepository
{
    private const SUBTIPOS_CHEQUE = [13, 14, 10070, 10071];

    private $pdo;
    private $movimientos;
    private $importaciones;
    private $historial;
    private $estados;
    private $asociaciones;
    private $reservas;
    private $conciliaciones;
    private $configuraciones;
    private $mapeosContables;
    private $valoresErp;
    private $subtiposErp;
    private $operacionesValoresErp;
    private $cuentasErp;
    private $entidadesErp;

    public function __construct(PDO $pdo, EsquemaBancos $esquema)
    {
        $this->pdo = $pdo;
        $this->movimientos = $esquema->tablaBancos('bancos_movimiento_extracto');
        $this->importaciones = $esquema->tablaBancos('bancos_importacion_extracto');
        $this->historial = $esquema->tablaBancos('bancos_historial_asignacion');
        $this->estados = $esquema->tablaBancos('bancos_estado');
        $this->asociaciones = $esquema->tablaBancos('bancos_asociacion_movimiento');
        $this->reservas = $esquema->tablaBancos('bancos_reserva_recurso');
        $this->conciliaciones = $esquema->tablaBancos('bancos_conciliacion_cheque');
        $this->configuraciones = $esquema->tablaBancos('bancos_configuracion');
        $this->mapeosContables = $esquema->tablaBancos('bancos_mapeo_cuenta_contable');
        $this->valoresErp = $esquema->tablaLecturaErp('valor');
        $this->subtiposErp = $esquema->tablaLecturaErp('subtipo_valor');
        $this->operacionesValoresErp = $esquema->tablaLecturaErp('operacion_valor');
        $this->cuentasErp = $esquema->tablaLecturaErp('cuenta');
        $this->entidadesErp = $esquema->tablaLecturaErp('entidad');
    }

    public function evaluar($movimientoId, $cuentaBancariaId, $inicioPeriodo)
    {
        $movimiento = $this->obtenerMovimiento($movimientoId, $cuentaBancariaId, $inicioPeriodo);
        if ($movimiento['estado'] !== 'PARA_CERRAR') {
            throw new TransicionMovimientoException(
                'El preflight de conciliacion exige un movimiento en estado PARA_CERRAR.'
            );
        }

        $bloqueos = [];
        $advertencias = [];
        $efectos = [];
        $asociaciones = $this->obtenerAsociaciones($movimientoId);
        $valores = array_values(array_filter($asociaciones, static function (array $fila) {
            return $fila['valor_zetti_id'] !== null;
        }));
        $contables = array_values(array_filter($asociaciones, static function (array $fila) {
            return $fila['asiento_zetti_id'] !== null || $fila['borrador_asiento_id'] !== null;
        }));

        if (count($valores) !== 1) {
            $this->agregarHallazgo(
                $bloqueos,
                'CHEQUE_ASOCIADO_INVALIDO',
                'El movimiento debe tener exactamente un valor activo asociado para conciliar un cheque.'
            );
        }
        if (count($contables) > 0) {
            $this->agregarHallazgo(
                $bloqueos,
                'DESTINO_CONTABLE_AMBIGUO',
                'La conciliacion genera su propio asiento y no admite un asiento o borrador ya asociado.'
            );
        }

        $cheque = null;
        $vinculosErp = [];
        $conciliacion = null;
        $contextoContable = null;
        $politicaDiferencia = null;
        $requiereConciliacion = false;
        if (count($valores) === 1) {
            $valorId = $valores[0]['valor_zetti_id'];
            if (!$this->tieneReservaActiva($movimientoId, $valorId)) {
                $this->agregarHallazgo(
                    $bloqueos,
                    'RESERVA_AUSENTE',
                    'El cheque activo asociado no tiene una reserva activa equivalente.'
                );
            }
            $cheque = $this->obtenerValor($valorId, $movimiento['monto']);
            if ($cheque === null) {
                $this->agregarHallazgo($bloqueos, 'VALOR_INEXISTENTE', 'El valor asociado ya no existe en ERP.');
            } else {
                $vinculosErp = $this->obtenerVinculosErp($valorId);
                $conciliacion = $this->obtenerConciliacionRegistrada($movimientoId, $valorId);
                $contextoContable = $this->obtenerContextoContable(
                    $movimiento['configuracion_id'],
                    $movimiento['cuenta_bancaria_zetti_id'],
                    $cheque['subtipo_valor_zetti_id']
                );
                $politicaDiferencia = [
                    'diferencia' => $cheque['diferencia'],
                    'porcentaje_sobre_cheque' => $cheque['diferencia_porcentaje'],
                    'tolerancia_legacy_porcentaje' => '1.00',
                    'dentro_tolerancia_legacy' => $cheque['dentro_tolerancia_legacy'],
                    'tratamiento' => (float) $cheque['diferencia'] === 0.0
                        ? 'SIN_DIFERENCIA'
                        : 'BLOQUEAR_HASTA_DEFINIR',
                ];
                if (!in_array((int) $cheque['subtipo_valor_zetti_id'], self::SUBTIPOS_CHEQUE, true)) {
                    $this->agregarHallazgo(
                        $bloqueos,
                        'VALOR_NO_ES_CHEQUE',
                        'El valor asociado no pertenece a un subtipo de cheque admitido por BANCOS_MENSUAL.'
                    );
                } elseif ($conciliacion !== null) {
                    $this->agregarHallazgo(
                        $advertencias,
                        'CONCILIACION_YA_REGISTRADA',
                        'Ya existe una conciliacion registrada para este movimiento o cheque.'
                    );
                } elseif ((int) $cheque['estado_erp'] === 7) {
                    $this->agregarHallazgo(
                        $advertencias,
                        'CHEQUE_YA_LIQUIDADO',
                        'El cheque ya esta liquidado en ERP y no requiere una nueva conciliacion.'
                    );
                } elseif (in_array((int) $cheque['estado_erp'], [20, 36], true)) {
                    $this->agregarHallazgo(
                        $bloqueos,
                        'CHEQUE_ESTADO_NO_CONCILIABLE',
                        'El cheque se encuentra en un estado ERP excluido del circuito de conciliacion.'
                    );
                } else {
                    $requiereConciliacion = true;
                }
                if ($requiereConciliacion) {
                    $this->validarContextoContable($contextoContable, $bloqueos, $advertencias);
                    if ((float) $cheque['diferencia'] !== 0.0) {
                        $this->agregarHallazgo(
                            $bloqueos,
                            'DIFERENCIA_CONTABLE_NO_DEFINIDA',
                            'El movimiento y el cheque tienen importes distintos; falta definir la cuenta y regla para esa diferencia.'
                        );
                    }
                    if ($movimiento['moneda'] !== null && $cheque['moneda'] !== null
                        && (int) $movimiento['moneda'] !== (int) $cheque['moneda']
                    ) {
                        $this->agregarHallazgo(
                            $bloqueos,
                            'MONEDA_INCOMPATIBLE',
                            'La moneda del movimiento no coincide con la moneda del cheque ERP.'
                        );
                    }
                }
            }
        }

        if ($requiereConciliacion && count($bloqueos) === 0) {
            $valorId = $cheque['valor_zetti_id'];
            $efectos = [
                ['tipo' => 'VALOR_ORIGEN', 'accion' => 'LIQUIDAR_ESTADO_7', 'recurso_id' => $valorId],
                ['tipo' => 'OPERACION', 'accion' => 'CREAR_OPERACION_CONCILIACION'],
                ['tipo' => 'VALOR_RESULTANTE', 'accion' => 'CREAR_VALOR_BANCARIO_ESTADO_4'],
                ['tipo' => 'OPERACION_VALOR', 'accion' => 'VINCULAR_ORIGEN_Y_RESULTANTE'],
                ['tipo' => 'VALOR_CONCEPTO', 'accion' => 'ASIGNAR_CONCEPTO_376'],
                ['tipo' => 'ASIENTO', 'accion' => 'CREAR_ASIENTO_CONCILIACION'],
                ['tipo' => 'MOVIMIENTO_CONTABLE', 'accion' => 'CREAR_LINEAS_BALANCEADAS'],
                ['tipo' => 'CONCILIACION_CHEQUE', 'accion' => 'REGISTRAR_TRAZABILIDAD'],
            ];
            $this->agregarHallazgo(
                $advertencias,
                'EJECUCION_NO_HABILITADA',
                'El caso es consistente, pero la escritura de conciliacion ERP todavia no esta habilitada.'
            );
        }

        if (count($bloqueos) > 0) {
            $resultado = 'BLOQUEADO';
        } elseif ($conciliacion !== null) {
            $resultado = 'YA_CONCILIADO';
        } elseif (!$requiereConciliacion) {
            $resultado = 'NO_REQUIERE_CONCILIACION';
        } else {
            $resultado = 'LISTO_CON_ADVERTENCIAS';
        }

        return [
            'contrato_version' => 'CONCILIACION-CHEQUE-V1-PREFLIGHT',
            'movimiento_id' => (int) $movimientoId,
            'estado' => $movimiento['estado'],
            'monto_movimiento' => $movimiento['monto'],
            'moneda' => $movimiento['moneda'],
            'fecha_operacion' => $movimiento['fecha_operacion'],
            'referencia' => $movimiento['referencia'],
            'cuenta_bancaria_zetti_id' => $movimiento['cuenta_bancaria_zetti_id'],
            'resultado' => $resultado,
            'listo_para_conciliar' => $requiereConciliacion && count($bloqueos) === 0,
            'requiere_conciliacion' => $requiereConciliacion,
            'ejecutable_ahora' => false,
            'cheque' => $cheque,
            'contexto_contable' => $contextoContable,
            'politica_diferencia' => $politicaDiferencia,
            'vinculos_erp_existentes' => $vinculosErp,
            'conciliacion_registrada' => $conciliacion,
            'efectos_previstos' => $efectos,
            'bloqueos' => $bloqueos,
            'advertencias' => $advertencias,
            'solo_lectura' => true,
        ];
    }

    private function obtenerMovimiento($movimientoId, $cuentaBancariaId, $inicioPeriodo)
    {
        $consulta = $this->pdo->prepare(
            "SELECT m.id, m.moneda, m.fecha_operacion::date::text, m.referencia,
                    i.configuracion_id::text,
                    i.cuenta_bancaria_zetti_id::text,
                    CASE WHEN m.credito > 0 THEN m.credito ELSE m.debito END AS monto,
                    COALESCE((
                        SELECT e.codigo
                        FROM {$this->historial} h
                        JOIN {$this->estados} e ON e.id = h.estado_id
                        WHERE h.movimiento_id = m.id
                        ORDER BY h.registrado_en DESC, h.id DESC LIMIT 1
                    ), ei.codigo) AS estado
             FROM {$this->movimientos} m
             JOIN {$this->importaciones} i ON i.id = m.importacion_id
             JOIN {$this->estados} ei ON ei.id = i.estado_id
             WHERE m.id = :movimiento_id
               AND i.cuenta_bancaria_zetti_id = :cuenta_bancaria_id
               AND i.inicio_periodo = :inicio_periodo"
        );
        $consulta->execute([
            ':movimiento_id' => (int) $movimientoId,
            ':cuenta_bancaria_id' => (string) $cuentaBancariaId,
            ':inicio_periodo' => $inicioPeriodo,
        ]);
        $fila = $consulta->fetch(PDO::FETCH_ASSOC);
        if ($fila === false) {
            throw new MovimientoNoEncontradoException(
                'El movimiento no existe dentro de la cuenta y el periodo indicados.'
            );
        }
        return $fila;
    }

    private function obtenerAsociaciones($movimientoId)
    {
        $consulta = $this->pdo->prepare(
            "SELECT id, valor_zetti_id::text, asiento_zetti_id::text, borrador_asiento_id::text
             FROM {$this->asociaciones}
             WHERE movimiento_id = :movimiento_id AND activo
             ORDER BY id"
        );
        $consulta->execute([':movimiento_id' => (int) $movimientoId]);
        return $consulta->fetchAll(PDO::FETCH_ASSOC);
    }

    private function tieneReservaActiva($movimientoId, $valorId)
    {
        $consulta = $this->pdo->prepare(
            "SELECT EXISTS(
                 SELECT 1 FROM {$this->reservas}
                 WHERE movimiento_id = :movimiento_id
                   AND valor_zetti_id = :valor_id
                   AND activo
             )"
        );
        $consulta->execute([':movimiento_id' => (int) $movimientoId, ':valor_id' => $valorId]);
        return (bool) $consulta->fetchColumn();
    }

    private function obtenerValor($valorId, $montoMovimiento)
    {
        $consulta = $this->pdo->prepare(
            "WITH parametros AS (SELECT CAST(:monto_movimiento AS numeric) AS monto_movimiento)
             SELECT v.id::text AS valor_zetti_id, v.estado AS estado_erp,
                    v.tipo_valor, v.subtipo_valor AS subtipo_valor_zetti_id,
                    COALESCE(s.nombre, '') AS subtipo, v.entidad::text,
                    v.moneda, v.monto_principal,
                    v.fecha_emision::date::text, v.fecha_vencimiento::date::text,
                    (p.monto_movimiento - v.monto_principal)::text AS diferencia,
                    CASE WHEN v.monto_principal = 0 THEN NULL
                         ELSE round(((p.monto_movimiento - v.monto_principal)
                              / abs(v.monto_principal)) * 100, 5)::text END AS diferencia_porcentaje,
                    abs(p.monto_movimiento - v.monto_principal)
                        <= abs(v.monto_principal) * 0.01 AS dentro_tolerancia_legacy
             FROM {$this->valoresErp} v
             CROSS JOIN parametros p
             LEFT JOIN {$this->subtiposErp} s ON s.id = v.subtipo_valor
             WHERE v.id = :valor_id"
        );
        $consulta->execute([':monto_movimiento' => $montoMovimiento, ':valor_id' => $valorId]);
        $fila = $consulta->fetch(PDO::FETCH_ASSOC);
        if ($fila === false) {
            return null;
        }
        $fila['dentro_tolerancia_legacy'] = in_array(
            $fila['dentro_tolerancia_legacy'],
            [true, 1, '1', 't'],
            true
        );
        return $fila;
    }

    private function obtenerContextoContable($configuracionId, $cuentaBancariaId, $subtipoId)
    {
        $consulta = $this->pdo->prepare(
            "SELECT c.id::text AS configuracion_id, c.activo AS configuracion_activa,
                    e.nodo_creacion AS nodo_zetti_id,
                    cb.id::text AS cuenta_banco_zetti_id, cb.codigo AS cuenta_banco_codigo,
                    cb.nombre AS cuenta_banco_nombre, cb.imputable AS cuenta_banco_imputable,
                    cb.nodo AS cuenta_banco_nodo,
                    cv.id::text AS cuenta_valor_zetti_id, cv.codigo AS cuenta_valor_codigo,
                    cv.nombre AS cuenta_valor_nombre, cv.imputable AS cuenta_valor_imputable,
                    cv.nodo AS cuenta_valor_nodo
             FROM {$this->configuraciones} c
             LEFT JOIN {$this->entidadesErp} e ON e.id = :cuenta_bancaria_id
             LEFT JOIN {$this->cuentasErp} cb ON cb.id = c.cuenta_contable_zetti_id
             LEFT JOIN {$this->mapeosContables} mc
               ON mc.configuracion_id = c.id
              AND mc.subtipo_valor_zetti_id = :subtipo_id
              AND mc.activo
             LEFT JOIN {$this->cuentasErp} cv ON cv.id = mc.cuenta_zetti_id
             WHERE c.id = :configuracion_id"
        );
        $consulta->execute([
            ':cuenta_bancaria_id' => $cuentaBancariaId,
            ':subtipo_id' => (int) $subtipoId,
            ':configuracion_id' => $configuracionId,
        ]);
        $fila = $consulta->fetch(PDO::FETCH_ASSOC);
        if ($fila === false) {
            return null;
        }
        return [
            'configuracion_id' => $fila['configuracion_id'],
            'configuracion_activa' => in_array($fila['configuracion_activa'], [true, 1, '1', 't'], true),
            'nodo_zetti_id' => $fila['nodo_zetti_id'] === null ? null : (int) $fila['nodo_zetti_id'],
            'cuenta_banco' => $this->normalizarCuentaContable($fila, 'cuenta_banco'),
            'cuenta_valor' => $this->normalizarCuentaContable($fila, 'cuenta_valor'),
        ];
    }

    private function normalizarCuentaContable(array $fila, $prefijo)
    {
        if ($fila[$prefijo . '_zetti_id'] === null) {
            return null;
        }
        return [
            'cuenta_zetti_id' => $fila[$prefijo . '_zetti_id'],
            'codigo' => $fila[$prefijo . '_codigo'],
            'nombre' => $fila[$prefijo . '_nombre'],
            'imputable' => in_array($fila[$prefijo . '_imputable'], [true, 1, '1', 't'], true),
            'nodo_zetti_id' => $fila[$prefijo . '_nodo'] === null ? null : (int) $fila[$prefijo . '_nodo'],
        ];
    }

    private function validarContextoContable($contexto, array &$bloqueos, array &$advertencias)
    {
        if ($contexto === null || !$contexto['configuracion_activa']) {
            $this->agregarHallazgo(
                $bloqueos,
                'CONFIGURACION_INACTIVA',
                'La importacion no tiene una configuracion contable activa disponible.'
            );
            return;
        }
        if ($contexto['nodo_zetti_id'] === null) {
            $this->agregarHallazgo($bloqueos, 'NODO_NO_RESUELTO', 'No se pudo resolver el nodo de la cuenta bancaria.');
        }
        if ($contexto['cuenta_banco'] === null) {
            $this->agregarHallazgo(
                $bloqueos,
                'CUENTA_BANCO_NO_CONFIGURADA',
                'La configuracion no tiene una cuenta contable base para el banco.'
            );
        }
        if ($contexto['cuenta_valor'] === null) {
            $this->agregarHallazgo(
                $bloqueos,
                'CUENTA_VALOR_NO_CONFIGURADA',
                'No existe un mapeo contable activo para el subtipo del cheque.'
            );
        }
        foreach (['cuenta_banco', 'cuenta_valor'] as $tipo) {
            if ($contexto[$tipo] !== null && !$contexto[$tipo]['imputable']) {
                $this->agregarHallazgo(
                    $advertencias,
                    'CUENTA_NO_IMPUTABLE',
                    'La cuenta ' . ($tipo === 'cuenta_banco' ? 'del banco' : 'del cheque')
                        . ' esta marcada como no imputable en ERP.'
                );
            }
            if ($contexto[$tipo] !== null && $contexto['nodo_zetti_id'] !== null
                && $contexto[$tipo]['nodo_zetti_id'] !== null
                && $contexto[$tipo]['nodo_zetti_id'] !== $contexto['nodo_zetti_id']
            ) {
                $this->agregarHallazgo(
                    $advertencias,
                    'CUENTA_OTRO_NODO',
                    'La cuenta ' . ($tipo === 'cuenta_banco' ? 'del banco' : 'del cheque')
                        . ' pertenece a un nodo distinto del movimiento.'
                );
            }
        }
    }

    private function obtenerVinculosErp($valorId)
    {
        $consulta = $this->pdo->prepare(
            "SELECT id::text, operacion::text AS operacion_zetti_id, estado AS estado_erp, comprobante
             FROM {$this->operacionesValoresErp}
             WHERE valor = :valor_id
             ORDER BY id"
        );
        $consulta->execute([':valor_id' => $valorId]);
        return $consulta->fetchAll(PDO::FETCH_ASSOC);
    }

    private function obtenerConciliacionRegistrada($movimientoId, $valorId)
    {
        $consulta = $this->pdo->prepare(
            "SELECT c.id::text, c.movimiento_id::text, c.valor_origen_zetti_id::text,
                    c.operacion_zetti_id::text, c.valor_resultante_zetti_id::text,
                    c.asiento_zetti_id::text, e.codigo AS estado, c.forzada,
                    c.evidencia_origen, c.conciliado_en
             FROM {$this->conciliaciones} c
             JOIN {$this->estados} e ON e.id = c.estado_id
             WHERE c.movimiento_id = :movimiento_id OR c.valor_origen_zetti_id = :valor_id
             ORDER BY c.conciliado_en DESC, c.id DESC LIMIT 1"
        );
        $consulta->execute([':movimiento_id' => (int) $movimientoId, ':valor_id' => $valorId]);
        $fila = $consulta->fetch(PDO::FETCH_ASSOC);
        return $fila === false ? null : $fila;
    }

    private function agregarHallazgo(array &$destino, $codigo, $mensaje)
    {
        $destino[] = ['codigo' => $codigo, 'mensaje' => $mensaje];
    }
}
