<?php
use GlobalApps\Core\Diagnostics\DiagnosticSuite;
use GlobalApps\Core\Diagnostics\DiagnosticWarning;
use GlobalApps\Core\Diagnostics\RequestProfiler;
use GlobalApps\Core\Acceso\PermisoAcceso;
use GlobalApps\Core\Acceso\PoliticaAcceso;
use GlobalApps\Core\Identidad\Autenticador;
use GlobalApps\Core\Identidad\Cliente;
use GlobalApps\Core\Identidad\Cuenta;
use GlobalApps\Core\Identidad\Empleado;
use GlobalApps\Core\Identidad\Sesion;
use GlobalApps\Core\Identidad\UsuarioZweb;
use GlobalApps\Core\Infrastructure\ApiV2\ApiV2Factory;
use GlobalApps\Core\Infrastructure\Persistence\PdoProvider;

if (!defined('DIAGNOSTICO_HABILITADO') || !DIAGNOSTICO_HABILITADO) {
    http_response_code(404);
    exit;
}

// este archivo se incluye dentro del router, por eso toma la configuracion global
global $app_config;

$suite = new DiagnosticSuite();
$pdo = new PdoProvider([
    'ftweb' => [
        'dsn' => 'pgsql:host=' . HOST . ';port=' . PORT . ';dbname=' . DBASE,
        'user' => USER,
        'password' => PASS,
        'options' => [PDO::ATTR_PERSISTENT => FTWEB_PERSISTENT],
    ],
]);
$ftweb = null;
$sesionRestaurada = null;

$suite->probar('autoload del core', function () {
    $clases = [
        Cuenta::class,
        Empleado::class,
        Cliente::class,
        UsuarioZweb::class,
        PermisoAcceso::class,
        Sesion::class,
        Autenticador::class,
        PoliticaAcceso::class,
        ApiV2Factory::class,
    ];
    foreach ($clases as $clase) {
        if (!class_exists($clase)) {
            throw new RuntimeException('No se pudo cargar ' . $clase . '.');
        }
    }
    return count($clases) . ' clases cargadas bajo demanda';
});

$suite->probar('conexion ftweb', function () use ($pdo, &$ftweb) {
    $ftweb = $pdo->ftweb();
    if ((int) $ftweb->query('select 1')->fetchColumn() !== 1) {
        throw new RuntimeException('La consulta de control no devolvio el valor esperado.');
    }
    return 'conexion y consulta de control correctas';
});

$suite->probar('sesion del core', function () use (&$ftweb, &$sesionRestaurada, $sesion) {
    if (!$ftweb) {
        throw new DiagnosticWarning('No hay conexion para restaurar la sesion.');
    }
    $sesionRestaurada = (new Autenticador($ftweb))->restaurar($sesion->cuenta()->id());
    return 'cuenta activa restaurada sin contrasena ni hash';
});

$suite->probar('permisos de acceso', function () use (&$sesionRestaurada) {
    if ($sesionRestaurada === null) {
        throw new DiagnosticWarning('La sesion no pudo restaurarse.');
    }
    $permisos = 0;
    foreach ($sesionRestaurada->permisosAcceso() as $permisoAcceso) {
        $permisos += count($permisoAcceso->permisosApp());
    }
    return count($sesionRestaurada->permisosAcceso()) . ' aplicaciones y '
        . $permisos . ' permisos internos';
});

$suite->probar('empleado rrhh', function () use (&$sesionRestaurada) {
    if ($sesionRestaurada === null || $sesionRestaurada->empleado() === null) {
        throw new DiagnosticWarning('La cuenta no tiene un empleado activo asociado.');
    }
    $empleado = $sesionRestaurada->empleado();
    return $empleado->legajo() . ': '
        . count($empleado->relacionesLaborales()) . ' relaciones laborales';
});

$suite->probar('identidad zweb', function () use (&$sesionRestaurada) {
    if ($sesionRestaurada === null || $sesionRestaurada->usuarioZweb() === null) {
        throw new DiagnosticWarning('El empleado no tiene una identidad Zweb activa.');
    }
    $usuarioZweb = $sesionRestaurada->usuarioZweb();
    return count($usuarioZweb->nodos()) . ' nodos y '
        . count($usuarioZweb->grupos()) . ' grupos habilitados';
});

$suite->probar('cliente zweb', function () use (&$sesionRestaurada) {
    if ($sesionRestaurada === null || $sesionRestaurada->cliente() === null) {
        throw new DiagnosticWarning('El empleado no tiene un cliente Zweb asociado.');
    }
    $cliente = $sesionRestaurada->cliente();
    return count($cliente->excepcionesCuentaCorriente())
        . ' excepciones de cuenta corriente';
});

$suite->probar('politica de la app', function () use (&$sesionRestaurada) {
    if ($sesionRestaurada === null) {
        throw new DiagnosticWarning('La sesion no pudo restaurarse.');
    }
    $politica = new PoliticaAcceso(
        ID_APLICACION,
        [
            'libre' => FREE_FOR_ALL,
            'requiere_empleado' => REQUIERE_EMPLEADO,
            'requiere_zweb' => REQUIERE_ZWEB_USER,
            'requiere_cliente' => REQUIERE_CLIENTE,
        ]
    );
    $politica->validar($sesionRestaurada);
    return 'permiso y requisitos de identidad correctos';
});

$suite->probar('cliente apis v2', function () use ($app_config) {
    ApiV2Factory::crearCliente($app_config);
    return 'cliente construido; todavia no se realizo una llamada remota';
});

$probarApisV2 = isset($_GET['probar_apis_v2']) && $_GET['probar_apis_v2'] === '1';
if ($probarApisV2) {
    $suite->probar('metadata apis v2', function () use ($app_config) {
        $api = trim((string) ($app_config['apis_v2_diagnostic_api'] ?? ''));
        if ($api === '') {
            throw new DiagnosticWarning('No se configuro apis_v2_diagnostic_api.');
        }
        $metadata = ApiV2Factory::crearCliente($app_config)->metadata($api);
        return 'metadata recibida; requiere token: '
            . ($metadata['requiere_token'] ? 'si' : 'no');
    });

    $suite->probar('token apis v2', function () use ($app_config) {
        if (empty($app_config['apis_v2_user']) || empty($app_config['apis_v2_password'])) {
            throw new DiagnosticWarning('Las credenciales genericas aun no estan configuradas.');
        }
        $token = ApiV2Factory::crearTokenProvider($app_config)->accessToken();
        if ($token === '') {
            throw new RuntimeException('El proveedor devolvio un token vacio.');
        }
        unset($token);
        return 'token recibido y descartado sin mostrarlo ni guardarlo';
    });
}

$resultados = $suite->resultados();
$mediciones = RequestProfiler::mediciones();
?>
<link rel="stylesheet" type="text/css" href="_shared/css/diagnostico.css">
<div class="afterheader diagnostico" id="pagina-diagnostico">
    <section class="diagnostico-encabezado">
        <div>
            <h1><i class="fas fa-stethoscope"></i> Diagnostico del core</h1>
            <p>Pruebas de lectura y conectividad. No se muestran credenciales, hashes ni tokens.</p>
        </div>
        <div class="diagnostico-acciones">
            <a class="boton" href="index.php?shared=diagnostico">REPETIR PRUEBAS</a>
            <a class="boton" href="index.php?shared=diagnostico&amp;probar_apis_v2=1">PROBAR APIS V2</a>
        </div>
    </section>

    <section class="diagnostico-grid">
        <?php foreach ($resultados as $resultado): ?>
            <article class="diagnostico-prueba estado-<?php echo htmlspecialchars($resultado['estado'], ENT_QUOTES, 'UTF-8'); ?>">
                <div class="diagnostico-prueba-titulo">
                    <strong><?php echo htmlspecialchars($resultado['nombre'], ENT_QUOTES, 'UTF-8'); ?></strong>
                    <span><?php echo number_format($resultado['duracion_ms'], 2, ',', '.'); ?> ms</span>
                </div>
                <p><?php echo htmlspecialchars($resultado['detalle'], ENT_QUOTES, 'UTF-8'); ?></p>
                <small><?php echo strtoupper(htmlspecialchars($resultado['estado'], ENT_QUOTES, 'UTF-8')); ?></small>
            </article>
        <?php endforeach; ?>
    </section>

    <section class="diagnostico-rendimiento">
        <h2>Tiempos del request hasta esta pantalla</h2>
        <div class="diagnostico-metricas">
            <?php foreach ($mediciones as $nombreMedicion => $duracion): ?>
                <div>
                    <span><?php echo htmlspecialchars($nombreMedicion, ENT_QUOTES, 'UTF-8'); ?></span>
                    <strong><?php echo number_format($duracion, 2, ',', '.'); ?> ms</strong>
                </div>
            <?php endforeach; ?>
        </div>
        <p>El navegador tambien recibe estas mediciones en el header <code>Server-Timing</code>.</p>
    </section>
</div>
