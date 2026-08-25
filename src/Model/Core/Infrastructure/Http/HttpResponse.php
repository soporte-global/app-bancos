<?php
namespace GlobalApps\Core\Infrastructure\Http;

use UnexpectedValueException;

final class HttpResponse
{
    private $statusCode;
    private $body;

    public function __construct($statusCode, $body)
    {
        $this->statusCode = (int) $statusCode;
        $this->body = (string) $body;
    }

    public function statusCode()
    {
        return $this->statusCode;
    }

    public function body()
    {
        return $this->body;
    }

    public function successful()
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }

    public function json()
    {
        if ($this->body === '') {
            return null;
        }
        $data = json_decode($this->body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new UnexpectedValueException('La respuesta HTTP no contiene JSON válido.');
        }
        return $data;
    }
}
