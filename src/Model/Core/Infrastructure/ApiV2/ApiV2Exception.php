<?php
namespace GlobalApps\Core\Infrastructure\ApiV2;

use RuntimeException;

final class ApiV2Exception extends RuntimeException
{
    private $response;

    public function __construct($message, ApiV2Response $response = null)
    {
        parent::__construct((string) $message, $response ? $response->status() : 0);
        $this->response = $response;
    }

    public function response()
    {
        return $this->response;
    }
}
