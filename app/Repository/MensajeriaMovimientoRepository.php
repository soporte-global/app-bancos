<?php
namespace AppBancos\Repository;

use AppBancos\Application\MovimientoNoEncontradoException;
use AppBancos\Infrastructure\EsquemaBancos;
use PDO;
use RuntimeException;

final class MensajeriaMovimientoRepository
{
    private $pdo;
    private $movimientos;
    private $importaciones;
    private $mensajes;
    private $recepciones;

    public function __construct(PDO $pdo, EsquemaBancos $esquema)
    {
        $this->pdo = $pdo;
        $this->movimientos = $esquema->tablaBancos('bancos_movimiento_extracto');
        $this->importaciones = $esquema->tablaBancos('bancos_importacion_extracto');
        $this->mensajes = $esquema->tablaBancos('bancos_mensaje_movimiento');
        $this->recepciones = $esquema->tablaBancos('bancos_recepcion_mensaje');
    }

    public function agregar($movimientoId, $cuentaBancariaId, $inicioPeriodo, $usuarioId, $cuerpo)
    {
        $this->bloquearMovimiento($movimientoId, $cuentaBancariaId, $inicioPeriodo);
        $consulta = $this->pdo->prepare(
            "INSERT INTO {$this->mensajes}
                (movimiento_id, emisor_hub_id, tipo_mensaje, cuerpo)
             VALUES (:movimiento_id, :usuario_id, 'USUARIO', :cuerpo)
             RETURNING id, tipo_mensaje, cuerpo, emitido_en"
        );
        $consulta->execute([
            ':movimiento_id' => (int) $movimientoId,
            ':usuario_id' => (int) $usuarioId,
            ':cuerpo' => $cuerpo,
        ]);
        $mensaje = $consulta->fetch(PDO::FETCH_ASSOC);
        if ($mensaje === false) {
            throw new RuntimeException('No se pudo registrar el mensaje.');
        }

        $consulta = $this->pdo->prepare(
            "INSERT INTO {$this->recepciones} AS recepcion (mensaje_id, receptor_hub_id, leido_en)
             VALUES (:mensaje_id, :usuario_id, current_timestamp)
             ON CONFLICT (mensaje_id, receptor_hub_id)
             DO UPDATE SET leido_en = COALESCE(recepcion.leido_en, EXCLUDED.leido_en)"
        );
        $consulta->execute([
            ':mensaje_id' => (int) $mensaje['id'],
            ':usuario_id' => (int) $usuarioId,
        ]);

        return [
            'movimiento_id' => (int) $movimientoId,
            'mensaje_id' => (int) $mensaje['id'],
            'tipo_mensaje' => $mensaje['tipo_mensaje'],
            'cuerpo' => $mensaje['cuerpo'],
            'emitido_en' => (string) $mensaje['emitido_en'],
        ];
    }

    public function marcarLeidos($movimientoId, $cuentaBancariaId, $inicioPeriodo, $usuarioId)
    {
        $this->bloquearMovimiento($movimientoId, $cuentaBancariaId, $inicioPeriodo);
        $consulta = $this->pdo->prepare(
            "INSERT INTO {$this->recepciones} AS recepcion (mensaje_id, receptor_hub_id, leido_en)
             SELECT m.id, :usuario_id, current_timestamp
             FROM {$this->mensajes} m
             WHERE m.movimiento_id = :movimiento_id
               AND m.emisor_hub_id IS DISTINCT FROM :usuario_id
             ON CONFLICT (mensaje_id, receptor_hub_id)
             DO UPDATE SET leido_en = COALESCE(recepcion.leido_en, EXCLUDED.leido_en)"
        );
        $consulta->execute([
            ':movimiento_id' => (int) $movimientoId,
            ':usuario_id' => (int) $usuarioId,
        ]);

        $consulta = $this->pdo->prepare(
            "SELECT count(*) AS cantidad, max(r.leido_en) AS leido_en
             FROM {$this->mensajes} m
             JOIN {$this->recepciones} r
               ON r.mensaje_id = m.id AND r.receptor_hub_id = :usuario_id
             WHERE m.movimiento_id = :movimiento_id
               AND m.emisor_hub_id IS DISTINCT FROM :usuario_id"
        );
        $consulta->execute([
            ':movimiento_id' => (int) $movimientoId,
            ':usuario_id' => (int) $usuarioId,
        ]);
        $lectura = $consulta->fetch(PDO::FETCH_ASSOC);

        return [
            'movimiento_id' => (int) $movimientoId,
            'mensajes_leidos' => (int) $lectura['cantidad'],
            'leido_en' => $lectura['leido_en'] === null ? null : (string) $lectura['leido_en'],
        ];
    }

    private function bloquearMovimiento($movimientoId, $cuentaBancariaId, $inicioPeriodo)
    {
        $consulta = $this->pdo->prepare(
            "SELECT m.id
             FROM {$this->movimientos} m
             JOIN {$this->importaciones} i ON i.id = m.importacion_id
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
        if ($consulta->fetchColumn() === false) {
            throw new MovimientoNoEncontradoException(
                'El movimiento no existe dentro de la cuenta y el periodo indicados.'
            );
        }
    }
}
