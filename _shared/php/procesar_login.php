<?php
$raiz = dirname(__DIR__, 2);
require_once $raiz . '/src/Model/Core/Diagnostics/RequestProfiler.php';

GlobalApps\Core\Diagnostics\RequestProfiler::iniciar();
GlobalApps\Core\Diagnostics\RequestProfiler::comenzar('config');
require_once $raiz . '/src/config.php';
GlobalApps\Core\Diagnostics\RequestProfiler::terminar('config');
GlobalApps\Core\Diagnostics\RequestProfiler::habilitar(DIAGNOSTICO_HABILITADO);
GlobalApps\Core\Diagnostics\RequestProfiler::comenzar('autoload');
require_once $raiz . '/src/autoload.php';
GlobalApps\Core\Diagnostics\RequestProfiler::terminar('autoload');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (ID_APLICACION <= 0) {
    header('Location: ' . RUTA_WEB . '/index.php');
    exit;
}

unset($_SESSION['GENERAL']['user']);
setcookie('LOGIN', '', time() - 3600, '/');
setcookie(FolderName . '-ready', '', time() - 3600, '/');
unset($_COOKIE['LOGIN'], $_COOKIE[FolderName . '-ready']);

$username = trim((string) ($_POST['username'] ?? ''));
$cache = new GlobalApps\Core\Identidad\SesionCache(FolderName, AUTH_CACHE_TTL);

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Location: ' . RUTA_WEB . '/index.php');
    exit;
}

try {
    if ($username === '') {
        throw new InvalidArgumentException('No se recibieron credenciales validas.');
    }

    $password = base64_decode((string) ($_POST['password'] ?? ''), true);
    if ($password === false) {
        throw new InvalidArgumentException('La contrasena no tiene un formato valido.');
    }

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
        ->autenticar($username, $password);
    GlobalApps\Core\Diagnostics\RequestProfiler::terminar('login');
    unset($password);

    session_regenerate_id(true);
    $_SESSION[AUTH_SESSION_KEY] = [
        'cuenta_id' => $sesion->cuenta()->id(),
        'vence_at' => time() + LOGIN_MAX_AGE,
    ];
    unset($_SESSION[FolderName]['login_username']);
    unset($_SESSION['GENERAL']['user'], $_SESSION['GENERAL']['login_error']);
    $cache->guardar($sesion);

} catch (Throwable $error) {
    unset($password);
    $cache->limpiar();
    $_SESSION[FolderName]['login_username'] = $username;
    $errorEsperado = $error instanceof GlobalApps\Core\Identidad\AutenticacionException
        || $error instanceof InvalidArgumentException;
    if (!$errorEsperado) {
        error_log(get_class($error) . ': ' . $error->getMessage());
    }
    $_SESSION['GENERAL']['login_error'] = $error instanceof PDOException
        ? 'No se pudo validar el acceso en este momento.'
        : $error->getMessage();
}

header('Location: ' . RUTA_WEB . '/index.php');
exit;
