<?php
$raiz = dirname(__DIR__, 2);
require_once $raiz . '/src/config.php';
require_once $raiz . '/src/autoload.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$color = isset($_SESSION['COLOR_CONF']) && is_array($_SESSION['COLOR_CONF'])
    ? $_SESSION['COLOR_CONF']
    : null;

$_SESSION = [];
session_destroy();

// cerrar sesion elimina la autenticacion compartida por todas las apps nuevas
// estas cookies pertenecen al login anterior y se limpian durante la migracion
setcookie('LOGIN', '', time() - 3600, '/');
setcookie(FolderName . '-ready', '', time() - 3600, '/');
unset($_COOKIE['LOGIN'], $_COOKIE[FolderName . '-ready']);

session_name(SESSION_NAME);
session_start();
session_regenerate_id(true);
if ($color !== null) {
    $_SESSION['COLOR_CONF'] = $color;
}

header('Location: ' . RUTA_WEB . '/index.php');
exit;
