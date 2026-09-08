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

$comprobar(strpos($vista, '<main class="afterheader bandeja-page">') !== false, 'La bandeja debe identificar su shell para liberar el scroll heredado.');
$comprobar(strpos($css, '.afterheader.bandeja-page') !== false && strpos($css, 'overflow: visible') !== false, 'El contenedor debe permitir crecimiento vertical.');
$comprobar(strpos($css, 'html.bandeja-scroll') !== false && strpos($css, 'body.bandeja-scroll') !== false, 'Documento y body deben recuperar scroll vertical.');
$comprobar(strpos($javascript, "document.documentElement.classList.add('bandeja-scroll')") !== false, 'La corrección de scroll debe limitarse a la bandeja.');
$comprobar(substr_count($vista, '<col class="bandeja-col-') === 10, 'La tabla debe definir sus diez columnas.');
$comprobar(strpos($css, 'table-layout: fixed') !== false, 'La tabla desktop debe respetar la distribución explícita.');
$comprobar(strpos($css, 'font-family: Arial, Helvetica, sans-serif') !== false, 'La bandeja debe usar tipografía proporcional legible.');
$comprobar(strpos($css, '.estado-etiqueta') !== false && strpos($css, 'white-space: nowrap') !== false, 'Las etiquetas de estado no deben partir palabras.');

preg_match_all('/\.bandeja-col-[a-z]+\s*\{\s*width:\s*([0-9.]+)%/', $css, $anchos);
$comprobar(count($anchos[1]) === 10, 'Cada columna debe tener un ancho porcentual explícito.');
$suma = array_sum(array_map('floatval', $anchos[1]));
$comprobar(abs($suma - 100.0) < 0.01, 'La distribución de columnas debe sumar 100%.');

$archivosEvidencia = [
    'antes-pagina-real.png',
    'despues-desktop-10-filas.png',
    'despues-mobile-10-filas.png',
    'estado-sin-contexto.png',
    'estado-sin-movimientos.png',
    'estado-cargando.png',
    'estado-error.png',
    'detalle-abierto.png',
    'correccion-antes-detalle.png',
    'correccion-antes-sticky.png',
    'correccion-barra-estatica.png',
    'correccion-detalle-scroll-bloqueado.png',
    'correccion-modo-oscuro.png',
    'correccion-footer-desktop.png',
    'correccion-footer-mobile.png',
    'correccion-anterior-habilitado.png',
];

$directorioEvidencia = __DIR__ . '/../docs/ux/evidence/task-6/';
foreach ($archivosEvidencia as $archivo) {
    $ruta = $directorioEvidencia . $archivo;
    $comprobar(is_file($ruta) && filesize($ruta) > 0, 'Falta la captura ' . $archivo . '.');
}

echo "OK: regresión visual, scroll y distribución de columnas validados.\n";
