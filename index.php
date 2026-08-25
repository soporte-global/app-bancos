<?php 
ob_start();
require_once(__DIR__ . '/src/Model/Core/Diagnostics/RequestProfiler.php');
GlobalApps\Core\Diagnostics\RequestProfiler::iniciar();
// -------------------------
header('Access-Control-Allow-Origin: *');
GlobalApps\Core\Diagnostics\RequestProfiler::comenzar('config');
require_once(__DIR__ . '/src/config.php');         // archivo de configuración
GlobalApps\Core\Diagnostics\RequestProfiler::terminar('config');
GlobalApps\Core\Diagnostics\RequestProfiler::habilitar(DIAGNOSTICO_HABILITADO);
GlobalApps\Core\Diagnostics\RequestProfiler::comenzar('autoload');
require_once(__DIR__ . '/src/autoload.php');       // carga clases cuando realmente se usan
GlobalApps\Core\Diagnostics\RequestProfiler::terminar('autoload');
GlobalApps\Core\Diagnostics\RequestProfiler::comenzar('funciones');
require_once(__DIR__ . '/src/funciones.php');      // archivo de funciones compartidas
GlobalApps\Core\Diagnostics\RequestProfiler::terminar('funciones');
?>
<!doctype html>
<html>
<?php 
    GlobalApps\Core\Diagnostics\RequestProfiler::comenzar('html_head');
    include(RUTA . '/_shared/html/html-head.html');
    GlobalApps\Core\Diagnostics\RequestProfiler::terminar('html_head');
?>
    <body class="color_variable">
    <style id="css_dinamico"></style>
        <?php 
            // chequea si el usuario está logueado: si se le envió info por POST o si hay un SESSION o COOKIE
            // chequea si están disponibles todos los datos necesarios para abrir la aplicación o si falta algo
            // verifica el login y si es correcto, evalúa permisos de acceso del usuario
            // llama a pantalla_login o redirecciona según corresponda al GET
            GlobalApps\Core\Diagnostics\RequestProfiler::comenzar('routing');
            include(RUTA . '/_shared/php/redireccion_inicial.php');
            GlobalApps\Core\Diagnostics\RequestProfiler::terminar('routing');
        ?>
    </body>
</html>

