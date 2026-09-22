<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__, 2) . '/src/config.php';
require_once RUTA . '/src/autoload.php';

use AppBancos\Migration\MigracionLegacyIncremental;

$confirmar = in_array('--confirmar', $argv, true);
$clave = null;
foreach ($argv as $argumento) {
    if (strpos($argumento, '--clave=') === 0) {
        $clave = substr($argumento, strlen('--clave='));
    }
}

if ($confirmar && ($clave === null || $clave === '')) {
    fwrite(STDERR, "--confirmar exige --clave=<clave-idempotente>.\n");
    exit(2);
}

$pdo = new PDO(
    'pgsql:host=' . HOST . ';port=' . PORT . ';dbname=' . DBASE,
    USER,
    PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$migracion = new MigracionLegacyIncremental($pdo);

try {
    $resultado = $confirmar
        ? $migracion->ejecutar($clave)
        : $migracion->previsualizar();
    echo json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    $estado = $resultado['estado'] ?? 'ERROR';
    exit(in_array($estado, ['LISTO', 'OK'], true) ? 0 : 1);
} catch (Throwable $e) {
    fwrite(STDERR, json_encode([
        'estado' => 'ERROR',
        'mensaje' => $e->getMessage(),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL);
    exit(1);
}
