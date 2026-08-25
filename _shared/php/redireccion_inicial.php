<?php
require_once RUTA . '/_shared/php/contexto_app.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
unset($_SESSION['ERROR']);

// el primer request elimina cualquier credencial conservada por el login anterior
unset($_SESSION['GENERAL']['user']);
setcookie('LOGIN', '', time() - 3600, '/');
setcookie(FolderName . '-ready', '', time() - 3600, '/');
unset($_COOKIE['LOGIN'], $_COOKIE[FolderName . '-ready']);

if (ID_APLICACION <= 0) {
    renderizar_aplicacion();
    return;
}

$cuentaId = isset($_SESSION[AUTH_SESSION_KEY]['cuenta_id'])
    ? (int) $_SESSION[AUTH_SESSION_KEY]['cuenta_id']
    : 0;
$venceAt = (int) ($_SESSION[AUTH_SESSION_KEY]['vence_at'] ?? 0);
$username = (string) ($_SESSION[FolderName]['login_username'] ?? '');
$cache = new GlobalApps\Core\Identidad\SesionCache(FolderName, AUTH_CACHE_TTL);

if ($cuentaId <= 0 || $venceAt <= time()) {
    if ($cuentaId > 0) {
        $_SESSION['GENERAL']['login_error'] = 'La sesion vencio. Ingrese nuevamente.';
    }
    $cache->limpiar();
    unset($_SESSION[AUTH_SESSION_KEY]);
    mostrar_login($username);
    return;
}

try {
    $sesion = $cache->obtener();
    if ($sesion !== null && $sesion->cuenta()->id() !== $cuentaId) {
        $cache->limpiar();
        $sesion = null;
    }

    if ($sesion === null) {
        $pdo = new GlobalApps\Core\Infrastructure\Persistence\PdoProvider([
            'ftweb' => [
                'dsn' => 'pgsql:host=' . HOST . ';port=' . PORT . ';dbname=' . DBASE,
                'user' => USER,
                'password' => PASS,
                'options' => [PDO::ATTR_PERSISTENT => FTWEB_PERSISTENT],
            ],
        ]);

        GlobalApps\Core\Diagnostics\RequestProfiler::comenzar('login');
        $sesion = (new GlobalApps\Core\Identidad\Autenticador($pdo->ftweb()))
            ->restaurar($cuentaId);
        GlobalApps\Core\Diagnostics\RequestProfiler::terminar('login');
        $cache->guardar($sesion);
    }

} catch (GlobalApps\Core\Identidad\AutenticacionException $error) {
    $cache->limpiar();
    unset($_SESSION[AUTH_SESSION_KEY]);
    $_SESSION['GENERAL']['login_error'] = $error->getMessage();
    mostrar_login($username);
    return;
} catch (Throwable $error) {
    $cache->limpiar();
    error_log(get_class($error) . ': ' . $error->getMessage());
    $_SESSION['GENERAL']['login_error'] = 'No se pudo restaurar la sesion en este momento.';
    mostrar_login($username);
    return;
}

$politica = new GlobalApps\Core\Acceso\PoliticaAcceso(ID_APLICACION, [
    'libre' => FREE_FOR_ALL,
    'requiere_empleado' => REQUIERE_EMPLEADO,
    'requiere_cliente' => REQUIERE_CLIENTE,
    'requiere_zweb' => REQUIERE_ZWEB_USER,
]);
try {
    $politica->validar($sesion);
} catch (GlobalApps\Core\Acceso\AccesoDenegadoException $error) {
    renderizar_aplicacion($sesion, [], false);
    return;
}

unset($_SESSION['GENERAL']['login_error'], $_SESSION[FolderName]['login_username']);
$permisosApp = $politica->permisosApp($sesion);
renderizar_aplicacion($sesion, $permisosApp);

function mostrar_login($username)
{
    $sesion = null;
    $permisos_app = [];
    $pagina = 'login';
    $dataEnviar = (object) [];
    $contextoApp = crear_contexto_app($sesion, $pagina, $dataEnviar);

    // el adaptador puede reconstruir variables PHP legacy en este mismo scope
    require RUTA . '/app/compatibilidad/contexto.php';
    validar_contexto_app($contextoApp);
    include RUTA . '/_shared/html/contexto-app.php';
    include RUTA . '/_shared/html/header.php';
    include RUTA . '/_shared/html/login.php';
    unset($_SESSION['GENERAL']['login_error']);
}

function renderizar_aplicacion(
    GlobalApps\Core\Identidad\Sesion $sesion = null,
    array $permisosApp = [],
    $accesoAplicacion = true
)
{
    $permisos_app = $permisosApp;
    $pagina = 'home';
    $incluirFooter = true;
    $cargarDatos = true;

    // una denegacion de la app se resuelve antes que cualquier destino interno
    if (!$accesoAplicacion) {
        http_response_code(403);
        $pagina = 'acceso_restringido';
        $archivo = RUTA . '/_shared/html/acceso_restringido.html';
        $incluirFooter = false;
        $cargarDatos = false;
    } elseif (isset($_GET['shared'])) {
        $pagina = (string) $_GET['shared'];
        $cargarDatos = false;
        if ($_GET['shared'] !== 'diagnostico') {
            http_response_code(404);
            $pagina = 'error';
            $archivo = RUTA . '/_shared/html/error.html';
            $incluirFooter = false;
        } elseif (!DIAGNOSTICO_HABILITADO) {
            http_response_code(403);
            $pagina = 'acceso_restringido';
            $archivo = RUTA . '/_shared/html/acceso_restringido.html';
            $incluirFooter = false;
        } else {
            $archivo = RUTA . '/_shared/html/diagnostico.php';
        }
    } elseif (isset($_GET['pag'])) {
        $pagina = (string) $_GET['pag'];
        $paginaValida = preg_match('/\A[a-zA-Z0-9_-]+\z/', $pagina) === 1;
        $php = $paginaValida ? RUTA . '/app/html/' . $pagina . '.php' : null;
        $html = $paginaValida ? RUTA . '/app/html/' . $pagina . '.html' : null;
        $archivo = $html && is_file($html) ? $html : ($php && is_file($php) ? $php : null);

        if ($archivo === null) {
            http_response_code(404);
            $pagina = 'error';
            $archivo = RUTA . '/_shared/html/error.html';
            $incluirFooter = false;
            $cargarDatos = false;
        } elseif ($sesion !== null) {
            $paginaAutorizada = false;
            foreach ($permisos_app as $permiso) {
                if ($pagina === $permiso->nombreInterno()) {
                    $paginaAutorizada = true;
                    break;
                }
            }
            if (!$paginaAutorizada) {
                http_response_code(403);
                $pagina = 'acceso_restringido';
                $archivo = RUTA . '/_shared/html/acceso_restringido.html';
                $incluirFooter = false;
                $cargarDatos = false;
            }
        }
    } else {
        $homePhp = RUTA . '/app/html/home.php';
        $homeHtml = RUTA . '/app/html/home.html';
        $archivo = is_file($homeHtml)
            ? $homeHtml
            : (is_file($homePhp) ? $homePhp : RUTA . '/_shared/html/en_construccion.html');
    }

    // los datos de negocio se cargan recien despues de resolver y autorizar el destino
    $dataEnviar = (object) [];
    if ($cargarDatos) {
        $dataEnviar = require RUTA . '/app/php/carga_inicial.php';
        if (!is_object($dataEnviar)) {
            throw new RuntimeException('app/php/carga_inicial.php debe devolver un objeto.');
        }
    }

    $contextoApp = crear_contexto_app($sesion, $pagina, $dataEnviar);
    // el adaptador puede reconstruir variables PHP legacy en este mismo scope
    require RUTA . '/app/compatibilidad/contexto.php';
    validar_contexto_app($contextoApp);
    include RUTA . '/_shared/html/contexto-app.php';
    include RUTA . '/_shared/html/header.php';
    include $archivo;
    if ($incluirFooter) {
        include RUTA . '/_shared/html/footer.html';
    }
}
