<?php 
$exception_occurred = false;
$vista_html = true; // en el script se pisará este valor en caso de ser necesario, esto es su valor por defecto

// register_shutdown_function(function() {
//     global $exception_occurred, $vista_html, $usa_ajax;
//     if(isset($usa_ajax) && !isset($vista_html)){
//         $vista_html = !$usa_ajax;
//     }
//     if (!$exception_occurred) {
//         $e = error_get_last(); // Obtiene el último error ocurrido
//         if ($e !== NULL) {
//             if (session_status() !== PHP_SESSION_ACTIVE) {
// 				session_start();
// 			}
//             if($vista_html){
//                 envia_error_visualizacion($e, "Se ha producido un error, por favor consulte con el encargado de sistemas, detalles debajo:");
//             }else{
//                 escribe_error($e, "Se ha producido un error, por favor consulte con el encargado de sistemas, detalles debajo:");
//             }
//         }
//     }
// });


function envia_error_visualizacion($e, $mensaje_error = 'Error al realizar esta operación, por favor contacte a soporte o inténtelo de nuevo más tarde', $cerrar_sesion = false) {
    global $exception_occurred;
	if ($e instanceof Throwable) {
        $exception_occurred = true;
        $_SESSION['ERROR']['objeto'] = base64_encode(serialize([
            'type' => get_class($e),
            'message' => $e->getMessage(),
            'code' => $e->getCode(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => trace_sanitizado($e->getTraceAsString(),true)
        ]));
    } else {
        $_SESSION['ERROR']['objeto'] = base64_encode(serialize([
            'message' => $e['message'],
            'file' => $e['file'],
            'line' => $e['line'],
        ]));
    }
	$_SESSION['ERROR']['mensaje'] = json_encode($mensaje_error);
    if ($cerrar_sesion) {
        $_SESSION['ERROR']['cerrar'] = true;
    }
    header('Location: ' . RUTA_WEB . '/error.php');
}

function escribe_error($e, $mensaje_error = 'Error al realizar esta operación, por favor contacte a soporte o inténtelo de nuevo más tarde', $html = false) {
    $sl = $html ? '<br>' : "\n";
    $error = $mensaje_error . "$sl$sl";

    if ($e instanceof Throwable) {
        $error .= "Error del tipo " . get_class($e) . "$sl";
        $error .= "--------------------------------------$sl";
        $error .= "Código de error: " . $e->getCode() . "$sl";
        $error .= "Encontrado en: " . $e->getFile() . "$sl";
        $error .= "Línea: " . $e->getLine() . "$sl";
        $error .= "Descripción: " . utf8izar($e->getMessage()) . "$sl";

        $error .= "Traza de errores:$sl" . trace_sanitizado($e->getTraceAsString(),$html);
    } else {
        // Manejo de errores no-Throwable
        $_SESSION['ERROR']['objeto'] = base64_encode(serialize([
            'message' => $e['message'],
            'file' => $e['file'],
            'line' => $e['line'],
        ]));
        $error .= "Error: $sl";
        $error .= "--------------------------------------$sl";
        $error .= "Código de error: -No Establecido- $sl";
        $error .= "Encontrado en: " . ($e['file']) . "$sl";
        $error .= "Línea: " . ($e['line']) . "$sl";
        $error .= "Descripción: " . ($e['message']);
    }
    echo $error;
}


function trace_sanitizado(string $trace, $html = false): string {
    $sl = $html ? '<br>' : "\n";
    // salto de línea para dejar el trace más bonito
    $trace = trim(str_replace('#',"$sl#",$trace),$sl);
    // quita cualquier cosa entre paréntesis, para no mostrar cosas como contraseñas etc
    return preg_replace('/\((.*?)\)/', '()', $trace);
}