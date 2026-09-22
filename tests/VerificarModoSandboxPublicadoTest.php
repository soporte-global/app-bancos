<?php

require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/autoload.php';

use AppBancos\Security\ProteccionDespliegueSandbox;

$index = file_get_contents(__DIR__ . '/../index.php');
$configuracion = file_get_contents(__DIR__ . '/../src/config.php');
$css = file_get_contents(__DIR__ . '/../app/css/bancos.css');
$api = file_get_contents(__DIR__ . '/../api.php');

$comprobar = static function ($condicion, $mensaje) {
    if (!$condicion) {
        throw new RuntimeException($mensaje);
    }
};

$comprobar(strpos($configuracion, "define('BANCOS_MODO_OPERATIVO'") !== false, 'Falta el modo operativo explícito.');
$comprobar(strpos($configuracion, 'bancos_sandbox_permitir_en_prod') !== false, 'Falta la confirmación adicional para producción.');
$comprobar(strpos($configuracion, "['postgres', 'root']") !== false, 'No se rechazan usuarios administradores.');
$comprobar(strpos($index, 'MODO DE PRUEBA') !== false, 'Falta el aviso visible del sandbox.');
$comprobar(strpos($index, 'global_temp') !== false, 'El aviso no identifica el destino de prueba.');
$comprobar(strpos($css, '.bancos-modo-sandbox') !== false, 'Falta el estilo del aviso de sandbox.');
$comprobar(strpos($api, 'ProteccionDespliegueSandbox::verificar') !== false, 'La API no verifica el rol restringido.');

$pdo = new PDO(
    'pgsql:host=' . HOST . ';port=' . PORT . ';dbname=' . DBASE,
    USER,
    PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
ProteccionDespliegueSandbox::verificar($pdo, 'sandbox', 'tunel');
$rechazado = false;
try {
    ProteccionDespliegueSandbox::verificar($pdo, 'sandbox', 'prod');
} catch (RuntimeException $esperada) {
    $rechazado = true;
}
$comprobar($rechazado, 'La conexión administrativa actual sería aceptada en un sandbox publicado.');

echo "Modo sandbox publicado y aviso permanente OK\n";
