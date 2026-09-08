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

$luminancia = static function ($hex) {
    $canales = [hexdec(substr($hex, 1, 2)), hexdec(substr($hex, 3, 2)), hexdec(substr($hex, 5, 2))];
    $canales = array_map(static function ($canal) {
        $valor = $canal / 255;
        return $valor <= 0.03928 ? $valor / 12.92 : pow(($valor + 0.055) / 1.055, 2.4);
    }, $canales);
    return 0.2126 * $canales[0] + 0.7152 * $canales[1] + 0.0722 * $canales[2];
};

$contraste = static function ($frente, $fondo) use ($luminancia) {
    $a = $luminancia($frente);
    $b = $luminancia($fondo);
    return (max($a, $b) + 0.05) / (min($a, $b) + 0.05);
};

$comprobar(strpos($vista, 'data-seleccionar-contexto') !== false, 'Falta el CTA del estado sin cuenta/período.');
$comprobar(strpos($vista, 'No hay movimientos para este contexto') !== false, 'Falta el estado contextual sin movimientos.');
$comprobar(strpos($vista, 'data-estado-cargando hidden') !== false, 'Falta el indicador de carga localizado.');
$comprobar(strpos($vista, 'data-reintentar-consulta') !== false, 'Falta la acción de reintento del error.');
$comprobar(strpos($vista, 'data-contenido-bandeja') !== false, 'Los estados deben permanecer dentro de la bandeja.');
$comprobar(strpos($vista, 'estado-etiqueta--') !== false, 'El estado de cada movimiento debe tener etiqueta textual.');
$comprobar(strpos($javascript, 'formulario.requestSubmit') !== false, 'Reintentar debe reutilizar la consulta preservada.');
$comprobar(strpos($css, '.bandeja-cargando') !== false && strpos($css, 'position: fixed') === false, 'La carga no debe bloquear la pantalla completa.');
$comprobar(strpos($css, '@media (prefers-reduced-motion: reduce)') !== false, 'Las animaciones deben respetar movimiento reducido.');

$paresAA = [
    ['#154f83', '#e8f1fb', 'ABIERTO'],
    ['#6a4500', '#fff3cd', 'PARA_CERRAR'],
    ['#155b38', '#e7f4ed', 'CERRADO'],
    ['#34465b', '#eef1f5', 'estado genérico'],
    ['#ffffff', '#0b5cad', 'acción primaria'],
];
foreach ($paresAA as $par) {
    $comprobar($contraste($par[0], $par[1]) >= 4.5, 'El contraste de ' . $par[2] . ' debe alcanzar AA.');
}

$_GET = [];
ob_start();
include __DIR__ . '/fixtures/bandeja-responsive.php';
$html = ob_get_clean();
$comprobar(strpos($html, '>ABIERTO</span>') !== false, 'El estado ABIERTO debe conservar texto visible.');
$comprobar(strpos($html, '>PARA_CERRAR</span>') !== false, 'El estado PARA_CERRAR debe conservar texto visible.');

echo "OK: estados vacío, carga, error y contraste AA validados.\n";
