<?php

require_once dirname(__DIR__) . '/src/config.php';
require_once RUTA . '/src/autoload.php';

use AppBancos\Infrastructure\EsquemaBancos;
use AppBancos\Application\ConsultarBandejaMensual;
use AppBancos\Application\CursorBandejaMensual;
use AppBancos\Repository\BandejaMensualRepository;

function comprobarBandeja($condicion, $mensaje)
{
    if (!$condicion) {
        throw new RuntimeException($mensaje);
    }
}

$pdo = new PDO(
    'pgsql:host=' . HOST . ';port=' . PORT . ';dbname=' . DBASE,
    USER,
    PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$repositorio = new BandejaMensualRepository(
    $pdo,
    EsquemaBancos::desdeConfiguracion(['bancos_debug' => true])
);

$cuentaFixture = $pdo->query(
    "SELECT cuenta_bancaria_zetti_id
     FROM global_temp.bancos_importacion_extracto
     WHERE version_origen = 'FIXTURE-015'"
)->fetchColumn();
comprobarBandeja($cuentaFixture !== false, 'No se encontró la importación fixture.');

$primeraPagina = $repositorio->listar($cuentaFixture, '2026-09-01', null, 2);
comprobarBandeja(count($primeraPagina) === 2, 'La primera página no devolvió dos movimientos.');
comprobarBandeja($primeraPagina[0]['referencia'] === 'DBG-001', 'El orden inicial no coincide con el fixture.');
comprobarBandeja($primeraPagina[1]['referencia'] === 'DBG-002', 'La primera página no respeta el orden por fecha e ID.');
comprobarBandeja($primeraPagina[0]['estado_codigo'] === 'ABIERTO', 'No se resolvió el estado legible de la importación.');

$cursor = [
    'fecha' => $primeraPagina[1]['fecha_operacion'],
    'id' => $primeraPagina[1]['id'],
];
$segundaPagina = $repositorio->listar($cuentaFixture, '2026-09-01', $cursor, 3);
comprobarBandeja(count($segundaPagina) === 3, 'El cursor no devolvió los tres movimientos restantes.');
comprobarBandeja($segundaPagina[0]['referencia'] === 'DBG-003', 'El cursor repitió u omitió movimientos.');
comprobarBandeja($segundaPagina[2]['referencia'] === 'DBG-005', 'La última fila de la segunda página no coincide.');

$casoDeUso = new ConsultarBandejaMensual(
    $repositorio,
    new CursorBandejaMensual('secreto-de-prueba-bandeja')
);
$respuesta = $casoDeUso->ejecutar([
    'cuenta_bancaria_id' => $cuentaFixture,
    'inicio_periodo' => '2026-09-01',
    'limite' => 2,
]);
comprobarBandeja(count($respuesta['movimientos']) === 2, 'El caso de uso no respetó el límite.');
comprobarBandeja(is_string($respuesta['siguiente_cursor']), 'No se entregó un cursor firmado para la segunda página.');
comprobarBandeja(strpos($respuesta['siguiente_cursor'], '.') !== false, 'El formato del cursor firmado es inválido.');

$respuestaSiguiente = $casoDeUso->ejecutar([
    'cuenta_bancaria_id' => $cuentaFixture,
    'inicio_periodo' => '2026-09-01',
    'limite' => 2,
    'cursor' => $respuesta['siguiente_cursor'],
]);
comprobarBandeja($respuestaSiguiente['movimientos'][0]['referencia'] === 'DBG-003', 'El cursor firmado no continuó la bandeja.');

try {
    $casoDeUso->ejecutar([
        'cuenta_bancaria_id' => (int) $cuentaFixture + 1,
        'inicio_periodo' => '2026-09-01',
        'cursor' => $respuesta['siguiente_cursor'],
    ]);
    throw new RuntimeException('Se aceptó un cursor para una cuenta distinta.');
} catch (InvalidArgumentException $esperada) {
}

$pagina = 'bandeja-mensual';
$_GET = [
    'cuenta_bancaria_id' => $cuentaFixture,
    'inicio_periodo' => '2026-09-01',
    'limite' => 2,
];
$datosPantalla = require RUTA . '/app/php/carga_inicial.php';
comprobarBandeja(
    count($datosPantalla->bandeja_mensual->resultado->movimientos) === 2,
    'La carga de la pantalla no expuso la primera página.'
);
comprobarBandeja(
    is_string($datosPantalla->bandeja_mensual->resultado->siguiente_cursor),
    'La carga de la pantalla no expuso el cursor firmado.'
);

echo "Bandeja mensual debug OK\n";
