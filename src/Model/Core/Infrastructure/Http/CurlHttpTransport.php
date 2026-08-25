<?php
namespace GlobalApps\Core\Infrastructure\Http;

use InvalidArgumentException;
use RuntimeException;

final class CurlHttpTransport implements HttpTransport
{
    private $connectTimeout;
    private $timeout;
    private $caBundle;

    public function __construct($connectTimeout = 10, $timeout = 120, $caBundle = null)
    {
        $this->connectTimeout = (int) $connectTimeout;
        $this->timeout = (int) $timeout;
        $this->caBundle = $caBundle === null ? null : trim((string) $caBundle);
    }

    public function request($method, $url, array $headers = [], $body = null)
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('La extensión cURL no está disponible.');
        }

        $method = strtoupper(trim((string) $method));
        if (!in_array($method, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'], true)) {
            throw new InvalidArgumentException('Método HTTP no soportado: ' . $method);
        }
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException('La URL solicitada no es válida.');
        }

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_ENCODING => '',
            CURLOPT_USERAGENT => 'global-apps-core/1.0',
        ]);
        if ($this->caBundle !== null && $this->caBundle !== '') {
            if (!is_file($this->caBundle) || !is_readable($this->caBundle)) {
                curl_close($curl);
                throw new RuntimeException('El bundle de autoridades certificantes no esta disponible.');
            }
            curl_setopt($curl, CURLOPT_CAINFO, $this->caBundle);
        }
        if ($body !== null) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
        }

        $responseBody = curl_exec($curl);
        if ($responseBody === false) {
            $mensaje = curl_error($curl);
            curl_close($curl);
            throw new RuntimeException('No se pudo completar la llamada HTTP: ' . $mensaje);
        }

        $statusCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        return new HttpResponse($statusCode, $responseBody);
    }
}
