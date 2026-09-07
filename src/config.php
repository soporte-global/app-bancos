<?php
define('RUTA', dirname(__DIR__));
//=============================================
// CONFIGURACIÓN DE ESTA APP ------------------
//=============================================
$app_config = require RUTA . '/app/config.php';
if (!is_array($app_config)) {
    throw new RuntimeException('app/config.php debe devolver un array.');
}
$local_config_path = RUTA . '/app/config.local.php';
if (is_file($local_config_path)) {
    $local_config = require $local_config_path;
    if (!is_array($local_config)) {
        throw new RuntimeException('app/config.local.php debe devolver un array.');
    }
    $app_config = array_replace($app_config, $local_config);
}

//=============================================
// CONFIGURACION DE PHP -----------------------
//=============================================
define('NOMBRE', $app_config['nombre']);
define('FolderName', $app_config['carpeta']);
define('CONEXION', $app_config['entorno']);
define('ID_APLICACION', (int) $app_config['id_aplicacion']);
define('PERMISOS_TODAS_LAS_APPS', (bool) ($app_config['permisos_todas_las_apps'] ?? false));
define('REQUIERE_EMPLEADO', (bool) ($app_config['requiere_empleado'] ?? false));
define('REQUIERE_ZWEB_USER', (bool) ($app_config['requiere_zweb_user'] ?? false));
define('REQUIERE_CLIENTE', (bool) ($app_config['requiere_cliente'] ?? false));
define('GRUPOS_CON_ACCESO', $app_config['grupos_con_acceso']);
define('FREE_FOR_ALL', (bool) $app_config['free_for_all']);
define('USA_COOKIES', (bool) $app_config['usa_cookies']);
define('MOSTRAR_HOME_AUTOMATICO', (bool) ($app_config['mostrar_home_automatico'] ?? true));
define('DIAGNOSTICO_HABILITADO', (bool) ($app_config['diagnostico_habilitado'] ?? false));
define('LOGIN_MAX_AGE', max(60, (int) ($app_config['login_max_age'] ?? 12 * 60 * 60)));
define('AUTH_CACHE_TTL', max(0, (int) ($app_config['auth_cache_ttl'] ?? 300)));
define('PROTOCOLO', $app_config['protocolo']);
define('SESSION_NAME', $app_config['session_name']);
define('AUTH_SESSION_KEY', 'GLOBAL_AUTH');
define('CHARSET', 'utf8');

$session_save_path = trim((string) ($app_config['session_save_path'] ?? ''));
if ($session_save_path === '') {
    $session_save_path = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
        . DIRECTORY_SEPARATOR . 'global_apps_sessions'
        . DIRECTORY_SEPARATOR . preg_replace('/[^A-Za-z0-9_.-]/', '_', SESSION_NAME);
}
if (!is_dir($session_save_path)
    && !mkdir($session_save_path, 0770, true)
    && !is_dir($session_save_path)
) {
    throw new RuntimeException('No se pudo crear el directorio de sesiones de la aplicacion.');
}
if (!is_writable($session_save_path)) {
    throw new RuntimeException('El directorio de sesiones de la aplicacion no es escribible.');
}
session_save_path($session_save_path);
session_name(SESSION_NAME);
ini_set('session.use_strict_mode', '1');
ini_set('session.gc_maxlifetime', (string) max(LOGIN_MAX_AGE + 3600, 3600));
$session_lifetime = USA_COOKIES ? LOGIN_MAX_AGE : 0;
session_set_cookie_params([
    'lifetime' => $session_lifetime,
    'path' => '/',
    'secure' => PROTOCOLO === 'https',
    'httponly' => true,
    'samesite' => 'Lax',
]);

set_time_limit(7200);
date_default_timezone_set('America/Argentina/Buenos_Aires');
ini_set('display_errors', CONEXION === 'prod' ? '0' : '1');
ini_set('display_startup_errors', CONEXION === 'prod' ? '0' : '1');
ini_set('xdebug.var_display_max_children', '-1');
ini_set('xdebug.var_display_max_data', '-1');
ini_set('xdebug.var_display_max_depth', '-1');
ini_set('memory_limit', '4G');
ini_set('post_max_size', '512M');
ini_set('upload_max_filesize', '512M');
ini_set('max_execution_time', '7200');
ini_set('max_file_uploads', '1200');
ini_set('default_charset', 'UTF-8');
error_reporting(E_ALL);
header('Content-Type: text/html; charset=utf-8');

//=============================================
// DEFAULTS -----------------------------------
//=============================================
$environment_defaults = [
    'prod' => [
        'so' => 'linux',
        'ftweb_host' => '192.168.1.253',
        'ftweb_port' => '5432',
        'rrhh_host' => '192.168.1.253',
        'rrhh_port' => '3306',
        'pdf_to_text' => null,
        'base_path' => '/' . FolderName,
    ],
    'tunel' => [
        'so' => 'windows',
        'ftweb_host' => 'localhost',
        'ftweb_port' => '5500',
        'rrhh_host' => 'localhost',
        'rrhh_port' => '5510',
        'pdf_to_text' => 'C:\\xpdfTools\\bin64\\pdftotext.exe',
        'base_path' => '/Desarrollos/' . FolderName,
    ],
    'testing' => [
        'so' => 'windows',
        'ftweb_host' => '200.59.239.233',
        'ftweb_port' => '50100',
        'rrhh_host' => 'localhost',
        'rrhh_port' => '5510',
        'pdf_to_text' => 'C:\\xpdfTools\\bin64\\pdftotext.exe',
        'base_path' => '/Desarrollos/' . FolderName,
    ],
];
if (!isset($environment_defaults[CONEXION])) {
    throw new InvalidArgumentException('Entorno de conexión inválido: ' . CONEXION);
}
$defaults = $environment_defaults[CONEXION];

//=============================================
// CONEXIONES / DETALLES ----------------------
//=============================================
define('SO', $defaults['so']);
define('RUTA_PDF_TO_TEXT', $app_config['pdf_to_text'] ?? $defaults['pdf_to_text']);
// --
define('HOST', $app_config['ftweb_host'] ?? $defaults['ftweb_host']);
define('PORT', $app_config['ftweb_port'] ?? $defaults['ftweb_port']);
define('DBASE', $app_config['ftweb_database']);
define('USER', $app_config['ftweb_user']);
define('PASS', $app_config['ftweb_password']);
define('FTWEB_PERSISTENT', (bool) ($app_config['ftweb_persistent'] ?? true));
define('BANCOS_DEBUG', (bool) ($app_config['bancos_debug'] ?? false));
define('BANCOS_ESQUEMA_OPERATIVO', BANCOS_DEBUG ? 'global_temp' : 'global_prod');
define('BANCOS_ESQUEMA_LECTURA_ERP', 'public');
define('BANCOS_ESQUEMA_ESCRITURA_ERP', BANCOS_DEBUG ? 'global_temp' : 'public');
if (BANCOS_DEBUG && CONEXION === 'prod') {
    throw new RuntimeException('bancos_debug no puede habilitarse en el entorno prod.');
}
// --
define('HOST2', $app_config['rrhh_host'] ?? $defaults['rrhh_host']);
define('PORT2', $app_config['rrhh_port'] ?? $defaults['rrhh_port']);
define('DBASE2', $app_config['rrhh_database']);
define('USER2', $app_config['rrhh_user']);
define('PASS2', $app_config['rrhh_password']);
// --
$http_host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$default_base_url = PROTOCOLO . '://' . $http_host . $defaults['base_path'];
$base_url = empty($app_config['base_url']) ? 
    $default_base_url :
    $app_config['base_url']
;
$default_hquery_path = rtrim(str_replace('\\', '/', dirname($defaults['base_path'])), '/') . '/hQuery';
$hquery_url = empty($app_config['hquery_url']) ? 
    PROTOCOLO . '://' . $http_host . $default_hquery_path :
    $app_config['hquery_url']
;
define('RUTA_WEB', rtrim($base_url, '/'));
define('HQUERY', rtrim($hquery_url, '/'));
define('PUERTO_ACCESO', PROTOCOLO === 'https' ? '5002' : '5001');
