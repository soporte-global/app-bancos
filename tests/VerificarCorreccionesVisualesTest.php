<?php
$vista = file_get_contents(__DIR__ . '/../app/html/bandeja-mensual.php');
$css = file_get_contents(__DIR__ . '/../app/css/bancos.css');
$temaClaro = file_get_contents(__DIR__ . '/../app/css/tema_claro.css');
$temaOscuro = file_get_contents(__DIR__ . '/../app/css/tema_oscuro.css');
$temaComponentes = file_get_contents(__DIR__ . '/../app/css/tema_componentes.css');
$javascript = file_get_contents(__DIR__ . '/../app/js/shared.js');
$paleta = file_get_contents(__DIR__ . '/../_shared/css/paleta_colores.css');
$compatibilidad = file_get_contents(__DIR__ . '/../_shared/css/compatibilidad_colores.css');
$header = file_get_contents(__DIR__ . '/../_shared/css/header.css');
$configuracion = require __DIR__ . '/../app/config.php';

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
$comprobar(strpos($temaClaro, '--ui-error-texto: var(--color-secundario1)') === false
    && strpos($temaOscuro, '--ui-error-texto: var(--color-secundario2)') === false,
    'Las acciones de error deben conservar contraste en ambos temas.');
$comprobar(strpos($temaComponentes, 'var(--ui-superficie)') !== false, 'La apariencia debe consumir tokens semánticos.');
$comprobar(strpos($vista, 'data-alternar-tema') === false, 'La bandeja no debe tener una segunda autoridad de tema.');
$comprobar(strpos($javascript, 'localStorage') === false, 'La preferencia de tema ya no pertenece al JavaScript de la bandeja.');
$comprobar(strpos($javascript, 'bandeja-tema-oscuro') === false, 'La bandeja no debe mutar el tema global.');
$comprobar(strpos($css, 'html.bandeja-detalle-activo') !== false, 'Falta el bloqueo de scroll al abrir detalle.');
$comprobar(strpos($css, 'overscroll-behavior: contain') !== false, 'El panel no debe propagar su scroll a la página.');
$comprobar(strpos($vista, 'data-controles-bandeja') === false, 'El formulario debe permanecer en su landmark original.');
$comprobar(strpos($javascript, 'footer.appendChild(controles)') === false, 'El formulario no debe moverse al footer.');
$comprobar(strpos($css, '.bancos-app') !== false, 'Los estilos propios deben quedar encapsulados.');
$comprobar(strpos($vista, '<header class="bandeja-encabezado">') !== false, 'La bandeja debe agrupar su título en una franja de ancho completo.');
$comprobar(strpos($css, 'padding: 0 0 48px') !== false && strpos($css, '--seccion-gutter:') !== false, 'Las franjas deben ocupar el viewport y aplicar espacio sólo a su contenido.');
$comprobar(strpos($css, '.bancos-app .importacion-encabezado') !== false && strpos($css, '.bancos-app .configuracion-encabezado') !== false, 'Los encabezados de importación y configuración deben conservar padding frente al shell compartido.');
$comprobar(strpos($temaClaro, '--ui-superficie: var(--color-secundario1)') !== false, 'El tema claro debe ofrecer una superficie blanca que contraste con el fondo.');
$comprobar(strpos($temaComponentes, '.bandeja-detalle-columnas') !== false && strpos($temaComponentes, 'background: var(--ui-superficie)') !== false, 'El detalle debe mantener sus zonas sobre una superficie diferenciada.');
$comprobar(strpos($temaComponentes, '.bandeja-detalle-operaciones > .bandeja-detalle-seccion:nth-of-type(odd)') !== false
    && strpos($temaComponentes, '.bandeja-detalle-seguimiento > .bandeja-detalle-seccion:nth-of-type(even)') !== false,
    'Las secciones del detalle deben alternar superficies sin volver a tarjetas.');
$comprobar(strpos($temaComponentes, '.bandeja-detalle-operaciones > .bandeja-detalle-seccion[data-accion-prevalidar-cierre]') !== false
    && strpos($temaComponentes, 'background: var(--ui-advertencia-fondo)') !== false,
    'Las acciones de cierre deben distinguirse con la paleta de atención.');
$comprobar(strpos($temaComponentes, '.bandeja-detalle .estado-etiqueta--cerrado') !== false
    && strpos($temaComponentes, '.bandeja-detalle .conciliacion-etiqueta--conciliado') !== false
    && strpos($temaComponentes, '.bandeja-detalle .bandeja-preflight-resultado.es-correcto') !== false,
    'Los indicadores positivos del detalle no deben usar verde.');
$comprobar(strpos($temaComponentes, '.configuracion-mapeos') !== false
    && strpos($temaComponentes, '.configuracion-asignaciones') !== false
    && strpos($temaComponentes, 'background: color-mix(in srgb, var(--color-secundario3) 12%, var(--ui-superficie))') !== false
    && strpos($temaComponentes, 'background: color-mix(in srgb, var(--color-principal2) 16%, var(--ui-superficie))') !== false,
    'Cuentas, mapeos y responsables deben distinguirse dentro de la paleta compartida.');
$comprobar(strpos($css, '.bandeja-lineas tbody tr:last-child td') !== false
    && strpos($temaComponentes, '.bandeja-lineas tbody tr:nth-child(even)') !== false
    && strpos($temaComponentes, '.bandeja-lineas th {') !== false
    && strpos($temaComponentes, 'background: var(--ui-tabla-cabecera)') !== false,
    'Las tablas internas deben compartir cabecera y filas alternadas con la bandeja mensual.');
$comprobar(preg_match('/\.bancos-app header\s*\{([^}]*)\}/s', $css, $encabezadosInternos) === 1, 'Los encabezados internos deben quedar aislados del header fijo compartido.');
$comprobar(strpos($encabezadosInternos[1], 'position: static') !== false && strpos($encabezadosInternos[1], 'box-shadow: none') !== false, 'Los encabezados internos deben permanecer en el flujo de la pagina y sin la sombra del shell.');
$comprobar(strpos($css, '.bancos-app header::before') !== false, 'Los encabezados internos no deben heredar la franja decorativa del shell.');
$comprobar(strpos($css, '.importacion-formulario > *') !== false && strpos($css, 'min-width: 0') !== false, 'Los formularios operativos no deben forzar desborde horizontal.');
$comprobar(strpos($css, '.configuracion-selector > *') !== false, 'El selector de configuracion debe poder contraerse en pantallas angostas.');
$comprobar(preg_match('/\.bandeja-shell\s*\{([^}]*)\}/s', $css, $anchoBandeja) === 1
    && strpos($anchoBandeja[1], 'width: 100%') !== false
    && strpos($anchoBandeja[1], 'max-width') === false,
    'La bandeja debe usar todo el ancho disponible.');
foreach (['importacion-page', 'configuracion-page'] as $clasePagina) {
    $comprobar(preg_match('/\.' . $clasePagina . '\s*\{([^}]*)\}/s', $css, $anchoPagina) === 1
        && strpos($anchoPagina[1], 'width: 100%') !== false
        && strpos($anchoPagina[1], 'max-width') === false,
        'La vista ' . $clasePagina . ' no debe limitarse a una columna central.');
}
$comprobar(strpos($css, '.bancos-app select,') !== false
    && strpos($css, '.bancos-app textarea {') !== false
    && strpos($css, 'min-width: 0') !== false,
    'Los controles de la aplicación deben ajustarse al ancho de sus celdas.');
$comprobar(strpos(file_get_contents(__DIR__ . '/../app/html/importaciones.php'), '<header class="importacion-encabezado">') !== false, 'La importacion debe conservar un encabezado semantico visible.');
$comprobar(strpos(file_get_contents(__DIR__ . '/../app/html/configuraciones.php'), '<header class="configuracion-encabezado">') !== false, 'La configuracion debe conservar un encabezado semantico visible.');
$comprobar(strpos($header, '.dropdown-menu .selector_tema') !== false, 'El selector de tema debe ocupar una fila propia dentro del menú.');
$comprobar(strpos($header, 'justify-content: space-between') !== false, 'El texto y el switch deben quedar alineados dentro de la fila.');
$comprobar($configuracion['nombre'] === 'APP BANCOS', 'La identidad visible de la aplicación debe ser APP BANCOS.');

preg_match('/\.bandeja-contexto\s*\{([^}]*)\}/s', $css, $contexto);
$comprobar(!isset($contexto[1]) || strpos($contexto[1], 'position: sticky') === false, 'La barra no debe permanecer sticky.');

echo "OK: sistema visual RRHH, tema compartido y composición continua validados.\n";
