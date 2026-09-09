<?php
namespace AppBancos\Security;

use AppBancos\Http\ApiException;

final class ProteccionCsrf
{
    const SESSION_KEY = 'bancos_csrf_token';

    public function obtenerToken(array &$sesion)
    {
        $token = isset($sesion[self::SESSION_KEY])
            ? (string) $sesion[self::SESSION_KEY]
            : '';
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            $token = bin2hex(random_bytes(32));
            $sesion[self::SESSION_KEY] = $token;
        }
        return $token;
    }

    public function validar(array $sesion, $tokenRecibido)
    {
        $esperado = isset($sesion[self::SESSION_KEY])
            ? (string) $sesion[self::SESSION_KEY]
            : '';
        $recibido = trim((string) $tokenRecibido);
        if ($esperado === '' || $recibido === '' || !hash_equals($esperado, $recibido)) {
            throw new ApiException(403, 'CSRF_INVALIDO', 'El token CSRF no es valido.');
        }
    }
}
