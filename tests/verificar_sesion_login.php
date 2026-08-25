<?php

require_once dirname(__DIR__) . '/src/config.php';

function comprobar_sesion($condicion, $mensaje)
{
    if (!$condicion) {
        throw new RuntimeException($mensaje);
    }
}

$cookieParams = session_get_cookie_params();
$savePath = session_save_path();
$gcMaxLifetime = (int) ini_get('session.gc_maxlifetime');

comprobar_sesion(LOGIN_MAX_AGE === 12 * 60 * 60, 'LOGIN_MAX_AGE no equivale a doce horas.');
comprobar_sesion(AUTH_CACHE_TTL === 300, 'AUTH_CACHE_TTL no equivale a cinco minutos.');
comprobar_sesion(REQUIERE_ZWEB_USER === false, 'La plantilla exige un usuario Zweb por defecto.');
comprobar_sesion(REQUIERE_CLIENTE === false, 'La plantilla exige un cliente por defecto.');
comprobar_sesion(PERMISOS_TODAS_LAS_APPS === false, 'La plantilla publica permisos de otras aplicaciones por defecto.');
comprobar_sesion(ID_APLICACION === 0, 'La plantilla tiene una aplicacion asignada.');
comprobar_sesion(SESSION_NAME === 'GLOBAL_APPS_AUTH', 'La plantilla no usa la cookie de autenticacion compartida.');
comprobar_sesion(AUTH_SESSION_KEY === 'GLOBAL_AUTH', 'La sesion no usa el espacio de autenticacion comun.');
comprobar_sesion($cookieParams['lifetime'] === LOGIN_MAX_AGE, 'La cookie de sesion no dura lo mismo que el login.');
comprobar_sesion($cookieParams['path'] === '/', 'La cookie de sesion no esta disponible para nuevas pestanas de la app.');
comprobar_sesion($gcMaxLifetime > LOGIN_MAX_AGE, 'El recolector de sesiones puede borrar antes del vencimiento logico.');
comprobar_sesion($savePath !== '', 'La aplicacion no configuro un directorio de sesiones.');
comprobar_sesion(strpos($savePath, 'global_apps_sessions') !== false, 'La sesion no usa el almacenamiento del nuevo Core.');
comprobar_sesion(strpos($savePath, FolderName) === false, 'El almacenamiento de autenticacion quedo aislado por app.');
comprobar_sesion(is_dir($savePath) && is_writable($savePath), 'El directorio de sesiones no es escribible.');

$consultaEmpleadoActivo = file_get_contents(
    RUTA . '/src/Model/Core/Identidad/sql/empleado-activo-por-cuenta.sql'
);
comprobar_sesion(
    strpos($consultaEmpleadoActivo, 're.fecha_baja is null') !== false,
    'La restauracion de sesion no reconoce la fecha de baja del empleado.'
);
comprobar_sesion(
    strpos($consultaEmpleadoActivo, 're.fecha_eliminacion') === false,
    'La restauracion de sesion usa la columna eliminada de empleados.'
);

$dataEnviar = require RUTA . '/app/php/carga_inicial.php';
comprobar_sesion(is_object($dataEnviar), 'La carga inicial debe devolver un objeto.');

require_once RUTA . '/_shared/php/contexto_app.php';
$contextoApp = crear_contexto_app(null, 'login', $dataEnviar);
$contextoOriginal = $contextoApp;
require RUTA . '/app/compatibilidad/contexto.php';
validar_contexto_app($contextoApp);
comprobar_sesion($contextoApp === $contextoOriginal, 'La compatibilidad base debe conservar el contexto.');
comprobar_sesion($contextoApp['app']['pagina'] === 'login', 'El contexto no conserva la pagina resuelta.');

$contextoInvalido = $contextoApp;
$contextoInvalido['data'] = [];
$rechazoDataArray = false;
try {
    validar_contexto_app($contextoInvalido);
} catch (RuntimeException $error) {
    $rechazoDataArray = true;
}
comprobar_sesion($rechazoDataArray, 'El contexto acepta data como array raiz.');

echo "Sesion login OK\n";
