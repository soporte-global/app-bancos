<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__, 2) . '/src/config.php';
require_once RUTA . '/src/autoload.php';

use AppBancos\Reporting\ConciliacionPostMigracion;

$pdo = new PDO(
    'pgsql:host=' . HOST . ';port=' . PORT . ';dbname=' . DBASE,
    USER,
    PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$informe = (new ConciliacionPostMigracion($pdo))->generar();

if (in_array('--json', $argv, true)) {
    echo json_encode($informe, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    exit($informe['estado'] === 'CON_DIFERENCIAS' ? 1 : 0);
}

echo '# Conciliación post-migración APP BANCOS' . PHP_EOL . PHP_EOL;
echo '- Generado: ' . $informe['generado_en'] . PHP_EOL;
echo '- Estado: **' . $informe['estado'] . '**' . PHP_EOL . PHP_EOL;
echo '| Tipo | Dimensión | Control | Esperado | Actual | Diferencia | Estado |' . PHP_EOL;
echo '|---|---|---|---:|---:|---:|---|' . PHP_EOL;
foreach ($informe['controles'] as $control) {
    echo '| ' . $control['tipo']
        . ' | ' . $control['dimension']
        . ' | ' . $control['id']
        . ' | ' . $control['esperado']
        . ' | ' . $control['actual']
        . ' | ' . $control['diferencia']
        . ' | ' . $control['estado'] . ' |' . PHP_EOL;
}
echo PHP_EOL . '## Excepciones documentadas' . PHP_EOL . PHP_EOL;
echo '| Tipo | Cantidad | Tratamiento |' . PHP_EOL;
echo '|---|---:|---|' . PHP_EOL;
foreach ($informe['excepciones'] as $excepcion) {
    echo '| ' . $excepcion['tipo'] . ' | ' . $excepcion['cantidad'] . ' | '
        . $excepcion['detalle'] . ' |' . PHP_EOL;
}

exit($informe['estado'] === 'CON_DIFERENCIAS' ? 1 : 0);
