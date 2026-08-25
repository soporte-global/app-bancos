<?php
namespace GlobalApps\Core\Infrastructure\ApiV2;

use GlobalApps\Core\Infrastructure\Http\HttpTransport;
use InvalidArgumentException;
use RuntimeException;

final class ApiV2Client
{
    private $transport;
    private $tokenProvider;
    private $baseUrl;
    private $metadata = [];

    public function __construct(
        HttpTransport $transport,
        $baseUrl,
        ApiV2TokenProvider $tokenProvider = null
    ) {
        $this->transport = $transport;
        $this->baseUrl = rtrim((string) $baseUrl, '/') . '/';
        $this->tokenProvider = $tokenProvider;
    }

    public function metadata($api)
    {
        $api = $this->normalizarApi($api);
        if (isset($this->metadata[$api])) {
            return $this->metadata[$api];
        }

        $response = $this->transport->request(
            'GET',
            $this->baseUrl . $api . '/?metadata=autenticacion',
            ['Accept: application/json']
        );
        $data = $response->json();
        if (!$response->successful()
            || !is_array($data)
            || !array_key_exists('requiere_token', $data)
            || !is_bool($data['requiere_token'])
        ) {
            throw new RuntimeException('No se pudo determinar la autenticación de la API ' . $api . '.');
        }

        $this->metadata[$api] = $data;
        return $this->metadata[$api];
    }

    public function post($api, $payload)
    {
        $api = $this->normalizarApi($api);
        $requiereToken = $this->metadata($api)['requiere_token'];

        $response = $this->enviarPost($api, $payload, $requiereToken);
        if ($requiereToken
            && $response->status() === 401
            && in_array($response->codigo(), ['token_expired', 'invalid_token'], true)
        ) {
            // el primer intento fue rechazado antes de ejecutar la lógica de negocio
            $this->tokenProvider->invalidate();
            $response = $this->enviarPost($api, $payload, true);
        }
        return $response;
    }

    public function postOrFail($api, $payload)
    {
        $response = $this->post($api, $payload);
        if (!$response->ok()) {
            throw new ApiV2Exception($response->mensaje(), $response);
        }
        return $response->data();
    }

    private function enviarPost($api, $payload, $requiereToken)
    {
        $headers = ['Accept: application/json', 'Content-Type: application/json'];
        if ($requiereToken) {
            if ($this->tokenProvider === null) {
                throw new RuntimeException('La API requiere token y no hay un proveedor configurado.');
            }
            $headers[] = 'Authorization: Bearer ' . $this->tokenProvider->accessToken();
        }

        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($body === false) {
            throw new InvalidArgumentException('El payload no puede convertirse a JSON.');
        }
        $httpResponse = $this->transport->request(
            'POST',
            $this->baseUrl . $api . '/',
            $headers,
            $body
        );
        return ApiV2Response::fromHttpResponse($httpResponse);
    }

    private function normalizarApi($api)
    {
        $api = trim((string) $api);
        if (!preg_match('/^[a-z0-9][a-z0-9_-]*$/', $api)) {
            throw new InvalidArgumentException('Nombre de API inválido: ' . $api);
        }
        return $api;
    }
}
