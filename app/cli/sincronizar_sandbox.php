<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__, 2) . '/src/config.php';
require_once RUTA . '/src/autoload.php';

use AppBancos\Migration\SincronizacionSandbox;

$confirmar = in_array('--confirmar', $argv, true);
$reiniciar = in_array('--reiniciar', $argv, true);
$aceptarOrigen = in_array('--aceptar-origen-conflictos', $argv, true);
$asegurarRango = in_array('--asegurar-rango-ids', $argv, true);
$clave = null;
foreach ($argv as $argumento) {
    if (strpos($argumento, '--clave=') === 0) {
        $clave = substr($argumento, strlen('--clave='));
    }
}
if ($confirmar && !$asegurarRango && ($clave === null || $clave === '')) {
    fwrite(STDERR, "--confirmar exige --clave=<clave-idempotente>.\n");
    exit(2);
}
if ($reiniciar && !$confirmar) {
    fwrite(STDERR, "--reiniciar exige --confirmar y una clave idempotente.\n");
    exit(2);
}
if ($aceptarOrigen && !$confirmar) {
    fwrite(STDERR, "--aceptar-origen-conflictos exige --confirmar y una clave idempotente.\n");
    exit(2);
}
if ($asegurarRango && !$confirmar) {
    fwrite(STDERR, "--asegurar-rango-ids exige --confirmar.\n");
    exit(2);
}

$pdo = new PDO(
    'pgsql:host=' . HOST . ';port=' . PORT . ';dbname=' . DBASE,
    USER,
    PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$servicio = new SincronizacionSandbox($pdo);

try {
    $resultado = $asegurarRango
        ? $servicio->asegurarRangoIds()
        : ($confirmar
        ? $servicio->ejecutar($clave, $reiniciar, $aceptarOrigen)
        : $servicio->previsualizar());
    echo json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    exit(in_array($resultado['estado'], ['LISTO', 'OK'], true) ? 0 : 1);
} catch (Throwable $error) {
    fwrite(STDERR, json_encode([
        'estado' => 'ERROR',
        'mensaje' => $error->getMessage(),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL);
    exit(1);
}
