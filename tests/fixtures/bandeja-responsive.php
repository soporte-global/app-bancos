<?php
define('RUTA_WEB', '/tests/fixtures/bandeja-responsive.php');

$movimientoBase = [
    'fecha_operacion' => '2026-07-14',
    'referencia' => 'TRX-94821',
    'descripcion' => 'Transferencia recibida de cliente mayorista',
    'credito' => '125.430,50',
    'debito' => '0,00',
    'estado_codigo' => 'ABIERTO',
    'valor_zetti_id' => null,
    'asiento_zetti_id' => null,
    'borrador_asiento_id' => null,
    'borrador_id' => null,
    'borrador_fecha_contable' => null,
    'borrador_modelo' => null,
    'borrador_total_debe' => null,
    'borrador_total_haber' => null,
    'ultimo_mensaje_tipo' => null,
    'ultimo_mensaje_cuerpo' => null,
];

$movimientoConSeguimiento = $movimientoBase;
$movimientoConSeguimiento['fecha_operacion'] = '2026-07-15';
$movimientoConSeguimiento['referencia'] = 'DB-20384';
$movimientoConSeguimiento['descripcion'] = 'Débito de servicio bancario mensual';
$movimientoConSeguimiento['credito'] = '0,00';
$movimientoConSeguimiento['debito'] = '18.250,00';
$movimientoConSeguimiento['estado_codigo'] = 'PARA_CERRAR';
$movimientoConSeguimiento['asiento_zetti_id'] = 48219;
$movimientoConSeguimiento['borrador_id'] = 731;
$movimientoConSeguimiento['borrador_fecha_contable'] = '2026-07-15';
$movimientoConSeguimiento['borrador_modelo'] = 'COMISIONES';
$movimientoConSeguimiento['borrador_total_debe'] = '18.250,00';
$movimientoConSeguimiento['borrador_total_haber'] = '18.250,00';
$movimientoConSeguimiento['ultimo_mensaje_tipo'] = 'OBSERVACION';
$movimientoConSeguimiento['ultimo_mensaje_cuerpo'] = 'Pendiente de revisión documental por Tesorería.';

$estadoFixture = $_GET['fixture_estado'] ?? 'resultados';
$bandejaFixture = (object) [
    'resultado' => (object) [
        'cuenta_bancaria_id' => 1042,
        'inicio_periodo' => '2026-07-01',
        'limite' => 25,
        'movimientos' => [$movimientoBase, $movimientoConSeguimiento],
        'siguiente_cursor' => 'cursor-opaco-de-ejemplo',
    ],
];

if ($estadoFixture === 'sin-contexto') {
    $bandejaFixture = (object) [];
    unset($_GET['cuenta_bancaria_id'], $_GET['inicio_periodo']);
} elseif ($estadoFixture === 'sin-movimientos') {
    $bandejaFixture->resultado->movimientos = [];
    $bandejaFixture->resultado->siguiente_cursor = null;
} elseif ($estadoFixture === 'error') {
    $bandejaFixture = (object) ['error' => 'La consulta no respondió dentro del tiempo esperado.'];
    $_GET['cuenta_bancaria_id'] = 1042;
    $_GET['inicio_periodo'] = '2026-07-01';
    $_GET['limite'] = 25;
}

$contextoApp = [
    'data' => (object) [
        'bandeja_mensual' => $bandejaFixture,
    ],
];
?><!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Evidencia de bandeja responsive</title>
    <link rel="stylesheet" href="../../app/css/shared.css">
    <script defer src="../../app/js/shared.js"></script>
    <style>
        body { margin: 0; font-family: Arial, sans-serif; background: #eef3f8; }
        .afterheader { width: 100%; }
    </style>
</head>
<body>
<?php include __DIR__ . '/../../app/html/bandeja-mensual.php'; ?>
</body>
</html>
