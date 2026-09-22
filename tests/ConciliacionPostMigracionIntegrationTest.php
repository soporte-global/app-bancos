<?php

require_once dirname(__DIR__) . '/src/config.php';
require_once dirname(__DIR__) . '/src/autoload.php';

use AppBancos\Reporting\ConciliacionPostMigracion;

$pdo = new PDO(
    'pgsql:host=' . HOST . ';port=' . PORT . ';dbname=' . DBASE,
    USER,
    PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$informe = (new ConciliacionPostMigracion($pdo))->generar();

if ($informe['estado'] === 'CON_DIFERENCIAS') {
    $diferencias = array_values(array_filter($informe['controles'], static function ($control) {
        return $control['tipo'] === 'PARIDAD_SNAPSHOT' && $control['estado'] !== 'APROBADO';
    }));
    throw new RuntimeException(
        'La paridad del snapshot post-migración encontró diferencias: '
        . json_encode($diferencias, JSON_UNESCAPED_UNICODE)
    );
}

if (count($informe['controles']) < 20) {
    throw new RuntimeException('El informe no cubre todas las dimensiones requeridas.');
}

$tiposExcepcion = array_column($informe['excepciones'], 'tipo');
foreach (['FILAS_DUPLICADAS_NO_CANONICAS', 'PERIODOS_OMITIDOS', 'RECURSO_VALOR', 'RECURSO_ASIENTO'] as $tipo) {
    if (!in_array($tipo, $tiposExcepcion, true)) {
        throw new RuntimeException('Falta la excepción documentada ' . $tipo . '.');
    }
}

$snapshot = array_values(array_filter($informe['controles'], static function ($control) {
    return $control['tipo'] === 'PARIDAD_SNAPSHOT';
}));
foreach ($snapshot as $control) {
    if ($control['estado'] !== 'APROBADO') {
        throw new RuntimeException('Falló el control de snapshot ' . $control['id'] . '.');
    }
}

echo 'Conciliación del snapshot OK: ' . count($snapshot)
    . ' controles aprobados; estado de corte ' . $informe['estado'] . ".\n";
