<?php
if (!defined('RUTA')) {
    http_response_code(403);
    exit;
}

if (($pagina ?? '') === 'importaciones') {
    try {
        $proveedor = new GlobalApps\Core\Infrastructure\Persistence\PdoProvider([
            'ftweb' => [
                'dsn' => 'pgsql:host=' . HOST . ';port=' . PORT . ';dbname=' . DBASE,
                'user' => USER,
                'password' => PASS,
                'options' => [PDO::ATTR_PERSISTENT => FTWEB_PERSISTENT],
            ],
        ]);
        $conexion = $proveedor->ftweb();
        $esquemas = AppBancos\Infrastructure\EsquemaBancos::desdeConfiguracion([
            'bancos_debug' => BANCOS_DEBUG,
        ]);
        $repositorioImportacion = new AppBancos\Repository\ContextoImportacionRepository(
            $conexion,
            $esquemas
        );
        return (object) ['importaciones' => (object) [
            'cuentas' => $repositorioImportacion->consultar(),
            'configuraciones' => $repositorioImportacion->consultarConfiguraciones(),
            'habilitada' => BANCOS_DEBUG,
        ]];
    } catch (Throwable $error) {
        http_response_code(503);
        error_log(get_class($error) . ': ' . $error->getMessage());
        return (object) ['importaciones' => (object) [
            'cuentas' => [],
            'configuraciones' => [],
            'habilitada' => BANCOS_DEBUG,
            'error' => 'No se pudo cargar el contexto de importacion.',
        ]];
    }
}

if (($pagina ?? '') !== 'bandeja-mensual') {
    return (object) [];
}

$datos = (object) [
    'solicitud' => false,
    'resultado' => null,
    'error' => null,
    'escritura_habilitada' => BANCOS_DEBUG,
    'contexto' => [
        'cuentas' => [],
        'responsables' => [],
    ],
];
$tieneCuenta = array_key_exists('cuenta_bancaria_id', $_GET);
$tienePeriodo = array_key_exists('inicio_periodo', $_GET);
try {
    $pdo = new GlobalApps\Core\Infrastructure\Persistence\PdoProvider([
        'ftweb' => [
            'dsn' => 'pgsql:host=' . HOST . ';port=' . PORT . ';dbname=' . DBASE,
            'user' => USER,
            'password' => PASS,
            'options' => [PDO::ATTR_PERSISTENT => FTWEB_PERSISTENT],
        ],
    ]);
    $conexion = $pdo->ftweb();
    $esquemas = AppBancos\Infrastructure\EsquemaBancos::desdeConfiguracion([
        'bancos_debug' => BANCOS_DEBUG,
    ]);
    $datos->contexto = (new AppBancos\Application\ConsultarContextoBandejaMensual(
        new AppBancos\Repository\ContextoBandejaMensualRepository($conexion, $esquemas)
    ))->ejecutar();

    if (!$tieneCuenta && !$tienePeriodo) {
        return (object) ['bandeja_mensual' => $datos];
    }

    $datos->solicitud = true;
    $consulta = new AppBancos\Application\ConsultarBandejaMensual(
        new AppBancos\Repository\BandejaMensualRepository(
            $conexion,
            $esquemas,
            isset($sesion) && $sesion instanceof GlobalApps\Core\Identidad\Sesion
                ? $sesion->cuenta()->id()
                : null
        ),
        new AppBancos\Application\CursorBandejaMensual(BANCOS_BANDEJA_CURSOR_SECRET)
    );
    $datos->resultado = (object) $consulta->ejecutar($_GET);
} catch (InvalidArgumentException $error) {
    http_response_code(400);
    $datos->error = $error->getMessage();
} catch (Throwable $error) {
    http_response_code(503);
    error_log(get_class($error) . ': ' . $error->getMessage());
    $datos->error = 'No se pudo consultar la información bancaria en este momento.';
}

return (object) ['bandeja_mensual' => $datos];
