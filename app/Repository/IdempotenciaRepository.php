<?php
namespace AppBancos\Repository;

use AppBancos\Infrastructure\EsquemaBancos;
use PDO;
use RuntimeException;

final class IdempotenciaRepository
{
    private $pdo;
    private $tabla;

    public function __construct(PDO $pdo, EsquemaBancos $esquema)
    {
        $this->pdo = $pdo;
        $this->tabla = $esquema->tablaBancos('bancos_solicitud_idempotente');
    }

    public function iniciar($operacion, $clave, $usuarioId, $huella)
    {
        $sql = "INSERT INTO {$this->tabla}
                    (operacion, clave, usuario_hub_id, huella_solicitud)
                VALUES
                    (:operacion, :clave, :usuario_id, :huella)
                ON CONFLICT (operacion, clave) DO NOTHING
                RETURNING id";
        $consulta = $this->pdo->prepare($sql);
        $consulta->execute([
            ':operacion' => $operacion,
            ':clave' => $clave,
            ':usuario_id' => $usuarioId,
            ':huella' => $huella,
        ]);
        $id = $consulta->fetchColumn();
        if ($id !== false) {
            return [
                'nueva' => true,
                'id' => (int) $id,
            ];
        }

        $consulta = $this->pdo->prepare(
            "SELECT id, usuario_hub_id, huella_solicitud, estado, codigo_http, respuesta_json
             FROM {$this->tabla}
             WHERE operacion = :operacion AND clave = :clave
             FOR UPDATE"
        );
        $consulta->execute([
            ':operacion' => $operacion,
            ':clave' => $clave,
        ]);
        $existente = $consulta->fetch(PDO::FETCH_ASSOC);
        if ($existente === false) {
            throw new RuntimeException('No se pudo recuperar la solicitud idempotente.');
        }
        return [
            'nueva' => false,
            'id' => (int) $existente['id'],
            'usuario_id' => (int) $existente['usuario_hub_id'],
            'huella' => (string) $existente['huella_solicitud'],
            'estado' => (string) $existente['estado'],
            'codigo_http' => $existente['codigo_http'] === null ? null : (int) $existente['codigo_http'],
            'respuesta' => $existente['respuesta_json'] === null
                ? null
                : $this->decodificar((string) $existente['respuesta_json']),
        ];
    }

    public function completar($id, $codigoHttp, array $respuesta)
    {
        $consulta = $this->pdo->prepare(
            "UPDATE {$this->tabla}
             SET estado = 'COMPLETADA',
                 codigo_http = :codigo_http,
                 respuesta_json = CAST(:respuesta AS jsonb),
                 completado_en = current_timestamp
             WHERE id = :id AND estado = 'EN_PROCESO'"
        );
        $consulta->execute([
            ':id' => (int) $id,
            ':codigo_http' => (int) $codigoHttp,
            ':respuesta' => $this->codificar($respuesta),
        ]);
        if ($consulta->rowCount() !== 1) {
            throw new RuntimeException('La solicitud idempotente no pudo completarse.');
        }
    }

    private function codificar(array $valor)
    {
        $json = json_encode($valor, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('No se pudo serializar la respuesta idempotente.');
        }
        return $json;
    }

    private function decodificar($json)
    {
        $valor = json_decode($json, true);
        if (!is_array($valor) || json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('La respuesta idempotente almacenada es invalida.');
        }
        return $valor;
    }
}
