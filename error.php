<?php 
header('Access-Control-Allow-Origin: *');
require_once(__DIR__ . '/src/config.php');         // archivo de configuración
require_once(__DIR__ . '/src/autoload.php');       // carga las clases del contexto
require_once(__DIR__ . '/src/funciones.php');      // archivo de funciones 
require_once(__DIR__ . '/_shared/php/contexto_app.php');
?>
<!doctype html>
<html>
    <?php 
    include(RUTA . '/_shared/html/html-head.html');
    ?>
    <body>
<?php 
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $title = "<a class=\"error_title color_variable\" href=\"".RUTA_WEB."\"> < VOLVER AL INICIO > </a>";
    $error = '<span class="error-message"><i class="fas fa-exclamation-triangle"></i> La página que está buscando fue trasladada o no existe.</span>';

    if(isset($_SESSION['ERROR'])){
        $mensaje_error = json_decode($_SESSION['ERROR']['mensaje']);
        $e = unserialize(base64_decode($_SESSION['ERROR']['objeto']));
        if (is_array($e) && isset($e['type'])) {
            $error = "<div class=\"div_error-f1\">";
            $error .=    "<div class=\"div_error-head\">";
            $error .=       "<p> <i class=\"fas fa-exclamation-triangle\"></i></p>";
            $error .=       "<p class=\"mensaje_error\">" . $mensaje_error . "</p>";
            $error .=    "</div>";
            $error .=   "<div class=\"div_tabla\">";
            $error .=   "<p class=\"title-i\"> INFORMACIÓN DEL ERROR: </p>";
            $error .=   "<table class=\"info_error\">";
            $error .=       "<tr><td>Tipo:</td><td>" . utf8izar($e['type']) . "</td></tr>";
            $error .=       "<tr><td>Encontrado en:</td><td>" . utf8izar($e['file']) . "</td></tr>";
            $error .=       "<tr><td>Línea:</td><td>" . utf8izar($e['line']) . "</td></tr>";
            $error .=       "<tr><td>Código:</td><td>" . utf8izar($e['code']) . "</td></tr>";
            $error .=       "<tr><td>Descripción:</td><td>" . utf8izar($e['message']) . "</td></tr>";
            $error .=   "</table>";
            $error .=   "<p class=\"title-i\"> CALL STACK: </p>";
            $error .=       "<table>" . ($e['trace']) . "</table>";
            $error .=   "</div>";
            $error .= "</div>";
        } else {
            $error = "<div class=\"div_error-f1\">";
            $error .=    "<div class=\"div_error-head\">";
            $error .=       "<p> <i class=\"fas fa-exclamation-triangle\"></i></p>";
            $error .=       "<p class=\"mensaje_error\">" . $mensaje_error . "</p>";
            $error .=    "</div>";
            $error .=   "<p class=\"title-i\"> INFORMACIÓN DEL ERROR: </p>";
            $error .=   "<table class=\"info_error\">";
            $error .=       "<tr><td>Tipo:</td><td> FATAL ERROR </td></tr>";
            $error .=       "<tr><td>Encontrado en:</td><td>" . utf8izar($e['file']) . "</td></tr>";
            $error .=       "<tr><td>Línea:</td><td>" . utf8izar($e['line']) . "</td></tr>";
            $error .=       "<tr><td>Descripción:</td><td>" . utf8izar($e['message']) . "</td></tr>";
            $error .=   "</table>";
            $error .= "</div>";
        }
    }
    $sesion = null;
    $permisos_app = [];
    $pagina = 'error';
    $dataEnviar = (object) [];
    $contextoApp = crear_contexto_app($sesion, $pagina, $dataEnviar);

    // el adaptador puede reconstruir variables PHP legacy en este mismo scope
    require RUTA . '/app/compatibilidad/contexto.php';
    validar_contexto_app($contextoApp);
    include RUTA . '/_shared/html/contexto-app.php';
    include(RUTA . '/_shared/html/header.php');
    echo $title;
?>
        <div class="afterheader error">
            <div class="centrado">
                    <?php 
                        echo "$error";
                    ?>                
            </div>
        </div>
    </body>
</html>
<script>
    $('header.top').addClass('error');
</script>
<?php 
    // mata session por si está causando algún problema que haya conducido a esta pantalla
    if(isset($_SESSION['ERROR']['cerrar'])){
        session_destroy();
    }
?>
