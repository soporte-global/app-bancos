<?php
if (!defined('RUTA')) {
    http_response_code(403);
    exit;
}

if (($pagina ?? '') !== 'bandeja-mensual') {
    return (object) [];
}

$datos = (object) [
    'solicitud' => false,
    'resultado' => null,
    'error' => null,
];
$tieneCuenta = array_key_exists('cuenta_bancaria_id', $_GET);
$tienePeriodo = array_key_exists('inicio_periodo', $_GET);
if (!$tieneCuenta && !$tienePeriodo) {
    return (object) ['bandeja_mensual' => $datos];
}

$datos->solicitud = true;
try {
    $pdo = new GlobalApps\Core\Infrastructure\Persistence\PdoProvider([
        'ftweb' => [
            'dsn' => 'pgsql:host=' . HOST . ';port=' . PORT . ';dbname=' . DBASE,
            'user' => USER,
            'password' => PASS,
            'options' => [PDO::ATTR_PERSISTENT => FTWEB_PERSISTENT],
        ],
    ]);
    $consulta = new AppBancos\Application\ConsultarBandejaMensual(
        new AppBancos\Repository\BandejaMensualRepository(
            $pdo->ftweb(),
            AppBancos\Infrastructure\EsquemaBancos::desdeConfiguracion([
                'bancos_debug' => BANCOS_DEBUG,
            ])
        ),
        new AppBancos\Application\CursorBandejaMensual(BANCOS_BANDEJA_CURSOR_SECRET)
    );
    $datos->resultado = (object) $consulta->ejecutar($_GET);
} catch (InvalidArgumentException $error) {
    http_response_code(400);
    $datos->error = $error->getMessage();
}

return (object) ['bandeja_mensual' => $datos];
