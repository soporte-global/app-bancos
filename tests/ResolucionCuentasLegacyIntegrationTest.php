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

$esperados = [
    '0191-168-011973/6' => ['CUENTA_UNICA', '103500000000524090'],
    '191168194623' => ['PERIODOS_OMITIDOS', null],
    'GENERAL' => ['ALCANCE_GLOBAL', null],
    'MERCADO PAGO' => ['MULTICUENTA', null],
];

$filas = $pdo->query(
    "SELECT cuenta_legacy, tratamiento, cuenta_bancaria_zetti_id::text AS cuenta_bancaria_zetti_id, metodo
       FROM global_prod.bancos_migracion_cuenta_legacy
      WHERE cuenta_legacy IN ('0191-168-011973/6', '191168194623', 'GENERAL', 'MERCADO PAGO')"
)->fetchAll(PDO::FETCH_ASSOC);

if (count($filas) !== count($esperados)) {
    throw new RuntimeException('No se encontraron las cuatro cuentas legacy especiales.');
}
foreach ($filas as $fila) {
    $esperado = $esperados[$fila['cuenta_legacy']];
    if ($fila['metodo'] !== 'MANUAL'
        || $fila['tratamiento'] !== $esperado[0]
        || $fila['cuenta_bancaria_zetti_id'] !== $esperado[1]
    ) {
        throw new RuntimeException('Tratamiento incorrecto para ' . $fila['cuenta_legacy'] . '.');
    }
}

$sinVinculo = (int) $pdo->query(
    "SELECT count(*)
       FROM global_prod.bancos_migracion_configuracion_legacy mc
      WHERE mc.cuenta_legacy = '0191-168-011973/6'
        AND NOT EXISTS (
            SELECT 1 FROM global_prod.bancos_configuracion_cuenta cc
             WHERE cc.configuracion_id = mc.configuracion_id
               AND cc.cuenta_bancaria_zetti_id = 103500000000524090
        )"
)->fetchColumn();
if ($sinVinculo !== 0) {
    throw new RuntimeException('Persisten configuraciones de Credicoop dólar sin vínculo.');
}
$vinculos = (int) $pdo->query(
    "SELECT count(*)
       FROM global_prod.bancos_migracion_configuracion_legacy mc
       JOIN global_prod.bancos_configuracion_cuenta cc ON cc.configuracion_id = mc.configuracion_id
      WHERE mc.cuenta_legacy = '0191-168-011973/6'
        AND cc.cuenta_bancaria_zetti_id = 103500000000524090"
)->fetchColumn();
if ($vinculos !== 20) {
    throw new RuntimeException('Se esperaban 20 vínculos de Credicoop dólar y se encontraron ' . $vinculos . '.');
}

$informe = (new ConciliacionPostMigracion($pdo))->generar();
$control = null;
foreach ($informe['controles'] as $candidato) {
    if ($candidato['id'] === 'cuentas-resueltas') {
        $control = $candidato;
        break;
    }
}
if ($control === null || $control['estado'] !== 'APROBADO') {
    throw new RuntimeException('El control cuentas-resueltas no quedó aprobado.');
}

echo "Tratamientos especiales y 20 vínculos de Credicoop dólar verificados; conciliación aprobada.\n";
