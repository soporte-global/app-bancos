<?php
$vistaSolicitada = (string) ($_GET['vista'] ?? 'importaciones');
$vista = in_array($vistaSolicitada, ['importaciones', 'configuraciones', 'about'], true)
    ? $vistaSolicitada
    : 'importaciones';
$rutaFixture = dirname(__DIR__, 2);
if (!defined('RUTA')) {
    define('RUTA', $rutaFixture);
}
require_once RUTA . '/src/autoload.php';
$tema = ($_GET['tema'] ?? 'claro') === 'oscuro' ? 'oscuro' : 'claro';

$contextoApp = [
    'data' => (object) [
        'importaciones' => (object) [
            'habilitada' => true,
            'cuentas' => [[
                'id' => 1042,
                'etiqueta' => 'Casa Central · Banco Demo · Cuenta corriente · Operativa · 001-42',
                'configuraciones' => 1,
            ]],
            'configuraciones' => [[
                'id' => 4,
                'cuenta_bancaria_id' => 1042,
                'etiqueta' => 'Banco Demo · formato mensual',
            ]],
        ],
        'configuraciones' => (object) [
            'habilitada' => true,
            'configuraciones' => [[
                'id' => 4,
                'etiqueta' => 'Banco Demo · formato mensual',
                'reglas' => 1,
            ]],
            'subtipos' => [['id' => 17, 'nombre' => 'Transferencia bancaria']],
            'cuentas_bancarias' => [[
                'id' => 1042,
                'etiqueta' => 'Casa Central · Banco Demo · Cuenta corriente · Operativa · 001-42',
            ]],
            'cuentas_contables' => [['id' => 1101, 'etiqueta' => '1.1.01 · Banco Demo']],
            'responsables' => [['id' => 159, 'usuario' => 'hvega']],
            'seleccionada' => [
                'id' => 4,
                'automaticas' => 1,
                'total_reglas' => 1,
                'cuentas' => [[
                    'id' => 1042,
                    'etiqueta' => 'Casa Central · Banco Demo · Cuenta corriente · Operativa · 001-42',
                ]],
                'mapeos' => [],
                'asignaciones' => [],
                'reglas' => [[
                    'id' => 31,
                    'subtipo_id' => 17,
                    'subtipo' => 'Transferencia bancaria',
                    'sentido' => 'A',
                    'codigo_extracto' => 'TRANSF',
                    'version' => 1,
                    'validar_automaticamente' => true,
                ]],
            ],
        ],
    ],
];

if (!defined('RUTA_WEB')) {
    define('RUTA_WEB', '/tests/fixtures/operacion-responsive.php');
}
?><!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Prueba visual de <?php echo $vista; ?></title>
    <link rel="stylesheet" href="../../_shared/css/paleta_colores.css">
    <link rel="stylesheet" href="../../_shared/css/general.css">
    <link rel="stylesheet" href="../../_shared/css/header.css">
    <link rel="stylesheet" href="../../app/css/bancos.css">
    <link rel="stylesheet" href="../../app/css/tema_componentes.css">
    <link rel="stylesheet" href="../../app/css/tema_<?php echo $tema; ?>.css">
    <style>
        body { margin: 0; font-family: 'Roboto Mono', monospace; }
        .fixture-menu { color: #1597e5; font-size: 28px; padding-left: 16px; }
        .fixture-logo { color: #fff; font-family: Arial, sans-serif; font-size: 28px; padding-right: 20px; }
    </style>
</head>
<body>
<header class="top"><span class="fixture-menu">☰</span><span class="fixture-logo">APP BANCOS</span></header>
<div class="espaciador"></div>
<?php include __DIR__ . '/../../app/html/' . $vista . '.php'; ?>
</body>
</html>
