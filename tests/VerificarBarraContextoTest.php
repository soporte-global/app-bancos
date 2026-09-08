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

$comprobar(strpos($vista, 'aria-label="Contexto de la bandeja"') !== false, 'Falta la barra semántica de contexto.');
$comprobar(strpos($vista, 'data-contexto-cuenta') !== false, 'Falta la cuenta en el contexto.');
$comprobar(strpos($vista, 'data-contexto-periodo') !== false, 'Falta el período en el contexto.');
$comprobar(strpos($vista, 'data-contexto-estado') !== false, 'Falta el estado de carga localizado.');
$comprobar(strpos($vista, 'Filtros activos: ninguno') !== false, 'Falta el resumen de filtros activos.');
$comprobar(
    preg_match('/\.bandeja-contexto\s*\{([^}]*)\}/s', $css, $reglaContexto) === 1
    && strpos($reglaContexto[1], 'position: static') !== false,
    'La barra de contexto no debe ocultar información durante el scroll.'
);
$comprobar(strpos($javascript, "cuenta.addEventListener('input'") !== false, 'La cuenta no actualiza el contexto localmente.');
$comprobar(strpos($javascript, "periodo.addEventListener('input'") !== false, 'El período no actualiza el contexto localmente.');
$comprobar(!preg_match('/window\.location(?:\.(?:assign|replace|reload))?\s*[=(]/', $javascript), 'La actualización local no debe forzar navegación global.');

ob_start();
include __DIR__ . '/fixtures/bandeja-responsive.php';
$html = ob_get_clean();

$comprobar(strpos($html, '>Extractos<') !== false, 'La miga debe comenzar en Extractos.');
$comprobar(strpos($html, '>1042<') !== false, 'El contexto debe mostrar la cuenta actual.');
$comprobar(strpos($html, 'julio de 2026') !== false, 'El período debe presentarse en texto legible.');
$comprobar(strpos($html, '2 movimientos cargados') !== false, 'El contexto debe presentar el estado de carga actual.');

echo "OK: barra de contexto validada en estructura, estado y actualización local.\n";
