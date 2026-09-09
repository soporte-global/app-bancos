<?php
require_once dirname(__DIR__) . '/app/Application/CursorBandejaMensual.php';

use AppBancos\Application\CursorBandejaMensual;

$comprobar = static function ($condicion, $mensaje) {
    if (!$condicion) {
        throw new RuntimeException($mensaje);
    }
};

$cursores = new CursorBandejaMensual('secreto-de-prueba-bandeja');
$cursor = $cursores->codificar(
    ['id' => 20, 'fecha_operacion' => '2026-07-08'],
    103500000000515822,
    '2026-07-01',
    2,
    6,
    ['estado' => 'ABIERTO', 'mensajes' => 'SIN']
);
$datos = $cursores->decodificar(
    $cursor,
    103500000000515822,
    '2026-07-01',
    ['estado' => 'ABIERTO', 'mensajes' => 'SIN']
);

$comprobar($datos['fecha'] === '2026-07-08', 'El cursor no conservó la fecha.');
$comprobar($datos['id'] === 20, 'El cursor no conservó el identificador.');
$comprobar($datos['pagina'] === 2, 'El cursor no conservó la página firmada.');
$comprobar($datos['inicio'] === 6, 'El cursor no conservó el inicio firmado.');

try {
    $cursores->decodificar(
        $cursor,
        103500000000515822,
        '2026-07-01',
        ['estado' => 'CERRADO', 'mensajes' => 'SIN']
    );
    throw new RuntimeException('Se aceptó un cursor para filtros distintos.');
} catch (InvalidArgumentException $esperada) {
}

echo "Cursor de bandeja OK\n";
