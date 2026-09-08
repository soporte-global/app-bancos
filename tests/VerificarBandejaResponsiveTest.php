<?php
$vista = file_get_contents(__DIR__ . '/../app/html/bandeja-mensual.php');
$css = file_get_contents(__DIR__ . '/../app/css/shared.css');

$comprobar = static function ($condicion, $mensaje) {
    if (!$condicion) {
        fwrite(STDERR, "FALLO: {$mensaje}\n");
        exit(1);
    }
};

$comprobar(strpos($vista, '<style>') === false, 'La vista no debe contener estilos inline.');
$comprobar(strpos($vista, 'style=') === false, 'La vista no debe contener atributos style.');
$comprobar(strpos($vista, 'class="bandeja-table-region"') !== false, 'Falta la región responsive de resultados.');
$comprobar(substr_count($vista, 'data-label=') === 9, 'Cada una de las nueve celdas debe tener etiqueta para el modo card.');
$comprobar(strpos($css, '@media (max-width: 1100px)') !== false, 'Falta el breakpoint intermedio explícito.');
$comprobar(strpos($css, '@media (max-width: 768px)') !== false, 'Falta el breakpoint móvil explícito.');
$comprobar(strpos($css, '.bandeja-tabla td::before') !== false, 'Faltan etiquetas visibles en las cards móviles.');
$comprobar(!preg_match('/\b(?:min-|max-)?height\s*:\s*[^;]*vh\b/i', $css), 'La altura no debe depender de vh.');

ob_start();
include __DIR__ . '/fixtures/bandeja-responsive.php';
$html = ob_get_clean();

$comprobar(strpos($html, 'TRX-94821') !== false, 'El render no conserva la referencia del movimiento.');
$comprobar(strpos($html, 'data-label="Estado"') !== false, 'El render no conserva la etiqueta de estado para móvil.');
$comprobar(strpos($html, 'cursor-opaco-de-ejemplo') !== false, 'El render no conserva el enlace de paginación actual.');
$comprobar(substr_count($html, '<tr>') === 3, 'El render esperado debe contener cabecera y dos movimientos.');

echo "OK: bandeja responsive validada en estructura y render.\n";
