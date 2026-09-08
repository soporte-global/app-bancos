<?php
$vista = file_get_contents(__DIR__ . '/../app/html/bandeja-mensual.php');
$css = file_get_contents(__DIR__ . '/../app/css/shared.css');
$javascript = file_get_contents(__DIR__ . '/../app/js/shared.js');
$paleta = file_get_contents(__DIR__ . '/../_shared/css/paleta_colores.css');

$comprobar = static function ($condicion, $mensaje) {
    if (!$condicion) {
        fwrite(STDERR, "FALLO: {$mensaje}\n");
        exit(1);
    }
};

$comprobar(!preg_match('/#[0-9a-f]{3,8}\b|rgba?\(|hsla?\(/i', $css), 'Los colores literales no están permitidos fuera de paleta_colores.css.');
preg_match_all('/(--[a-z0-9-]+)\s*:/i', $paleta, $variablesPaleta);
preg_match_all('/--bandeja-[a-z0-9-]+\s*:\s*var\((--[a-z0-9-]+)\)/i', $css, $origenesSemanticos);
$comprobar(count($origenesSemanticos[1]) > 0, 'Faltan tokens semánticos de la bandeja.');
foreach ($origenesSemanticos[1] as $origen) {
    $comprobar(in_array($origen, $variablesPaleta[1], true), 'El token ' . $origen . ' no pertenece a paleta_colores.css.');
}
$comprobar(strpos($vista, 'data-alternar-tema') !== false, 'Falta la opción de modo oscuro.');
$comprobar(strpos($css, '.bandeja-mensual[data-tema="oscuro"]') !== false, 'Faltan los tokens del modo oscuro.');
$comprobar(strpos($javascript, "window.localStorage.setItem(clave, tema)") !== false, 'La preferencia de tema debe persistirse.');
$comprobar(strpos($javascript, "document.body.classList.add('bandeja-tema-oscuro')") !== false, 'El fondo de página debe acompañar el modo oscuro.');
$comprobar(strpos($css, 'html.bandeja-detalle-activo') !== false, 'Falta el bloqueo de scroll al abrir detalle.');
$comprobar(strpos($css, 'overscroll-behavior: contain') !== false, 'El panel no debe propagar su scroll a la página.');
$comprobar(strpos($vista, 'data-controles-bandeja') !== false, 'El formulario debe identificar el bloque movible al footer.');
$comprobar(strpos($javascript, "document.querySelector('header.footer')") !== false, 'Debe reutilizarse el footer de la estructura.');
$comprobar(strpos($javascript, 'footer.appendChild(controles)') !== false, 'El bloque de consulta debe moverse al footer existente.');
$comprobar(strpos($javascript, "footer.style.removeProperty('display')") !== false, 'La bandeja debe volver visible el footer ocultado por el script compartido.');
$comprobar(strpos($css, 'header.footer.bandeja-footer') !== false, 'Faltan estilos acotados para el footer de bandeja.');
$comprobar(strpos($css, '.bandeja-footer *') !== false && strpos($css, 'box-sizing: border-box') !== false, 'El footer debe contener sus controles sin overflow horizontal.');

preg_match('/\.bandeja-contexto\s*\{([^}]*)\}/s', $css, $contexto);
$comprobar(isset($contexto[1]) && strpos($contexto[1], 'position: static') !== false, 'La barra de contexto debe ser estática.');
$comprobar(!isset($contexto[1]) || strpos($contexto[1], 'position: sticky') === false, 'La barra no debe permanecer sticky.');

echo "OK: scroll de detalle, barra estática, modo oscuro, footer y paleta validados.\n";
