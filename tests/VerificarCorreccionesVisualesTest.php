<?php
$vista = file_get_contents(__DIR__ . '/../app/html/bandeja-mensual.php');
$css = file_get_contents(__DIR__ . '/../app/css/bancos.css');
$temaClaro = file_get_contents(__DIR__ . '/../app/css/tema_claro.css');
$temaOscuro = file_get_contents(__DIR__ . '/../app/css/tema_oscuro.css');
$temaComponentes = file_get_contents(__DIR__ . '/../app/css/tema_componentes.css');
$javascript = file_get_contents(__DIR__ . '/../app/js/shared.js');
$paleta = file_get_contents(__DIR__ . '/../_shared/css/paleta_colores.css');
$compatibilidad = file_get_contents(__DIR__ . '/../_shared/css/compatibilidad_colores.css');

$comprobar = static function ($condicion, $mensaje) {
    if (!$condicion) {
        fwrite(STDERR, "FALLO: {$mensaje}\n");
        exit(1);
    }
};

$comprobar(!preg_match('/#[0-9a-f]{3,8}\b|rgba?\(|hsla?\(/i', $css), 'El CSS estructural no debe contener colores literales.');
$comprobar(strpos($paleta, '--paleta-principal1') !== false, 'Faltan las bases de la paleta compartida.');
$comprobar(strpos($paleta, '@import url("compatibilidad_colores.css")') !== false, 'La paleta debe cargar sus alias históricos.');
$comprobar(strpos($compatibilidad, '--color-principal-500: var(--paleta-principal1)') !== false, 'Los alias históricos deben derivar de la paleta.');
$comprobar(strpos($temaClaro, '--color-secundario1: #ffffff') !== false, 'Faltan las bases del tema claro.');
$comprobar(strpos($temaOscuro, 'color-scheme: dark') !== false, 'Falta el tema oscuro real.');
$comprobar(strpos($temaComponentes, 'var(--ui-superficie)') !== false, 'La apariencia debe consumir tokens semánticos.');
$comprobar(strpos($vista, 'data-alternar-tema') === false, 'La bandeja no debe tener una segunda autoridad de tema.');
$comprobar(strpos($javascript, 'localStorage') === false, 'La preferencia de tema ya no pertenece al JavaScript de la bandeja.');
$comprobar(strpos($javascript, 'bandeja-tema-oscuro') === false, 'La bandeja no debe mutar el tema global.');
$comprobar(strpos($css, 'html.bandeja-detalle-activo') !== false, 'Falta el bloqueo de scroll al abrir detalle.');
$comprobar(strpos($css, 'overscroll-behavior: contain') !== false, 'El panel no debe propagar su scroll a la página.');
$comprobar(strpos($vista, 'data-controles-bandeja') === false, 'El formulario debe permanecer en su landmark original.');
$comprobar(strpos($javascript, 'footer.appendChild(controles)') === false, 'El formulario no debe moverse al footer.');
$comprobar(strpos($css, '.bancos-app') !== false, 'Los estilos propios deben quedar encapsulados.');

preg_match('/\.bandeja-contexto\s*\{([^}]*)\}/s', $css, $contexto);
$comprobar(!isset($contexto[1]) || strpos($contexto[1], 'position: sticky') === false, 'La barra no debe permanecer sticky.');

echo "OK: sistema visual RRHH, tema compartido y composición continua validados.\n";
