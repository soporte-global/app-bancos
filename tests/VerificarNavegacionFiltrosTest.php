<?php
$vista = file_get_contents(__DIR__ . '/../app/html/bandeja-mensual.php');
$css = file_get_contents(__DIR__ . '/../app/css/shared.css');
$javascript = file_get_contents(__DIR__ . '/../app/js/shared.js');

$comprobar = static function ($condicion, $mensaje) {
    if (!$condicion) {
        fwrite(STDERR, "FALLO: {$mensaje}\n");
        exit(1);
    }
};

$comprobar(strpos($vista, 'data-pagina-anterior') !== false, 'Falta el control Anterior.');
$comprobar(strpos($vista, 'data-pagina-siguiente') !== false, 'Falta el control Siguiente para resultados con cursor.');
$comprobar(strpos($vista, 'data-posicion-pagina') !== false, 'Falta la posición amigable de página.');
$comprobar(strpos($vista, 'data-limpiar-filtro="limite"') !== false, 'Falta la opción de limpiar el filtro soportado.');
$comprobar(strpos($javascript, 'sessionStorage') !== false, 'Falta persistencia local de la posición anterior.');
$comprobar(strpos($javascript, "parametros.has('cursor')") !== false, 'La paginación no reconoce el cursor actual.');
$comprobar(strpos($javascript, "etiqueta.textContent = 'Límite: '") !== false, 'Falta la actualización local del badge de límite.');
$comprobar(strpos($css, '.bandeja-paginacion-controles') !== false, 'Falta el layout de los controles de paginación.');
$comprobar(strpos($javascript, 'atob(') === false, 'La UI no debe decodificar el cursor opaco.');

$_GET = [];
ob_start();
include __DIR__ . '/fixtures/bandeja-responsive.php';
$html = ob_get_clean();

$comprobar(strpos($html, 'Página 1 · movimientos 1–2') !== false, 'La primera página debe mostrar un rango amigable.');
$comprobar(strpos($html, '>Anterior<') !== false && strpos($html, '>Siguiente<') !== false, 'Deben renderizarse ambos controles.');
$comprobar(strpos($html, 'Límite: 25') !== false, 'El límite no predeterminado debe mostrarse como filtro activo.');
$comprobar(strpos($html, 'cursor-opaco-de-ejemplo') !== false, 'Siguiente debe conservar el cursor opaco existente.');

echo "OK: navegación por cursor e indicadores de filtros validados.\n";
