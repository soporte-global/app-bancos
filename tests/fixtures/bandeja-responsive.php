<?php
if (!defined('RUTA_WEB')) {
    define('RUTA_WEB', '/tests/fixtures/bandeja-responsive.php');
}

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

$movimientosFixture = [$movimientoBase, $movimientoConSeguimiento];
$cantidadFilasFixture = max(1, min(20, (int) ($_GET['fixture_filas'] ?? 2)));
$movimientosFixture = array_slice($movimientosFixture, 0, $cantidadFilasFixture);
for ($indice = 3; $indice <= $cantidadFilasFixture; $indice++) {
    $movimientoAdicional = $indice % 2 === 0 ? $movimientoConSeguimiento : $movimientoBase;
    $movimientoAdicional['referencia'] = 'MOV-' . str_pad((string) $indice, 3, '0', STR_PAD_LEFT);
    $movimientoAdicional['descripcion'] = 'Movimiento representativo para prueba visual de desplazamiento y densidad';
    $movimientosFixture[] = $movimientoAdicional;
}

$estadoFixture = $_GET['fixture_estado'] ?? 'resultados';
$temaFixture = ($_GET['fixture_tema'] ?? 'claro') === 'oscuro' ? 'oscuro' : 'claro';
$limiteFixture = max(1, min(100, (int) ($_GET['limite'] ?? 25)));
$bandejaFixture = (object) [
    'resultado' => (object) [
        'cuenta_bancaria_id' => 1042,
        'inicio_periodo' => '2026-07-01',
        'limite' => $limiteFixture,
        'movimientos' => $movimientosFixture,
        'pagina_actual' => 1,
        'inicio_actual' => count($movimientosFixture) > 0 ? 1 : 0,
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
    <link rel="stylesheet" href="../../_shared/css/paleta_colores.css">
    <link rel="stylesheet" href="../../_shared/css/general.css">
    <link rel="stylesheet" href="../../_shared/css/header.css">
    <link rel="stylesheet" href="../../app/css/bancos.css">
    <link rel="stylesheet" href="../../app/css/tema_componentes.css">
    <link rel="stylesheet" href="../../app/css/tema_<?php echo $temaFixture; ?>.css">
    <script defer src="../../app/js/shared.js"></script>
    <style>
        body { margin: 0; font-family: 'Roboto Mono', monospace; }
        .afterheader { width: 100%; }
        .fixture-menu { color: #1597e5; font-size: 28px; padding-left: 16px; }
        .fixture-logo { color: #fff; font-family: Arial, sans-serif; font-size: 28px; padding-right: 20px; }
    </style>
</head>
<body class="bandeja-fixture">
<header class="top"><span class="fixture-menu">☰</span><span class="fixture-logo">APP BANCOS</span></header>
<div class="espaciador"></div>
<?php include __DIR__ . '/../../app/html/bandeja-mensual.php'; ?>
<header class="footer irrelevante" style="display: none;"></header>
</body>
</html>
