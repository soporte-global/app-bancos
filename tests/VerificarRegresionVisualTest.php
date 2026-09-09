<?php
$vista = file_get_contents(__DIR__ . '/../app/html/bandeja-mensual.php');
$css = file_get_contents(__DIR__ . '/../app/css/bancos.css');
$temaClaro = file_get_contents(__DIR__ . '/../app/css/tema_claro.css');
$temaOscuro = file_get_contents(__DIR__ . '/../app/css/tema_oscuro.css');
$javascript = file_get_contents(__DIR__ . '/../app/js/shared.js');

$comprobar = static function ($condicion, $mensaje) {
    if (!$condicion) {
        fwrite(STDERR, "FALLO: {$mensaje}\n");
        exit(1);
    }
};

$comprobar(strpos($vista, '<main class="afterheader bandeja-page">') !== false, 'La bandeja debe identificar su shell para liberar el scroll heredado.');
$comprobar(strpos($css, '.afterheader.bandeja-page') !== false && strpos($css, 'overflow: visible') !== false, 'El contenedor debe permitir crecimiento vertical.');
$comprobar(strpos($css, 'html.bandeja-scroll') === false && strpos($css, 'body.bandeja-scroll') === false, 'El scroll no debe depender de clases mutadas por JavaScript.');
$comprobar(strpos($javascript, "classList.add('bandeja-scroll')") === false, 'La bandeja no debe mutar el scroll global al iniciar.');
$comprobar(substr_count($vista, '<col class="bandeja-col-') === 10, 'La tabla debe definir sus diez columnas.');
$comprobar(strpos($css, 'table-layout: fixed') !== false, 'La tabla desktop debe respetar la distribución explícita.');
$comprobar(strpos($css, 'font-family: inherit') !== false, 'La bandeja debe heredar Roboto Mono del shell compartido.');
$comprobar(strpos($css, '.estado-etiqueta') !== false && strpos($css, 'white-space: nowrap') !== false, 'Las etiquetas de estado no deben partir palabras.');
$comprobar(strpos($css, 'box-shadow: 0 4px 16px') === false, 'Los paneles de contenido no deben usar elevación decorativa.');
$comprobar(strpos($temaClaro, 'color-scheme: light') !== false, 'Falta el tema claro.');
$comprobar(strpos($temaOscuro, 'color-scheme: dark') !== false, 'Falta el tema oscuro.');

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
