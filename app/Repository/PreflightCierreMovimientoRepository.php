<?php
namespace AppBancos\Repository;

use AppBancos\Application\MovimientoNoEncontradoException;
use AppBancos\Application\TransicionMovimientoException;
use AppBancos\Infrastructure\EsquemaBancos;
use PDO;

final class PreflightCierreMovimientoRepository
{
    private $pdo;
    private $movimientos;
    private $importaciones;
    private $historial;
    private $estados;
    private $asociaciones;
    private $reservas;
    private $borradores;
    private $lineasBorrador;
    private $valoresErp;
    private $subtiposValorErp;
    private $asientosErp;
    private $movimientosErp;
    private $periodosErp;
    private $conciliacionesCheque;

    public function __construct(PDO $pdo, EsquemaBancos $esquema)
    {
        $this->pdo = $pdo;
        $this->movimientos = $esquema->tablaBancos('bancos_movimiento_extracto');
        $this->importaciones = $esquema->tablaBancos('bancos_importacion_extracto');
        $this->historial = $esquema->tablaBancos('bancos_historial_asignacion');
        $this->estados = $esquema->tablaBancos('bancos_estado');
        $this->asociaciones = $esquema->tablaBancos('bancos_asociacion_movimiento');
        $this->reservas = $esquema->tablaBancos('bancos_reserva_recurso');
        $this->borradores = $esquema->tablaBancos('bancos_borrador_asiento');
        $this->lineasBorrador = $esquema->tablaBancos('bancos_linea_borrador_asiento');
        $this->valoresErp = $esquema->tablaLecturaErp('valor');
        $this->subtiposValorErp = $esquema->tablaLecturaErp('subtipo_valor');
        $this->asientosErp = $esquema->tablaLecturaErp('asiento');
        $this->movimientosErp = $esquema->tablaLecturaErp('movimiento');
        $this->periodosErp = $esquema->tablaLecturaErp('periodo');
        $this->conciliacionesCheque = $esquema->tablaBancos('bancos_conciliacion_cheque');
    }

    public function evaluar($movimientoId, $cuentaBancariaId, $inicioPeriodo)
    {
        $movimiento = $this->obtenerMovimiento($movimientoId, $cuentaBancariaId, $inicioPeriodo);
        if ($movimiento['estado'] !== 'PARA_CERRAR') {
            throw new TransicionMovimientoException(
                'El preflight de cierre exige un movimiento en estado PARA_CERRAR.'
            );
        }

        $asociaciones = $this->obtenerAsociaciones($movimientoId);
        $reservas = $this->obtenerReservas($movimientoId);
        $bloqueos = [];
        $advertencias = [];
        $efectos = [];
        $tipos = ['VALOR' => 0, 'ASIENTO' => 0, 'BORRADOR' => 0];

        foreach ($asociaciones as $asociacion) {
            $tipo = $this->tipoRecurso($asociacion);
            $tipos[$tipo]++;
            if (!$this->tieneReservaCorrespondiente($asociacion, $reservas)) {
                $this->agregarHallazgo(
                    $bloqueos,
                    'RESERVA_AUSENTE',
                    'La asociacion activa de ' . strtolower($tipo) . ' no tiene una reserva activa equivalente.'
                );
            }
        }
        foreach ($reservas as $reserva) {
            if (!$this->tieneAsociacionCorrespondiente($reserva, $asociaciones)) {
                $this->agregarHallazgo(
                    $bloqueos,
                    'RESERVA_HUERFANA',
                    'Existe una reserva activa sin una asociacion activa equivalente.'
                );
            }
        }

        if ($tipos['ASIENTO'] > 0 && $tipos['BORRADOR'] > 0) {
            $this->agregarHallazgo(
                $bloqueos,
                'DESTINO_CONTABLE_AMBIGUO',
                'El movimiento no puede cerrar con un asiento existente y un borrador activos al mismo tiempo.'
            );
        }
        if (array_sum($tipos) === 0) {
            $this->agregarHallazgo(
                $advertencias,
                'SIN_EFECTO_ERP',
                'El movimiento no tiene asociaciones activas; el legado permitia cerrarlo sin efectos ERP.'
            );
        }

        foreach ($asociaciones as $asociacion) {
            if ($asociacion['valor_zetti_id'] !== null) {
                $this->evaluarValor($asociacion, $bloqueos, $advertencias, $efectos);
            } elseif ($asociacion['asiento_zetti_id'] !== null) {
                $this->evaluarAsiento($asociacion, $bloqueos, $advertencias, $efectos);
            } else {
                $this->evaluarBorrador($asociacion, $bloqueos, $efectos);
            }
        }

        $efectos[] = [
            'tipo' => 'ESTADO_MOVIMIENTO',
            'accion' => 'REGISTRAR_CERRADO',
            'recurso_id' => (string) $movimientoId,
        ];
        $listo = count($bloqueos) === 0;
        $ejecutableAhora = $listo;
        $requiereValor = false;
        $requiereBorrador = false;
        foreach ($efectos as $efecto) {
            if ($efecto['tipo'] === 'BORRADOR_ASIENTO') {
                if (!in_array($efecto['accion'], ['CREAR_ASIENTO', 'SIN_CAMBIOS_YA_MATERIALIZADO'], true)) {
                    $ejecutableAhora = false;
                    break;
                }
                $requiereBorrador = true;
            }
            if ($efecto['tipo'] === 'VALOR') {
                if (!in_array($efecto['accion'], ['LIQUIDAR_ESTADO_7', 'SIN_CAMBIOS_YA_LIQUIDADO'], true)) {
                    $ejecutableAhora = false;
                    break;
                }
                $requiereValor = true;
            }
        }
        if ($requiereValor && $requiereBorrador) {
            $alcanceEjecucion = 'VALOR_Y_BORRADOR_ASIENTO';
        } elseif ($requiereBorrador) {
            $alcanceEjecucion = 'BORRADOR_ASIENTO';
        } elseif ($requiereValor) {
            $alcanceEjecucion = 'VALOR_NO_CHEQUE';
        } else {
            $alcanceEjecucion = 'SIN_EFECTO_ERP';
        }
        if ($listo && !$ejecutableAhora) {
            $this->agregarHallazgo(
                $advertencias,
                'EFECTO_ERP_NO_HABILITADO',
                'El caso es consistente, pero requiere un efecto ERP que todavia no esta habilitado.'
            );
        }
        return [
            'contrato_version' => 'CIERRE-MENSUAL-V1-PREFLIGHT',
            'movimiento_id' => (int) $movimientoId,
            'estado' => $movimiento['estado'],
            'monto' => $movimiento['monto'],
            'moneda' => $movimiento['moneda'],
            'listo' => $listo,
            'ejecutable_ahora' => $ejecutableAhora,
            'alcance_ejecucion' => $ejecutableAhora ? $alcanceEjecucion : 'NO_HABILITADO',
            'resultado' => $listo
                ? (count($advertencias) > 0 ? 'LISTO_CON_ADVERTENCIAS' : 'LISTO')
                : 'BLOQUEADO',
            'efectos_previstos' => $efectos,
            'bloqueos' => $bloqueos,
            'advertencias' => $advertencias,
            'solo_lectura' => true,
        ];
    }

    private function obtenerMovimiento($movimientoId, $cuentaBancariaId, $inicioPeriodo)
    {
        $consulta = $this->pdo->prepare(
            "SELECT m.id, m.moneda,
                    CASE WHEN m.credito > 0 THEN m.credito ELSE m.debito END AS monto,
                    COALESCE((
                        SELECT eh.codigo
                        FROM {$this->historial} h
                        JOIN {$this->estados} eh ON eh.id = h.estado_id
                        WHERE h.movimiento_id = m.id
                        ORDER BY h.registrado_en DESC, h.id DESC
                        LIMIT 1
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
        $movimiento = $consulta->fetch(PDO::FETCH_ASSOC);
        if ($movimiento === false) {
            throw new MovimientoNoEncontradoException(
                'El movimiento no existe dentro de la cuenta y el periodo indicados.'
            );
        }
        return $movimiento;
    }

    private function obtenerAsociaciones($movimientoId)
    {
        $consulta = $this->pdo->prepare(
            "SELECT * FROM (
                 SELECT id, movimiento_id, valor_zetti_id::text, asiento_zetti_id::text,
                        borrador_asiento_id::text, compartido
                 FROM {$this->asociaciones}
                 WHERE movimiento_id = :movimiento_id AND activo AND valor_zetti_id IS NOT NULL
                 UNION ALL
                 SELECT id, movimiento_id, valor_zetti_id::text, asiento_zetti_id::text,
                        borrador_asiento_id::text, compartido
                 FROM {$this->asociaciones}
                 WHERE movimiento_id = :movimiento_id AND activo AND asiento_zetti_id IS NOT NULL
                 UNION ALL
                 SELECT id, movimiento_id, valor_zetti_id::text, asiento_zetti_id::text,
                        borrador_asiento_id::text, compartido
                 FROM {$this->asociaciones}
                 WHERE movimiento_id = :movimiento_id AND activo AND borrador_asiento_id IS NOT NULL
             ) asociaciones_activas
             ORDER BY id"
        );
        $consulta->execute([':movimiento_id' => (int) $movimientoId]);
        return $consulta->fetchAll(PDO::FETCH_ASSOC);
    }

    private function obtenerReservas($movimientoId)
    {
        $consulta = $this->pdo->prepare(
            "SELECT * FROM (
                 SELECT id, valor_zetti_id::text, asiento_zetti_id::text,
                        borrador_asiento_id::text, compartido
                 FROM {$this->reservas}
                 WHERE movimiento_id = :movimiento_id AND activo AND valor_zetti_id IS NOT NULL
                 UNION ALL
                 SELECT id, valor_zetti_id::text, asiento_zetti_id::text,
                        borrador_asiento_id::text, compartido
                 FROM {$this->reservas}
                 WHERE movimiento_id = :movimiento_id AND activo AND asiento_zetti_id IS NOT NULL
                 UNION ALL
                 SELECT id, valor_zetti_id::text, asiento_zetti_id::text,
                        borrador_asiento_id::text, compartido
                 FROM {$this->reservas}
                 WHERE movimiento_id = :movimiento_id AND activo AND borrador_asiento_id IS NOT NULL
             ) reservas_activas
             ORDER BY id"
        );
        $consulta->execute([':movimiento_id' => (int) $movimientoId]);
        return $consulta->fetchAll(PDO::FETCH_ASSOC);
    }

    private function evaluarValor(array $asociacion, array &$bloqueos, array &$advertencias, array &$efectos)
    {
        $consulta = $this->pdo->prepare(
            "SELECT v.id::text, v.estado, v.monto_principal,
                    COALESCE(s.nombre, '') AS subtipo,
                    upper(COALESCE(s.nombre, '')) LIKE '%CHEQUE%' AS es_cheque
             FROM {$this->valoresErp} v
             LEFT JOIN {$this->subtiposValorErp} s ON s.id = v.subtipo_valor
             WHERE v.id = :id"
        );
        $consulta->execute([':id' => $asociacion['valor_zetti_id']]);
        $valor = $consulta->fetch(PDO::FETCH_ASSOC);
        if ($valor === false) {
            $this->agregarHallazgo($bloqueos, 'VALOR_INEXISTENTE', 'El valor asociado ya no existe en ERP.');
            return;
        }
        $esCheque = in_array($valor['es_cheque'], [true, 1, '1', 't'], true);
        if ((int) $valor['estado'] === 7) {
            $efectos[] = ['tipo' => 'VALOR', 'accion' => 'SIN_CAMBIOS_YA_LIQUIDADO', 'recurso_id' => $valor['id']];
            return;
        }
        if ($esCheque) {
            $consulta = $this->pdo->prepare(
                "SELECT id::text FROM {$this->conciliacionesCheque}
                 WHERE movimiento_id=:movimiento_id AND valor_origen_zetti_id=:valor_id
                 ORDER BY id DESC LIMIT 1"
            );
            $consulta->execute([
                ':movimiento_id' => (int) $asociacion['movimiento_id'],
                ':valor_id' => $valor['id'],
            ]);
            $conciliacionId = $consulta->fetchColumn();
            if ($conciliacionId !== false) {
                $efectos[] = [
                    'tipo' => 'CONCILIACION_CHEQUE',
                    'accion' => 'SIN_CAMBIOS_YA_CONCILIADO',
                    'recurso_id' => (string) $conciliacionId,
                ];
                return;
            }
            $this->agregarHallazgo(
                $bloqueos,
                'CHEQUE_REQUIERE_CONCILIACION',
                'El cheque asociado no esta liquidado y requiere el modulo de conciliacion antes del cierre.'
            );
            return;
        }
        if (in_array((int) $valor['estado'], [20, 36], true)) {
            $this->agregarHallazgo(
                $bloqueos,
                'VALOR_ESTADO_NO_CERRABLE',
                'El valor asociado se encuentra en un estado ERP que no admite liquidacion.'
            );
            return;
        }
        $efectos[] = ['tipo' => 'VALOR', 'accion' => 'LIQUIDAR_ESTADO_7', 'recurso_id' => $valor['id']];
        $this->agregarHallazgo(
            $advertencias,
            'VALOR_SERA_LIQUIDADO',
            'El cierre definitivo cambiaria el estado ERP del valor a liquidado.'
        );
    }

    private function evaluarAsiento(array $asociacion, array &$bloqueos, array &$advertencias, array &$efectos)
    {
        $consulta = $this->pdo->prepare(
            "SELECT a.id::text, a.rev, a.periodo,
                    count(m.id) AS cantidad_lineas,
                    COALESCE(sum(CASE WHEN m.debita THEN abs(m.monto) ELSE 0 END), 0) AS total_debe,
                    COALESCE(sum(CASE WHEN NOT m.debita THEN abs(m.monto) ELSE 0 END), 0) AS total_haber,
                    COALESCE(p.cerrado, false) AS periodo_cerrado
             FROM {$this->asientosErp} a
             LEFT JOIN {$this->movimientosErp} m ON m.asiento = a.id
             LEFT JOIN {$this->periodosErp} p ON p.id = a.periodo
             WHERE a.id = :id
             GROUP BY a.id, a.rev, a.periodo, p.cerrado"
        );
        $consulta->execute([':id' => $asociacion['asiento_zetti_id']]);
        $asiento = $consulta->fetch(PDO::FETCH_ASSOC);
        if ($asiento === false) {
            $this->agregarHallazgo($bloqueos, 'ASIENTO_INEXISTENTE', 'El asiento asociado ya no existe en ERP.');
            return;
        }
        if (in_array($asiento['rev'], [true, 1, '1', 't'], true)) {
            $this->agregarHallazgo($bloqueos, 'ASIENTO_REVERTIDO', 'El asiento asociado esta revertido en ERP.');
        }
        if ((int) $asiento['cantidad_lineas'] < 2 || $asiento['total_debe'] !== $asiento['total_haber']) {
            $this->agregarHallazgo($bloqueos, 'ASIENTO_INVALIDO', 'El asiento asociado no tiene al menos dos lineas balanceadas.');
        }
        if (in_array($asiento['periodo_cerrado'], [true, 1, '1', 't'], true)) {
            $this->agregarHallazgo(
                $advertencias,
                'PERIODO_CONTABLE_CERRADO',
                'El asiento existente pertenece a un periodo cerrado; el preflight no propone modificarlo.'
            );
        }
        $efectos[] = ['tipo' => 'ASIENTO_EXISTENTE', 'accion' => 'SIN_CAMBIOS', 'recurso_id' => $asiento['id']];
    }

    private function evaluarBorrador(array $asociacion, array &$bloqueos, array &$efectos)
    {
        $consulta = $this->pdo->prepare(
            "SELECT b.id::text, b.activo, b.asiento_zetti_id::text, b.fecha_contable,
                    b.nodo_zetti_id, count(l.id) AS cantidad_lineas,
                    COALESCE(sum(l.debe), 0) AS total_debe,
                    COALESCE(sum(l.haber), 0) AS total_haber
             FROM {$this->borradores} b
             LEFT JOIN {$this->lineasBorrador} l ON l.borrador_asiento_id = b.id
             WHERE b.id = :id
             GROUP BY b.id, b.activo, b.asiento_zetti_id, b.fecha_contable, b.nodo_zetti_id"
        );
        $consulta->execute([':id' => $asociacion['borrador_asiento_id']]);
        $borrador = $consulta->fetch(PDO::FETCH_ASSOC);
        if ($borrador === false || !in_array($borrador['activo'], [true, 1, '1', 't'], true)) {
            $this->agregarHallazgo($bloqueos, 'BORRADOR_INACTIVO', 'El borrador asociado no existe o ya no esta activo.');
            return;
        }
        if ((int) $borrador['cantidad_lineas'] < 2 || (int) $borrador['cantidad_lineas'] > 200
            || $borrador['total_debe'] !== $borrador['total_haber']
        ) {
            $this->agregarHallazgo($bloqueos, 'BORRADOR_INVALIDO', 'El borrador debe tener entre 2 y 200 lineas balanceadas.');
        }
        $accion = $borrador['asiento_zetti_id'] === null ? 'CREAR_ASIENTO' : 'SIN_CAMBIOS_YA_MATERIALIZADO';
        $efectos[] = [
            'tipo' => 'BORRADOR_ASIENTO',
            'accion' => $accion,
            'recurso_id' => $borrador['id'],
            'fecha_contable' => $borrador['fecha_contable'],
            'nodo_zetti_id' => (int) $borrador['nodo_zetti_id'],
            'cantidad_lineas' => (int) $borrador['cantidad_lineas'],
        ];
    }

    private function tipoRecurso(array $fila)
    {
        if ($fila['valor_zetti_id'] !== null) {
            return 'VALOR';
        }
        if ($fila['asiento_zetti_id'] !== null) {
            return 'ASIENTO';
        }
        return 'BORRADOR';
    }

    private function tieneReservaCorrespondiente(array $asociacion, array $reservas)
    {
        foreach ($reservas as $reserva) {
            if ($this->mismoRecurso($asociacion, $reserva)) {
                if ($asociacion['asiento_zetti_id'] !== null
                    && (bool) $asociacion['compartido'] !== (bool) $reserva['compartido']
                ) {
                    continue;
                }
                return true;
            }
        }
        return false;
    }

    private function tieneAsociacionCorrespondiente(array $reserva, array $asociaciones)
    {
        foreach ($asociaciones as $asociacion) {
            if ($this->mismoRecurso($asociacion, $reserva)) {
                return true;
            }
        }
        return false;
    }

    private function mismoRecurso(array $primera, array $segunda)
    {
        foreach (['valor_zetti_id', 'asiento_zetti_id', 'borrador_asiento_id'] as $campo) {
            if ($primera[$campo] !== null && $primera[$campo] === $segunda[$campo]) {
                return true;
            }
        }
        return false;
    }

    private function agregarHallazgo(array &$destino, $codigo, $mensaje)
    {
        foreach ($destino as $existente) {
            if ($existente['codigo'] === $codigo && $existente['mensaje'] === $mensaje) {
                return;
            }
        }
        $destino[] = ['codigo' => $codigo, 'mensaje' => $mensaje];
    }
}
