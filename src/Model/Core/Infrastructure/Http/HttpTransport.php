<?php
namespace GlobalApps\Core\Infrastructure\Http;

interface HttpTransport
{
    public function request($method, $url, array $headers = [], $body = null);
}
