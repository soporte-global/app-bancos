<?php
namespace AppBancos\Application;

use AppBancos\Repository\AuditoriaRepository;
use AppBancos\Repository\IdempotenciaRepository;
use InvalidArgumentException;
use PDO;
use RuntimeException;
use Throwable;

final class EjecutorComandoIdempotente
{
    private $pdo;
    private $idempotencia;
    private $auditoria;

    public function __construct(
        PDO $pdo,
        IdempotenciaRepository $idempotencia,
        AuditoriaRepository $auditoria
    ) {
        $this->pdo = $pdo;
        $this->idempotencia = $idempotencia;
        $this->auditoria = $auditoria;
    }

    public function ejecutar(
        $operacion,
        $clave,
        $usuarioId,
        array $entrada,
        array $eventoAuditoria,
        callable $comando
    ) {
        $operacion = trim((string) $operacion);
        $clave = trim((string) $clave);
        $usuarioId = (int) $usuarioId;
        if (!preg_match('/^[a-z][a-z0-9._-]{2,79}$/', $operacion)) {
            throw new InvalidArgumentException('La operacion idempotente es invalida.');
        }
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{7,119}$/', $clave)) {
            throw new InvalidArgumentException('La clave de idempotencia es invalida.');
        }
        if ($usuarioId <= 0) {
            throw new InvalidArgumentException('El usuario de la operacion es invalido.');
        }
        $this->validarEvento($eventoAuditoria);
        if ($this->pdo->inTransaction()) {
            throw new RuntimeException('El ejecutor idempotente debe controlar su propia transaccion.');
        }

        $huella = hash('sha256', $this->jsonCanonico($entrada));
        $this->pdo->beginTransaction();
        try {
            $solicitud = $this->idempotencia->iniciar($operacion, $clave, $usuarioId, $huella);
            if (!$solicitud['nueva']) {
                if ($solicitud['usuario_id'] !== $usuarioId || !hash_equals($solicitud['huella'], $huella)) {
                    throw new ConflictoIdempotenciaException(
                        'La clave de idempotencia ya fue usada con otra solicitud.'
                    );
                }
                if ($solicitud['estado'] !== 'COMPLETADA' || !is_array($solicitud['respuesta'])) {
                    throw new ConflictoIdempotenciaException('La solicitud con esta clave sigue en proceso.');
                }
                $this->pdo->commit();
                return [
                    'codigo_http' => $solicitud['codigo_http'],
                    'respuesta' => $solicitud['respuesta'],
                    'repetida' => true,
                ];
            }

            $resultado = $comando();
            if (!is_array($resultado)
                || !isset($resultado['codigo_http'])
                || !isset($resultado['respuesta'])
                || !is_array($resultado['respuesta'])
            ) {
                throw new RuntimeException('El comando no respeto el contrato de respuesta.');
            }
            $codigoHttp = (int) $resultado['codigo_http'];
            if ($codigoHttp < 200 || $codigoHttp > 299) {
                throw new RuntimeException('El comando devolvio un estado HTTP no exitoso.');
            }

            $this->auditoria->registrar($solicitud['id'], $usuarioId, $eventoAuditoria);
            $this->idempotencia->completar($solicitud['id'], $codigoHttp, $resultado['respuesta']);
            $this->pdo->commit();
            return [
                'codigo_http' => $codigoHttp,
                'respuesta' => $resultado['respuesta'],
                'repetida' => false,
            ];
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $error;
        }
    }

    private function validarEvento(array $evento)
    {
        foreach (['accion', 'recurso_tipo'] as $campo) {
            if (!isset($evento[$campo]) || trim((string) $evento[$campo]) === '') {
                throw new InvalidArgumentException('Falta el campo de auditoria ' . $campo . '.');
            }
        }
    }

    private function jsonCanonico(array $valor)
    {
        $normalizado = $this->normalizar($valor);
        $json = json_encode($normalizado, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new InvalidArgumentException('La solicitud no puede serializarse.');
        }
        return $json;
    }

    private function normalizar($valor)
    {
        if (!is_array($valor)) {
            return $valor;
        }
        if ($this->esLista($valor)) {
            return array_map([$this, 'normalizar'], $valor);
        }
        ksort($valor, SORT_STRING);
        foreach ($valor as $clave => $elemento) {
            $valor[$clave] = $this->normalizar($elemento);
        }
        return $valor;
    }

    private function esLista(array $valor)
    {
        return array_keys($valor) === range(0, count($valor) - 1);
    }
}
