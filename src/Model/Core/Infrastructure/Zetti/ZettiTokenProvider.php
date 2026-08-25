<?php
namespace GlobalApps\Core\Infrastructure\Zetti;

use GlobalApps\Core\Infrastructure\Http\HttpTransport;
use RuntimeException;

final class ZettiTokenProvider
{
    private $transport;
    private $url;
    private $user;
    private $password;
    private $clientCredentials;
    private $tokenData;
    private $expiresAt = 0;

    public function __construct(
        HttpTransport $transport,
        $url,
        $user,
        $password,
        $clientCredentials
    ) {
        $this->transport = $transport;
        $this->url = (string) $url;
        $this->user = (string) $user;
        $this->password = (string) $password;
        $this->clientCredentials = (string) $clientCredentials;
    }

    public function accessToken()
    {
        if ($this->tokenData !== null && $this->expiresAt > time() + 30) {
            return $this->tokenData['access_token'];
        }

        $response = $this->transport->request(
            'POST',
            $this->url,
            [
                'Accept: application/json',
                'Content-Type: application/x-www-form-urlencoded',
                'Authorization: Basic ' . base64_encode($this->clientCredentials),
            ],
            http_build_query([
                'username' => $this->user,
                'password' => $this->password,
                'grant_type' => 'password',
            ])
        );
        $data = $response->json();
        if (!$response->successful() || !is_array($data) || empty($data['access_token'])) {
            throw new RuntimeException('Zetti no pudo emitir un access token válido.');
        }

        $this->tokenData = $data;
        $ttl = isset($data['expires_in']) && is_numeric($data['expires_in'])
            ? (int) $data['expires_in']
            : 300;
        $this->expiresAt = time() + max(60, $ttl);
        return $this->tokenData['access_token'];
    }

    public function tokenData()
    {
        if ($this->tokenData === null) {
            $this->accessToken();
        }
        return $this->tokenData;
    }

    public function invalidate()
    {
        $this->tokenData = null;
        $this->expiresAt = 0;
    }
}
