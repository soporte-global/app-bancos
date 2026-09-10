<?php
namespace AppBancos\Repository;

use AppBancos\Application\MovimientoNoEncontradoException;
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

    private function bloquearMovimiento($movimientoId, $cuentaBancariaId, $inicioPeriodo)
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
        return $movimiento;
    }
}
