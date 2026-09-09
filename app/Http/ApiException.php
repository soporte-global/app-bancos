<?php
namespace AppBancos\Http;

use RuntimeException;

final class ApiException extends RuntimeException
{
    private $estadoHttp;
    private $codigoApi;

    public function __construct($estadoHttp, $codigoApi, $mensaje)
    {
        parent::__construct((string) $mensaje);
        $this->estadoHttp = (int) $estadoHttp;
        $this->codigoApi = (string) $codigoApi;
    }

    public function estadoHttp()
    {
        return $this->estadoHttp;
    }

    public function codigoApi()
    {
        return $this->codigoApi;
    }
}
