<?php
namespace AppBancos\Repository;

use AppBancos\Application\MovimientoNoEncontradoException;
use AppBancos\Application\RecursoNoDisponibleException;
use AppBancos\Application\TransicionMovimientoException;
use AppBancos\Infrastructure\EsquemaBancos;
use PDO;
use RuntimeException;

final class MovimientoMensualRepository
{
    private $pdo;
    private $movimientos;
    private $importaciones;
    private $historial;
    private $estados;
    private $asociaciones;
    private $reservas;
    private $borradores;
    private $valoresErp;
    private $lineasBorrador;
    private $nodosErp;
    private $cuentasErp;

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
        $this->valoresErp = $esquema->tablaLecturaErp('valor');
        $this->lineasBorrador = $esquema->tablaBancos('bancos_linea_borrador_asiento');
        $this->nodosErp = $esquema->tablaLecturaErp('nodo');
        $this->cuentasErp = $esquema->tablaLecturaErp('cuenta');
    }

    public function preparar($movimientoId, $cuentaBancariaId, $inicioPeriodo, $usuarioId, $clave)
    {
        $consulta = $this->pdo->prepare(
            "SELECT m.id,
                    COALESCE((
                        SELECT eh.codigo
                        FROM {$this->historial} h
                        JOIN {$this->estados} eh ON eh.id = h.estado_id
                        WHERE h.movimiento_id = m.id
                        ORDER BY h.registrado_en DESC, h.id DESC
                        LIMIT 1
                    ), ei.codigo) AS estado_actual
             FROM {$this->movimientos} m
             JOIN {$this->importaciones} i ON i.id = m.importacion_id
             JOIN {$this->estados} ei ON ei.id = i.estado_id
             WHERE m.id = :movimiento_id
               AND i.cuenta_bancaria_zetti_id = :cuenta_bancaria_id
               AND i.inicio_periodo = :inicio_periodo
             FOR UPDATE OF m"
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
        if ($movimiento['estado_actual'] !== 'ABIERTO') {
            throw new TransicionMovimientoException(
                'Solo un movimiento ABIERTO puede pasar a PARA_CERRAR.'
            );
        }

        $consulta = $this->pdo->prepare(
            "SELECT id FROM {$this->estados}
             WHERE codigo = 'PARA_CERRAR' AND activo IS TRUE"
        );
        $consulta->execute();
        $estadoId = $consulta->fetchColumn();
        if ($estadoId === false) {
            throw new RuntimeException('El estado PARA_CERRAR no esta configurado.');
        }

        $consulta = $this->pdo->prepare(
            "INSERT INTO {$this->historial}
                (movimiento_id, estado_id, usuario_hub_id, observacion)
             VALUES
                (:movimiento_id, :estado_id, :usuario_id, :observacion)
             RETURNING id, registrado_en"
        );
        $consulta->execute([
            ':movimiento_id' => (int) $movimientoId,
            ':estado_id' => (int) $estadoId,
            ':usuario_id' => (int) $usuarioId,
            ':observacion' => 'API|PREPARAR|' . $clave,
        ]);
        $evento = $consulta->fetch(PDO::FETCH_ASSOC);
        if ($evento === false) {
            throw new RuntimeException('No se pudo registrar la transicion del movimiento.');
        }

        $consulta = $this->pdo->prepare(
            "UPDATE {$this->movimientos}
             SET fecha_modificacion = current_timestamp,
                 usuario_modificacion = :usuario_id
             WHERE id = :movimiento_id"
        );
        $consulta->execute([
            ':usuario_id' => (int) $usuarioId,
            ':movimiento_id' => (int) $movimientoId,
        ]);

        return [
            'movimiento_id' => (int) $movimientoId,
            'estado_anterior' => 'ABIERTO',
            'estado' => 'PARA_CERRAR',
            'historial_id' => (int) $evento['id'],
            'registrado_en' => (string) $evento['registrado_en'],
        ];
    }

    public function revertirPreparacion(
        $movimientoId,
        $cuentaBancariaId,
        $inicioPeriodo,
        $usuarioId,
        $motivo,
        $clave
    ) {
        $movimiento = $this->bloquearMovimiento(
            $movimientoId,
            $cuentaBancariaId,
            $inicioPeriodo
        );
        if ($movimiento['estado_actual'] !== 'PARA_CERRAR') {
            throw new TransicionMovimientoException(
                'Solo un movimiento PARA_CERRAR puede volver a ABIERTO.'
            );
        }

        $consulta = $this->pdo->prepare(
            "SELECT id FROM {$this->estados} WHERE codigo = 'ABIERTO' AND activo IS TRUE"
        );
        $consulta->execute();
        $estadoId = $consulta->fetchColumn();
        if ($estadoId === false) {
            throw new RuntimeException('El estado ABIERTO no esta configurado.');
        }

        $consulta = $this->pdo->prepare(
            "UPDATE {$this->asociaciones} SET activo = false
             WHERE movimiento_id = :movimiento_id AND activo IS TRUE"
        );
        $consulta->execute([':movimiento_id' => (int) $movimientoId]);
        $asociacionesDescartadas = $consulta->rowCount();

        $consulta = $this->pdo->prepare(
            "UPDATE {$this->reservas} SET activo = false
             WHERE movimiento_id = :movimiento_id AND activo IS TRUE"
        );
        $consulta->execute([':movimiento_id' => (int) $movimientoId]);
        $reservasDescartadas = $consulta->rowCount();

        $consulta = $this->pdo->prepare(
            "UPDATE {$this->borradores}
             SET activo = false,
                 fecha_modificacion = current_timestamp,
                 usuario_modificacion = :usuario_id
             WHERE movimiento_id = :movimiento_id AND activo IS TRUE"
        );
        $consulta->execute([
            ':movimiento_id' => (int) $movimientoId,
            ':usuario_id' => (int) $usuarioId,
        ]);
        $borradoresDescartados = $consulta->rowCount();

        $consulta = $this->pdo->prepare(
            "INSERT INTO {$this->historial}
                (movimiento_id, estado_id, usuario_hub_id, motivo, observacion)
             VALUES
                (:movimiento_id, :estado_id, :usuario_id, :motivo, :observacion)
             RETURNING id, registrado_en"
        );
        $consulta->execute([
            ':movimiento_id' => (int) $movimientoId,
            ':estado_id' => (int) $estadoId,
            ':usuario_id' => (int) $usuarioId,
            ':motivo' => $motivo,
            ':observacion' => 'API|REVERTIR_PREPARACION|' . $clave,
        ]);
        $evento = $consulta->fetch(PDO::FETCH_ASSOC);
        if ($evento === false) {
            throw new RuntimeException('No se pudo registrar la reversion del movimiento.');
        }

        $consulta = $this->pdo->prepare(
            "UPDATE {$this->movimientos}
             SET fecha_modificacion = current_timestamp,
                 usuario_modificacion = :usuario_id
             WHERE id = :movimiento_id"
        );
        $consulta->execute([
            ':usuario_id' => (int) $usuarioId,
            ':movimiento_id' => (int) $movimientoId,
        ]);

        return [
            'movimiento_id' => (int) $movimientoId,
            'estado_anterior' => 'PARA_CERRAR',
            'estado' => 'ABIERTO',
            'motivo' => $motivo,
            'historial_id' => (int) $evento['id'],
            'registrado_en' => (string) $evento['registrado_en'],
            'descartados' => [
                'asociaciones' => $asociacionesDescartadas,
                'reservas' => $reservasDescartadas,
                'borradores' => $borradoresDescartados,
            ],
        ];
    }

    public function asociarValor(
        $movimientoId,
        $cuentaBancariaId,
        $inicioPeriodo,
        $valorId,
        $usuarioId,
        $clave
    ) {
        $movimiento = $this->bloquearMovimiento(
            $movimientoId,
            $cuentaBancariaId,
            $inicioPeriodo
        );
        if ($movimiento['estado_actual'] !== 'ABIERTO') {
            throw new TransicionMovimientoException(
                'Solo se pueden asociar valores a un movimiento ABIERTO.'
            );
        }
        $montoMovimiento = (float) $movimiento['credito'] > 0
            ? (string) $movimiento['credito']
            : (string) $movimiento['debito'];

        $consulta = $this->pdo->prepare(
            "SELECT id, monto_principal, estado,
                    abs(monto_principal) >= CAST(:monto AS numeric) AS monto_suficiente
             FROM {$this->valoresErp}
             WHERE id = :valor_id
             FOR SHARE"
        );
        $consulta->execute([
            ':valor_id' => (int) $valorId,
            ':monto' => $montoMovimiento,
        ]);
        $valor = $consulta->fetch(PDO::FETCH_ASSOC);
        if ($valor === false) {
            throw new RecursoNoDisponibleException('El valor ERP indicado no existe.');
        }
        if (in_array((int) $valor['estado'], [7, 20, 36], true)) {
            throw new RecursoNoDisponibleException('El estado actual del valor ERP no permite asociarlo.');
        }
        if (!in_array($valor['monto_suficiente'], [true, 1, '1', 't'], true)) {
            throw new RecursoNoDisponibleException(
                'El monto disponible del valor ERP es menor que el movimiento.'
            );
        }

        $consulta = $this->pdo->prepare(
            "SELECT EXISTS (
                 SELECT 1 FROM {$this->asociaciones}
                 WHERE movimiento_id = :movimiento_id AND activo IS TRUE AND valor_zetti_id IS NOT NULL
             ) OR EXISTS (
                 SELECT 1 FROM {$this->reservas}
                 WHERE movimiento_id = :movimiento_id AND activo IS TRUE AND valor_zetti_id IS NOT NULL
             )"
        );
        $consulta->execute([':movimiento_id' => (int) $movimientoId]);
        if ($consulta->fetchColumn()) {
            throw new RecursoNoDisponibleException(
                'El movimiento ya tiene un valor asociado o reservado.'
            );
        }

        $consulta = $this->pdo->prepare(
            "INSERT INTO {$this->reservas}
                (movimiento_id, valor_zetti_id, activo, reservado_por, motivo, observacion)
             VALUES
                (:movimiento_id, :valor_id, true, :usuario_id, 'ASOCIACION_VALOR', :observacion)
             ON CONFLICT (valor_zetti_id) WHERE activo AND valor_zetti_id IS NOT NULL
             DO NOTHING
             RETURNING id"
        );
        $consulta->execute([
            ':movimiento_id' => (int) $movimientoId,
            ':valor_id' => (int) $valorId,
            ':usuario_id' => (int) $usuarioId,
            ':observacion' => 'API|ASOCIAR_VALOR|' . $clave,
        ]);
        $reservaId = $consulta->fetchColumn();
        if ($reservaId === false) {
            throw new RecursoNoDisponibleException(
                'El valor ERP fue reservado por otro movimiento.'
            );
        }

        $consulta = $this->pdo->prepare(
            "INSERT INTO {$this->asociaciones}
                (movimiento_id, valor_zetti_id, monto_asociado, activo, observacion, usuario_creacion)
             VALUES
                (:movimiento_id, :valor_id, :monto, true, :observacion, :usuario_id)
             ON CONFLICT (valor_zetti_id) WHERE activo AND valor_zetti_id IS NOT NULL
             DO NOTHING
             RETURNING id"
        );
        $consulta->execute([
            ':movimiento_id' => (int) $movimientoId,
            ':valor_id' => (int) $valorId,
            ':monto' => $montoMovimiento,
            ':usuario_id' => (int) $usuarioId,
            ':observacion' => 'API|ASOCIAR_VALOR|' . $clave,
        ]);
        $asociacionId = $consulta->fetchColumn();
        if ($asociacionId === false) {
            throw new RecursoNoDisponibleException(
                'El valor ERP ya esta asociado a otro movimiento.'
            );
        }

        $consulta = $this->pdo->prepare(
            "UPDATE {$this->movimientos}
             SET fecha_modificacion = current_timestamp, usuario_modificacion = :usuario_id
             WHERE id = :movimiento_id"
        );
        $consulta->execute([
            ':usuario_id' => (int) $usuarioId,
            ':movimiento_id' => (int) $movimientoId,
        ]);

        return [
            'movimiento_id' => (int) $movimientoId,
            'valor_zetti_id' => (int) $valorId,
            'monto_asociado' => $montoMovimiento,
            'reserva_id' => (int) $reservaId,
            'asociacion_id' => (int) $asociacionId,
        ];
    }

    public function crearBorrador(
        $movimientoId,
        $cuentaBancariaId,
        $inicioPeriodo,
        $nodoId,
        $fechaContable,
        $modelo,
        array $lineas,
        $usuarioId,
        $clave
    ) {
        $movimiento = $this->bloquearMovimiento(
            $movimientoId,
            $cuentaBancariaId,
            $inicioPeriodo
        );
        if ($movimiento['estado_actual'] !== 'ABIERTO') {
            throw new TransicionMovimientoException(
                'Solo se puede crear un borrador para un movimiento ABIERTO.'
            );
        }

        $consulta = $this->pdo->prepare("SELECT 1 FROM {$this->nodosErp} WHERE id = :id");
        $consulta->execute([':id' => (int) $nodoId]);
        if ($consulta->fetchColumn() === false) {
            throw new RecursoNoDisponibleException('El nodo ERP indicado no existe.');
        }

        $cuentas = array_values(array_unique(array_map(function ($linea) {
            return (int) $linea['cuenta_zetti_id'];
        }, $lineas)));
        $marcadores = [];
        $parametros = [];
        foreach ($cuentas as $indice => $cuentaId) {
            $marcador = ':cuenta_' . $indice;
            $marcadores[] = $marcador;
            $parametros[$marcador] = $cuentaId;
        }
        $consulta = $this->pdo->prepare(
            "SELECT count(*) FROM {$this->cuentasErp} WHERE id IN (" . implode(', ', $marcadores) . ')'
        );
        $consulta->execute($parametros);
        if ((int) $consulta->fetchColumn() !== count($cuentas)) {
            throw new RecursoNoDisponibleException('Una o mas cuentas ERP no existen.');
        }

        $consulta = $this->pdo->prepare(
            "SELECT EXISTS (
                 SELECT 1 FROM {$this->borradores}
                 WHERE movimiento_id = :movimiento_id AND activo IS TRUE
             ) OR EXISTS (
                 SELECT 1 FROM {$this->reservas}
                 WHERE movimiento_id = :movimiento_id AND activo IS TRUE AND borrador_asiento_id IS NOT NULL
             ) OR EXISTS (
                 SELECT 1 FROM {$this->asociaciones}
                 WHERE movimiento_id = :movimiento_id AND activo IS TRUE AND borrador_asiento_id IS NOT NULL
             )"
        );
        $consulta->execute([':movimiento_id' => (int) $movimientoId]);
        if ($consulta->fetchColumn()) {
            throw new RecursoNoDisponibleException('El movimiento ya tiene un borrador activo.');
        }

        $consulta = $this->pdo->prepare(
            "SELECT id FROM {$this->estados} WHERE codigo = 'ABIERTO' AND activo IS TRUE"
        );
        $consulta->execute();
        $estadoId = $consulta->fetchColumn();
        if ($estadoId === false) {
            throw new RuntimeException('El estado ABIERTO no esta configurado.');
        }

        $claveBorrador = 'API|' . hash('sha256', (string) $clave);
        $consulta = $this->pdo->prepare(
            "INSERT INTO {$this->borradores}
                (movimiento_id, nodo_zetti_id, fecha_contable, estado_id,
                 clave_idempotencia, modelo, activo, observacion,
                 usuario_creacion, usuario_modificacion)
             VALUES
                (:movimiento_id, :nodo_id, :fecha_contable, :estado_id,
                 :clave, :modelo, true, 'BORRADOR_API', :usuario_id, :usuario_id)
             RETURNING id"
        );
        $consulta->execute([
            ':movimiento_id' => (int) $movimientoId,
            ':nodo_id' => (int) $nodoId,
            ':fecha_contable' => $fechaContable,
            ':estado_id' => (int) $estadoId,
            ':clave' => $claveBorrador,
            ':modelo' => $modelo,
            ':usuario_id' => (int) $usuarioId,
        ]);
        $borradorId = $consulta->fetchColumn();
        if ($borradorId === false) {
            throw new RuntimeException('No se pudo crear el borrador contable.');
        }

        $insertarLinea = $this->pdo->prepare(
            "INSERT INTO {$this->lineasBorrador}
                (borrador_asiento_id, cuenta_zetti_id, debe, haber, observacion)
             VALUES
                (:borrador_id, :cuenta_id, :debe, :haber, :observacion)"
        );
        foreach ($lineas as $linea) {
            $insertarLinea->execute([
                ':borrador_id' => (int) $borradorId,
                ':cuenta_id' => (int) $linea['cuenta_zetti_id'],
                ':debe' => $linea['debe'],
                ':haber' => $linea['haber'],
                ':observacion' => $linea['observacion'],
            ]);
        }

        $consulta = $this->pdo->prepare(
            "INSERT INTO {$this->reservas}
                (movimiento_id, borrador_asiento_id, activo, reservado_por, motivo, observacion)
             VALUES
                (:movimiento_id, :borrador_id, true, :usuario_id, 'BORRADOR', :observacion)
             RETURNING id"
        );
        $consulta->execute([
            ':movimiento_id' => (int) $movimientoId,
            ':borrador_id' => (int) $borradorId,
            ':usuario_id' => (int) $usuarioId,
            ':observacion' => 'API|CREAR_BORRADOR|' . $clave,
        ]);
        $reservaId = $consulta->fetchColumn();

        $consulta = $this->pdo->prepare(
            "INSERT INTO {$this->asociaciones}
                (movimiento_id, borrador_asiento_id, activo, observacion, usuario_creacion)
             VALUES
                (:movimiento_id, :borrador_id, true, :observacion, :usuario_id)
             RETURNING id"
        );
        $consulta->execute([
            ':movimiento_id' => (int) $movimientoId,
            ':borrador_id' => (int) $borradorId,
            ':usuario_id' => (int) $usuarioId,
            ':observacion' => 'API|CREAR_BORRADOR|' . $clave,
        ]);
        $asociacionId = $consulta->fetchColumn();
        if ($reservaId === false || $asociacionId === false) {
            throw new RuntimeException('No se pudo reservar y asociar el borrador.');
        }

        $consulta = $this->pdo->prepare(
            "UPDATE {$this->movimientos}
             SET fecha_modificacion = current_timestamp, usuario_modificacion = :usuario_id
             WHERE id = :movimiento_id"
        );
        $consulta->execute([
            ':usuario_id' => (int) $usuarioId,
            ':movimiento_id' => (int) $movimientoId,
        ]);

        return [
            'movimiento_id' => (int) $movimientoId,
            'borrador_id' => (int) $borradorId,
            'reserva_id' => (int) $reservaId,
            'asociacion_id' => (int) $asociacionId,
            'cantidad_lineas' => count($lineas),
        ];
    }

    private function bloquearMovimiento($movimientoId, $cuentaBancariaId, $inicioPeriodo)
    {
        $consulta = $this->pdo->prepare(
            "SELECT m.id, m.credito, m.debito, m.moneda,
                    COALESCE((
                        SELECT eh.codigo
                        FROM {$this->historial} h
                        JOIN {$this->estados} eh ON eh.id = h.estado_id
                        WHERE h.movimiento_id = m.id
                        ORDER BY h.registrado_en DESC, h.id DESC
                        LIMIT 1
                    ), ei.codigo) AS estado_actual
             FROM {$this->movimientos} m
             JOIN {$this->importaciones} i ON i.id = m.importacion_id
             JOIN {$this->estados} ei ON ei.id = i.estado_id
             WHERE m.id = :movimiento_id
               AND i.cuenta_bancaria_zetti_id = :cuenta_bancaria_id
               AND i.inicio_periodo = :inicio_periodo
             FOR UPDATE OF m"
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
}
