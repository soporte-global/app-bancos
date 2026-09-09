<?php

if (!defined('RUTA')) {
    define('RUTA', dirname(__DIR__));
}
require_once RUTA . '/src/autoload.php';

use AppBancos\Http\ApiException;
use AppBancos\Security\AutorizadorAccion;
use AppBancos\Security\ProteccionCsrf;
use GlobalApps\Core\Acceso\PermisoAcceso;
use GlobalApps\Core\Acceso\PermisoApp;
use GlobalApps\Core\Identidad\Cuenta;
use GlobalApps\Core\Identidad\Sesion;

function comprobarProteccion($condicion, $mensaje)
{
    if (!$condicion) {
        throw new RuntimeException($mensaje);
    }
}

$csrf = new ProteccionCsrf();
$estadoSesion = [];
$token = $csrf->obtenerToken($estadoSesion);
comprobarProteccion(strlen($token) === 64, 'El token CSRF no tiene la entropia esperada.');
comprobarProteccion($csrf->obtenerToken($estadoSesion) === $token, 'El token CSRF cambio dentro de la sesion.');
$csrf->validar($estadoSesion, $token);
try {
    $csrf->validar($estadoSesion, str_repeat('0', 64));
    throw new RuntimeException('Se acepto un token CSRF incorrecto.');
} catch (ApiException $error) {
    comprobarProteccion($error->codigoApi() === 'CSRF_INVALIDO', 'El error CSRF no expuso su codigo estable.');
}

$permisoPreparar = new PermisoApp(80, 12, 2, 1, 'Preparar', 'movimiento-preparar', null);
$admin = new Sesion(
    new Cuenta(10, 'hvega', 'Hernan'),
    null,
    null,
    null,
    [new PermisoAcceso(12, 'APP BANCOS', '/app-bancos', null, 1, 'Administrador', [])]
);
$general = new Sesion(
    new Cuenta(11, 'usuario', 'Usuario'),
    null,
    null,
    null,
    [new PermisoAcceso(12, 'APP BANCOS', '/app-bancos', null, 2, 'General', [$permisoPreparar])]
);
$sinAcceso = new Sesion(new Cuenta(12, 'externo', 'Externo'));
$autorizador = new AutorizadorAccion(12);
$autorizador->exigir($admin, 'cualquier-accion-futura');
$autorizador->exigir($general, 'movimiento-preparar');

foreach ([
    [$general, 'movimiento-cerrar'],
    [$sinAcceso, 'movimiento-preparar'],
] as $caso) {
    try {
        $autorizador->exigir($caso[0], $caso[1]);
        throw new RuntimeException('Se autorizo una accion sin permiso.');
    } catch (ApiException $error) {
        comprobarProteccion($error->estadoHttp() === 403, 'La denegacion no usa HTTP 403.');
    }
}

echo "Proteccion de escritura OK\n";
