<?php
$vista = file_get_contents(__DIR__ . '/../app/html/bandeja-mensual.php');
$css = file_get_contents(__DIR__ . '/../app/css/bancos.css');

$comprobar = static function ($condicion, $mensaje) {
    if (!$condicion) {
        fwrite(STDERR, "FALLO: {$mensaje}\n");
        exit(1);
    }
};

$comprobar(strpos($vista, '<style>') === false, 'La vista no debe contener estilos inline.');
$comprobar(strpos($vista, 'style=') === false, 'La vista no debe contener atributos style.');
$comprobar(strpos($vista, 'class="bandeja-table-region"') !== false, 'Falta la región responsive de resultados.');
$comprobar(substr_count($vista, 'data-label=') === 10, 'Cada una de las diez celdas debe tener etiqueta para el modo card.');
$comprobar(strpos($css, '@media (max-width: 900px)') !== false, 'Falta el breakpoint intermedio de la referencia RRHH.');
$comprobar(strpos($css, '@media (max-width: 650px)') !== false, 'Falta el breakpoint móvil de la referencia RRHH.');
$comprobar(strpos($css, '.bandeja-tabla td::before') !== false, 'Faltan etiquetas visibles en las cards móviles.');
preg_match('/\.bandeja-shell\s*\{([^}]*)\}/s', $css, $shell);
$comprobar(!isset($shell[1]) || !preg_match('/\b(?:min-|max-)?height\s*:\s*[^;]*vh\b/i', $shell[1]), 'El contenido de la bandeja no debe fijar su altura al viewport.');
$comprobar(strpos($css, '.bandeja-detalle-fondo') !== false && strpos($css, 'height: 100dvh') !== false, 'El overlay del detalle sí debe cubrir el viewport dinámico.');

ob_start();
include __DIR__ . '/fixtures/bandeja-responsive.php';
$html = ob_get_clean();

$comprobar(strpos($html, 'TRX-94821') !== false, 'El render no conserva la referencia del movimiento.');
$comprobar(strpos($html, 'data-label="Estado"') !== false, 'El render no conserva la etiqueta de estado para móvil.');
$comprobar(strpos($html, 'cursor-opaco-de-ejemplo') !== false, 'El render no conserva el enlace de paginación actual.');
$comprobar(substr_count($html, 'class="bandeja-ver-detalle"') === 2, 'El render esperado debe contener dos movimientos.');

echo "OK: bandeja responsive validada en estructura y render.\n";
