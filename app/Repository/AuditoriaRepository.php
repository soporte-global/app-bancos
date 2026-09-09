<?php
namespace AppBancos\Repository;

use AppBancos\Infrastructure\EsquemaBancos;
use PDO;
use RuntimeException;

final class AuditoriaRepository
{
    private $pdo;
    private $tabla;

    public function __construct(PDO $pdo, EsquemaBancos $esquema)
    {
        $this->pdo = $pdo;
        $this->tabla = $esquema->tablaBancos('bancos_evento_auditoria');
    }

    public function registrar($solicitudId, $usuarioId, array $evento)
    {
        $detalles = isset($evento['detalles']) && is_array($evento['detalles'])
            ? $evento['detalles']
            : [];
        $json = json_encode($detalles, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('No se pudieron serializar los detalles de auditoria.');
        }

        $consulta = $this->pdo->prepare(
            "INSERT INTO {$this->tabla}
                (solicitud_id, usuario_hub_id, accion, recurso_tipo, recurso_id, resultado, detalles)
             VALUES
                (:solicitud_id, :usuario_id, :accion, :recurso_tipo, :recurso_id, :resultado, CAST(:detalles AS jsonb))"
        );
        $consulta->execute([
            ':solicitud_id' => (int) $solicitudId,
            ':usuario_id' => (int) $usuarioId,
            ':accion' => (string) $evento['accion'],
            ':recurso_tipo' => (string) $evento['recurso_tipo'],
            ':recurso_id' => isset($evento['recurso_id']) && $evento['recurso_id'] !== null
                ? (string) $evento['recurso_id']
                : null,
            ':resultado' => (string) ($evento['resultado'] ?? 'EXITOSA'),
            ':detalles' => $json,
        ]);
    }
}
