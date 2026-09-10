<?php
namespace AppBancos\Repository;

use AppBancos\Application\MovimientoNoEncontradoException;
use AppBancos\Application\RecursoNoDisponibleException;
use AppBancos\Infrastructure\EsquemaBancos;
use PDO;
use RuntimeException;

final class AsignacionMovimientoRepository
{
    private $pdo;
    private $movimientos;
    private $importaciones;
    private $historial;

    public function __construct(PDO $pdo, EsquemaBancos $esquema)
    {
        $this->pdo = $pdo;
        $this->movimientos = $esquema->tablaBancos('bancos_movimiento_extracto');
        $this->importaciones = $esquema->tablaBancos('bancos_importacion_extracto');
        $this->historial = $esquema->tablaBancos('bancos_historial_asignacion');
    }

    public function asignar(
        $movimientoId,
        $cuentaBancariaId,
        $inicioPeriodo,
        $responsableId,
        $operadorId,
        $clave
    ) {
        $consulta = $this->pdo->prepare(
            "SELECT m.id, COALESCE(h.estado_id, i.estado_id) AS estado_id,
                    h.usuario_hub_id AS responsable_anterior_id
             FROM {$this->movimientos} m
             JOIN {$this->importaciones} i ON i.id = m.importacion_id
             LEFT JOIN LATERAL (
                 SELECT ha.estado_id, ha.usuario_hub_id
                 FROM {$this->historial} ha
                 WHERE ha.movimiento_id = m.id
                 ORDER BY ha.registrado_en DESC, ha.id DESC
                 LIMIT 1
             ) h ON true
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

        $consulta = $this->pdo->prepare(
            "SELECT rl.usuario
             FROM global_prod.rrhh_login rl
             WHERE rl.id = :responsable_id
               AND rl.habilitado IS TRUE
               AND rl.fecha_eliminacion IS NULL
               AND EXISTS (
                   SELECT 1
                   FROM global_prod.hub_permisos_efectivos_usuario e
                   JOIN global_prod.hub_permisos p ON p.id = e.permiso
                   WHERE e.usuario = rl.id
                     AND p.aplicacion = :aplicacion_id
                     AND p.tipo_permiso = 1
                     AND p.ignorado IS NULL
               )"
        );
        $consulta->execute([':responsable_id' => (int) $responsableId, ':aplicacion_id' => ID_APLICACION]);
        $usuario = $consulta->fetchColumn();
        if ($usuario === false) {
            throw new RecursoNoDisponibleException(
                'El responsable no tiene acceso efectivo a APP BANCOS.'
            );
        }

        $anterior = $movimiento['responsable_anterior_id'] === null
            ? null
            : (int) $movimiento['responsable_anterior_id'];
        if ($anterior === (int) $responsableId) {
            return [
                'movimiento_id' => (int) $movimientoId,
                'responsable_anterior_id' => $anterior,
                'responsable_id' => (int) $responsableId,
                'responsable' => $usuario,
                'historial_id' => null,
                'cambio' => false,
            ];
        }

        $consulta = $this->pdo->prepare(
            "INSERT INTO {$this->historial}
                (movimiento_id, estado_id, usuario_hub_id, observacion)
             VALUES (:movimiento_id, :estado_id, :responsable_id, :observacion)
             RETURNING id, registrado_en"
        );
        $consulta->execute([
            ':movimiento_id' => (int) $movimientoId,
            ':estado_id' => (int) $movimiento['estado_id'],
            ':responsable_id' => (int) $responsableId,
            ':observacion' => 'API|ASIGNAR_RESPONSABLE|OPERADOR:' . (int) $operadorId . '|' . $clave,
        ]);
        $evento = $consulta->fetch(PDO::FETCH_ASSOC);
        if ($evento === false) {
            throw new RuntimeException('No se pudo registrar la asignacion.');
        }

        $consulta = $this->pdo->prepare(
            "UPDATE {$this->movimientos}
             SET fecha_modificacion = current_timestamp,
                 usuario_modificacion = :operador_id
             WHERE id = :movimiento_id"
        );
        $consulta->execute([':operador_id' => (int) $operadorId, ':movimiento_id' => (int) $movimientoId]);

        return [
            'movimiento_id' => (int) $movimientoId,
            'responsable_anterior_id' => $anterior,
            'responsable_id' => (int) $responsableId,
            'responsable' => $usuario,
            'historial_id' => (int) $evento['id'],
            'registrado_en' => (string) $evento['registrado_en'],
            'cambio' => true,
        ];
    }
}
