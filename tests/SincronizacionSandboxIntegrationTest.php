<?php

require_once dirname(__DIR__) . '/src/config.php';
require_once dirname(__DIR__) . '/src/autoload.php';

use AppBancos\Migration\SincronizacionSandbox;

$pdo = new PDO(
    'pgsql:host=' . HOST . ';port=' . PORT . ';dbname=' . DBASE,
    USER,
    PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$antes = (int) $pdo->query('SELECT count(*) FROM global_temp.bancos_sandbox_sincronizacion')->fetchColumn();
$vista = (new SincronizacionSandbox($pdo))->previsualizar();
$despues = (int) $pdo->query('SELECT count(*) FROM global_temp.bancos_sandbox_sincronizacion')->fetchColumn();

if ($antes !== $despues) {
    throw new RuntimeException('La previsualización registró DML.');
}
if (!in_array($vista['estado'], ['LISTO', 'REQUIERE_REINICIO_SANDBOX'], true)) {
    throw new RuntimeException('Estado de sincronización inválido.');
}
if (count($vista['tablas']) < 20 || $vista['faltantes'] < 0 || $vista['conflictos'] < 0) {
    throw new RuntimeException('La previsualización no cubre el conjunto operativo esperado.');
}
if (strpos(file_get_contents(__DIR__ . '/../app/cli/sincronizar_sandbox.php'), '--reiniciar') === false) {
    throw new RuntimeException('El CLI no expone el reinicio controlado del sandbox.');
}
if (strpos(file_get_contents(__DIR__ . '/../app/cli/sincronizar_sandbox.php'), '--aceptar-origen-conflictos') === false) {
    throw new RuntimeException('El CLI no expone la resolución explícita de conflictos.');
}
if (strpos(file_get_contents(__DIR__ . '/../app/cli/sincronizar_sandbox.php'), '--asegurar-rango-ids') === false) {
    throw new RuntimeException('El CLI no expone la reparación segura de secuencias.');
}

echo 'Previsualización de sincronización sandbox sin DML: '
    . $vista['faltantes'] . ' faltantes, ' . $vista['conflictos'] . " conflictos.\n";
