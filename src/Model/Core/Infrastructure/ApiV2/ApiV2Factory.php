<?php
namespace GlobalApps\Core\Infrastructure\ApiV2;

use GlobalApps\Core\Infrastructure\Http\CurlHttpTransport;
use GlobalApps\Core\Infrastructure\Http\HttpTransport;
use InvalidArgumentException;

final class ApiV2Factory
{
    public static function crearCliente(array $configuracion, HttpTransport $transport = null)
    {
        $baseUrl = trim((string) ($configuracion['apis_v2_base_url'] ?? ''));
        if ($baseUrl === '') {
            throw new InvalidArgumentException('Falta configurar apis_v2_base_url.');
        }
        $transport = $transport ?: self::crearTransport($configuracion);
        $tokens = self::crearTokenProvider($configuracion, $transport);
        return new ApiV2Client($transport, $baseUrl, $tokens);
    }

    public static function crearTokenProvider(
        array $configuracion,
        HttpTransport $transport = null
    ) {
        $baseUrl = trim((string) ($configuracion['apis_v2_base_url'] ?? ''));
        if ($baseUrl === '') {
            throw new InvalidArgumentException('Falta configurar apis_v2_base_url.');
        }
        $transport = $transport ?: self::crearTransport($configuracion);
        return new ApiV2TokenProvider(
            $transport,
            $baseUrl,
            $configuracion['apis_v2_user'] ?? '',
            $configuracion['apis_v2_password'] ?? ''
        );
    }

    private static function crearTransport(array $configuracion)
    {
        return new CurlHttpTransport(
            10,
            120,
            $configuracion['apis_v2_ca_bundle'] ?? null
        );
    }
}
