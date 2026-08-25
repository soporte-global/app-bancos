<?php
namespace GlobalApps\Core\Infrastructure\ApiV2;

use GlobalApps\Core\Infrastructure\Http\HttpTransport;
use RuntimeException;

final class ApiV2TokenProvider
{
    private $transport;
    private $baseUrl;
    private $usuario;
    private $password;
    private $accessToken;
    private $expiresAt = 0;

    public function __construct(HttpTransport $transport, $baseUrl, $usuario, $password)
    {
        $this->transport = $transport;
        $this->baseUrl = rtrim((string) $baseUrl, '/') . '/';
        $this->usuario = (string) $usuario;
        $this->password = (string) $password;
    }

    public function accessToken()
    {
        if ($this->accessToken !== null && $this->expiresAt > time() + 30) {
            return $this->accessToken;
        }
        if ($this->usuario === '' || $this->password === '') {
            throw new RuntimeException('Faltan las credenciales para APIS-global v2.');
        }

        $body = json_encode([
            'usuario' => $this->usuario,
            'password' => $this->password,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $httpResponse = $this->transport->request(
            'POST',
            $this->baseUrl . 'token/',
            ['Accept: application/json', 'Content-Type: application/json'],
            $body
        );
        $response = ApiV2Response::fromHttpResponse($httpResponse);
        if (!$response->ok()) {
            throw new ApiV2Exception($response->mensaje(), $response);
        }

        $data = $response->data();
        if (!is_array($data) || empty($data['access_token'])) {
            throw new RuntimeException('La API v2 no devolvió un access token válido.');
        }

        $this->accessToken = (string) $data['access_token'];
        $this->expiresAt = $this->resolverVencimiento($data);
        return $this->accessToken;
    }

    public function invalidate()
    {
        $this->accessToken = null;
        $this->expiresAt = 0;
    }

    private function resolverVencimiento(array $data)
    {
        if (!empty($data['expires_at'])) {
            $expiresAt = strtotime($data['expires_at']);
            if ($expiresAt !== false && $expiresAt > time()) {
                return $expiresAt;
            }
        }
        if (isset($data['expires_in']) && is_numeric($data['expires_in'])) {
            return time() + max(1, (int) $data['expires_in']);
        }
        throw new RuntimeException('El token v2 no informa un vencimiento válido.');
    }
}
