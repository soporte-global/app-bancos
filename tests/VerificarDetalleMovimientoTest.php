<?php
$vista = file_get_contents(__DIR__ . '/../app/html/bandeja-mensual.php');
$css = file_get_contents(__DIR__ . '/../app/css/bancos.css');
$javascript = file_get_contents(__DIR__ . '/../app/js/shared.js');

$comprobar = static function ($condicion, $mensaje) {
    if (!$condicion) {
        fwrite(STDERR, "FALLO: {$mensaje}\n");
        exit(1);
    }
};

$comprobar(strpos($vista, 'data-abrir-detalle') !== false, 'Falta el acceso al detalle desde la fila.');
$comprobar(strpos($vista, '<template data-detalle-movimiento>') !== false, 'El detalle debe reutilizar los datos ya renderizados.');
$comprobar(strpos($vista, 'data-panel-detalle hidden') !== false, 'Falta el panel lateral inicialmente cerrado.');
$comprobar(strpos($vista, '<h3>Asociaciones</h3>') !== false, 'Falta la sección Asociaciones.');
$comprobar(strpos($vista, '<h3>Mensajes</h3>') !== false, 'Falta la sección Mensajes.');
$comprobar(strpos($vista, 'data-agregar-mensaje') !== false, 'Falta la acción para publicar mensajes.');
$comprobar(strpos($vista, 'data-marcar-mensajes-leidos') !== false, 'Falta la acción para registrar lecturas.');
$comprobar(strpos($vista, 'mensaje-no-leido') !== false, 'Falta la identificación de mensajes pendientes.');
$comprobar(strpos($vista, 'data-asignar-responsable') !== false, 'Falta la asignación de responsable.');
$comprobar(strpos($vista, 'acceso efectivo a APP BANCOS') !== false, 'La interfaz no explica el alcance de responsables.');
$comprobar(strpos($vista, '<h3>Borradores</h3>') !== false, 'Falta la sección Borradores.');
$comprobar(strpos($vista, '<h3>Historial</h3>') !== false, 'Falta la sección Historial.');
$comprobar(strpos($vista, 'Sin eventos históricos adicionales.') !== false, 'La ausencia de historial adicional debe ser explícita.');
$comprobar(strpos($css, '.bandeja-resumen-texto') !== false && strpos($css, '-webkit-line-clamp: 2') !== false, 'La fila debe resumir el contenido extenso.');
$comprobar(strpos($css, '.bandeja-detalle') !== false && strpos($css, 'right: 0') !== false, 'El detalle debe abrir como panel lateral.');
$comprobar(substr_count($css, 'height: 100dvh') >= 2, 'El panel y su fondo deben cubrir el viewport aunque la tabla tenga pocas filas.');
$comprobar(strpos($css, 'body:has(.bancos-app)') !== false && strpos($css, 'min-height: 100dvh') !== false, 'El shell filtrado debe conservar como mínimo la altura visible.');
$comprobar(strpos($javascript, 'plantilla.content.cloneNode(true)') !== false, 'El panel debe usar contenido local sin otra consulta.');
$comprobar(strpos($javascript, 'disparador.focus({ preventScroll: true })') !== false, 'Cerrar el panel debe devolver el foco sin mover la lista.');
$comprobar(strpos($javascript, 'window.scrollTo(0, posicionScroll)') !== false, 'Cerrar el panel debe restaurar la posición de la lista.');
$comprobar(strpos($javascript, "classList.add('bandeja-detalle-activo')") !== false, 'Abrir el panel debe bloquear el scroll de fondo.');
$comprobar(strpos($javascript, "classList.remove('bandeja-detalle-activo')") !== false, 'Cerrar el panel debe restaurar el scroll de fondo.');
$comprobar(strpos($javascript, "evento.key === 'Tab'") !== false, 'El diálogo debe contener el foco de teclado.');
$comprobar(strpos($javascript, 'movimiento.agregar-mensaje') !== false, 'El cliente no publica mensajes por el endpoint esperado.');
$comprobar(strpos($javascript, 'movimiento.marcar-mensajes-leidos') !== false, 'El cliente no registra lecturas por el endpoint esperado.');
$comprobar(strpos($javascript, 'movimiento.asignar-responsable') !== false, 'El cliente no asigna responsables por el endpoint esperado.');
$comprobar(!preg_match('/(?:history\.(?:pushState|replaceState)|location\.(?:assign|replace))/', $javascript), 'Abrir detalle no debe modificar URL ni cursor.');

$_GET = [];
ob_start();
include __DIR__ . '/fixtures/bandeja-responsive.php';
$html = ob_get_clean();

$comprobar(substr_count($html, 'data-abrir-detalle') === 2, 'Cada movimiento debe ofrecer un único acceso al detalle.');
$comprobar(strpos($html, 'Borrador #731 · COMISIONES') !== false, 'La fila debe mostrar un resumen compacto del borrador.');
$comprobar(strpos($html, 'Pendiente de revisión documental por Tesorería.') !== false, 'El detalle debe conservar el cuerpo completo del mensaje.');
$comprobar(strpos($html, '<th>Debe</th><th>Haber</th>') !== false && strpos($html, '<td>18.250,00</td>') !== false, 'El detalle debe conservar las líneas del borrador.');

$_GET = ['fixture_filas' => 1];
ob_start();
include __DIR__ . '/fixtures/bandeja-responsive.php';
$htmlUnaFila = ob_get_clean();
$comprobar(substr_count($htmlUnaFila, 'data-abrir-detalle') === 1, 'El fixture debe cubrir el detalle con una sola fila.');

echo "OK: resumen de fila y detalle de movimiento validados.\n";
