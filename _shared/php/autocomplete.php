<?php
$raiz = dirname(__DIR__, 2);
require_once $raiz . '/src/config.php';
require_once $raiz . '/src/autoload.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$conexion = null;

try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405);
        throw new RuntimeException('El autocomplete solo acepta solicitudes POST.');
    }

    $cuentaId = isset($_SESSION[AUTH_SESSION_KEY]['cuenta_id'])
        ? (int) $_SESSION[AUTH_SESSION_KEY]['cuenta_id']
        : 0;
    $venceAt = (int) ($_SESSION[AUTH_SESSION_KEY]['vence_at'] ?? 0);
    if ($cuentaId <= 0 || $venceAt <= time()) {
        unset($_SESSION[AUTH_SESSION_KEY], $_SESSION[FolderName]['sesion_cache']);
        http_response_code(401);
        throw new RuntimeException('La sesion no esta autenticada.');
    }

    $consultas = $app_config['autocomplete_consultas'] ?? [];
    $consulta = trim((string) ($_POST['consulta'] ?? ''));
    if ($consulta === '' || !is_array($consultas) || !isset($consultas[$consulta])) {
        http_response_code(400);
        throw new InvalidArgumentException('La consulta de autocomplete no existe.');
    }

    $baseSql = realpath(RUTA . '/app/sql');
    $archivoSql = $baseSql === false
        ? false
        : realpath($baseSql . DIRECTORY_SEPARATOR . (string) $consultas[$consulta]);
    if ($baseSql === false
        || $archivoSql === false
        || strpos($archivoSql, $baseSql . DIRECTORY_SEPARATOR) !== 0
        || strtolower(pathinfo($archivoSql, PATHINFO_EXTENSION)) !== 'sql'
    ) {
        http_response_code(500);
        throw new RuntimeException('La consulta de autocomplete esta mal configurada.');
    }

    $proveedor = new GlobalApps\Core\Infrastructure\Persistence\PdoProvider([
        'ftweb' => [
            'dsn' => 'pgsql:host=' . HOST . ';port=' . PORT . ';dbname=' . DBASE,
            'user' => USER,
            'password' => PASS,
            'options' => [PDO::ATTR_PERSISTENT => FTWEB_PERSISTENT],
        ],
    ]);
    $conexion = $proveedor->ftweb();

    $cache = new GlobalApps\Core\Identidad\SesionCache(FolderName, AUTH_CACHE_TTL);
    $sesion = $cache->obtener();
    if ($sesion === null || $sesion->cuenta()->id() !== $cuentaId) {
        $sesion = (new GlobalApps\Core\Identidad\Autenticador($conexion))->restaurar($cuentaId);
        $cache->guardar($sesion);
    }
    (new GlobalApps\Core\Acceso\PoliticaAcceso(
        ID_APLICACION,
        [
            'libre' => FREE_FOR_ALL,
            'requiere_empleado' => REQUIERE_EMPLEADO,
            'requiere_zweb' => REQUIERE_ZWEB_USER,
            'requiere_cliente' => REQUIERE_CLIENTE,
        ]
    ))->validar($sesion);

    $conexion->beginTransaction();
    $conexion->exec('set transaction read only');
    $prepare = $conexion->prepare(file_get_contents($archivoSql));
    $prepare->execute([':busqueda' => (string) ($_POST['busqueda'] ?? '')]);
    $respuesta = $prepare->fetchAll(PDO::FETCH_ASSOC);
    $conexion->rollBack();

    echo json_encode($respuesta);
} catch (Throwable $error) {
    if ($conexion instanceof PDO && $conexion->inTransaction()) {
        $conexion->rollBack();
    }
    if ($error instanceof GlobalApps\Core\Identidad\AutenticacionException) {
        unset($_SESSION[AUTH_SESSION_KEY], $_SESSION[FolderName]['sesion_cache']);
        http_response_code(401);
    } elseif ($error instanceof GlobalApps\Core\Acceso\AccesoDenegadoException) {
        http_response_code(403);
    } elseif (http_response_code() < 400) {
        http_response_code(500);
    }
    $estado = http_response_code();
    if ($estado >= 500) {
        error_log(get_class($error) . ': ' . $error->getMessage());
    }
    $mensaje = $estado >= 500
        ? 'No se pudo completar la consulta de autocomplete.'
        : $error->getMessage();
    echo json_encode(['error' => $mensaje]);
}

exit;
