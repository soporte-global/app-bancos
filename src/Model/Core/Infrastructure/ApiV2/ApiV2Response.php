<?php
namespace GlobalApps\Core\Infrastructure\ApiV2;

use GlobalApps\Core\Infrastructure\Http\HttpResponse;
use UnexpectedValueException;

final class ApiV2Response
{
    private $ok;
    private $status;
    private $codigo;
    private $mensaje;
    private $data;
    private $errores;

    private function __construct(array $respuesta)
    {
        $this->ok = (bool) $respuesta['ok'];
        $this->status = (int) $respuesta['status'];
        $this->codigo = (string) $respuesta['codigo'];
        $this->mensaje = (string) $respuesta['mensaje'];
        $this->data = $respuesta['data'];
        $this->errores = $respuesta['errores'];
    }

    public static function fromHttpResponse(HttpResponse $response)
    {
        $respuesta = $response->json();
        foreach (['ok', 'status', 'codigo', 'mensaje', 'data', 'errores'] as $campo) {
            if (!is_array($respuesta) || !array_key_exists($campo, $respuesta)) {
                throw new UnexpectedValueException(
                    'La respuesta no respeta el contrato estándar de APIS-global v2.'
                );
            }
        }
        if ((int) $respuesta['status'] !== $response->statusCode()) {
            throw new UnexpectedValueException('El estado HTTP no coincide con el cuerpo de la API.');
        }
        return new self($respuesta);
    }

    public function ok()
    {
        return $this->ok;
    }

    public function status()
    {
        return $this->status;
    }

    public function codigo()
    {
        return $this->codigo;
    }

    public function mensaje()
    {
        return $this->mensaje;
    }

    public function data()
    {
        return $this->data;
    }

    public function errores()
    {
        return $this->errores;
    }
}
