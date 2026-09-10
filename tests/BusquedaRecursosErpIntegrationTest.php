<?php

require_once dirname(__DIR__) . '/src/config.php';
require_once RUTA . '/src/autoload.php';

use AppBancos\Application\BuscarRecursosErpMovimientoMensual;
use AppBancos\Application\MovimientoNoEncontradoException;
use AppBancos\Infrastructure\EsquemaBancos;
use AppBancos\Repository\BusquedaRecursosErpRepository;

function comprobarBusquedaRecursos($condicion, $mensaje)
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
$movimiento = $pdo->query(
    "SELECT m.id, i.cuenta_bancaria_zetti_id, i.inicio_periodo,
            CASE WHEN m.credito > 0 THEN m.credito ELSE m.debito END monto
     FROM global_temp.bancos_movimiento_extracto m
     JOIN global_temp.bancos_importacion_extracto i ON i.id = m.importacion_id
     JOIN global_temp.bancos_estado e ON e.id = i.estado_id
     WHERE i.version_origen = 'FIXTURE-015' AND e.codigo = 'ABIERTO'
     ORDER BY m.id LIMIT 1"
)->fetch(PDO::FETCH_ASSOC);
comprobarBusquedaRecursos($movimiento !== false, 'Falta un movimiento ABIERTO de fixture.');

$antes = [
    'asociaciones' => (int) $pdo->query('SELECT count(*) FROM global_temp.bancos_asociacion_movimiento')->fetchColumn(),
    'reservas' => (int) $pdo->query('SELECT count(*) FROM global_temp.bancos_reserva_recurso')->fetchColumn(),
];
$casoDeUso = new BuscarRecursosErpMovimientoMensual(
    new BusquedaRecursosErpRepository(
        $pdo,
        EsquemaBancos::desdeConfiguracion(['bancos_debug' => true])
    )
);
$contexto = [
    'movimiento_id' => (int) $movimiento['id'],
    'cuenta_bancaria_id' => (string) $movimiento['cuenta_bancaria_zetti_id'],
    'inicio_periodo' => $movimiento['inicio_periodo'],
    'limite' => 5,
];

$valores = $casoDeUso->buscarValores($contexto);
comprobarBusquedaRecursos(count($valores['resultados']) > 0, 'La búsqueda contextual no devolvió valores.');
comprobarBusquedaRecursos(count($valores['resultados']) <= 5, 'La búsqueda de valores excedió el límite.');
foreach ($valores['resultados'] as $valor) {
    comprobarBusquedaRecursos(
        !in_array((int) $valor['estado_id'], [7, 20, 36], true),
        'La búsqueda devolvió un valor en estado no asociable.'
    );
    comprobarBusquedaRecursos(
        (float) $valor['monto_principal'] >= (float) $movimiento['monto'],
        'La búsqueda devolvió un valor con monto insuficiente.'
    );
}

$compartidos = $casoDeUso->buscarAsientos($contexto + ['compartido' => '1']);
comprobarBusquedaRecursos(count($compartidos['resultados']) > 0, 'La búsqueda no devolvió asientos compartibles.');
comprobarBusquedaRecursos(count($compartidos['resultados']) <= 5, 'La búsqueda de asientos excedió el límite.');
foreach ($compartidos['resultados'] as $asiento) {
    comprobarBusquedaRecursos((int) $asiento['cantidad_lineas'] >= 2, 'Se devolvió un asiento sin estructura suficiente.');
    comprobarBusquedaRecursos($asiento['total_debe'] === $asiento['total_haber'], 'Se devolvió un asiento desbalanceado.');
    comprobarBusquedaRecursos(
        !in_array($asiento['tiene_uso_exclusivo'], [true, 1, '1', 't'], true),
        'Se devolvió un asiento compartido con uso exclusivo.'
    );
}

$exclusivos = $casoDeUso->buscarAsientos($contexto + ['compartido' => '0']);
comprobarBusquedaRecursos(count($exclusivos['resultados']) <= 5, 'La búsqueda exclusiva excedió el límite.');
foreach ($exclusivos['resultados'] as $asiento) {
    comprobarBusquedaRecursos($asiento['importe_maximo'] === $movimiento['monto'], 'El asiento exclusivo no coincide en importe.');
    comprobarBusquedaRecursos(
        !in_array($asiento['tiene_usos'], [true, 1, '1', 't'], true),
        'Se devolvió un asiento exclusivo ya utilizado.'
    );
}

$nodos = $casoDeUso->buscarNodos($contexto + ['busqueda' => '']);
comprobarBusquedaRecursos(count($nodos['resultados']) > 0, 'La búsqueda no devolvió nodos ERP.');
comprobarBusquedaRecursos(count($nodos['resultados']) <= 5, 'La búsqueda de nodos excedió el límite.');
$nodo = $nodos['resultados'][0];
comprobarBusquedaRecursos(
    preg_match('/^[1-9][0-9]*$/', (string) $nodo['id']) === 1,
    'La búsqueda de nodos no preservó el identificador.'
);

$cuentaMuestra = $pdo->query(
    "SELECT id::text AS id, codigo
     FROM public.cuenta
     WHERE length(trim(COALESCE(codigo, ''))) >= 2
     ORDER BY id DESC
     LIMIT 1"
)->fetch(PDO::FETCH_ASSOC);
comprobarBusquedaRecursos($cuentaMuestra !== false, 'Falta una cuenta ERP para probar la búsqueda.');
$cuentas = $casoDeUso->buscarCuentas($contexto + [
    'nodo_zetti_id' => (int) $nodo['id'],
    'busqueda' => $cuentaMuestra['codigo'],
]);
comprobarBusquedaRecursos(count($cuentas['resultados']) > 0, 'La búsqueda no devolvió cuentas ERP.');
comprobarBusquedaRecursos(count($cuentas['resultados']) <= 5, 'La búsqueda de cuentas excedió el límite.');
foreach ($cuentas['resultados'] as $cuenta) {
    comprobarBusquedaRecursos(
        preg_match('/^[1-9][0-9]*$/', (string) $cuenta['id']) === 1,
        'La búsqueda de cuentas no preservó el identificador.'
    );
    comprobarBusquedaRecursos(
        array_key_exists('nodo', $cuenta) && array_key_exists('imputable', $cuenta),
        'La cuenta no informa nodo e imputabilidad.'
    );
}

try {
    $casoDeUso->buscarCuentas($contexto + [
        'nodo_zetti_id' => (int) $nodo['id'],
        'busqueda' => 'x',
    ]);
    throw new RuntimeException('La búsqueda aceptó un término de cuenta demasiado corto.');
} catch (InvalidArgumentException $esperada) {
}

try {
    $casoDeUso->buscarValores(array_merge($contexto, ['movimiento_id' => 9223372036854775807]));
    throw new RuntimeException('La búsqueda aceptó un movimiento fuera del contexto.');
} catch (MovimientoNoEncontradoException $esperada) {
}

$despues = [
    'asociaciones' => (int) $pdo->query('SELECT count(*) FROM global_temp.bancos_asociacion_movimiento')->fetchColumn(),
    'reservas' => (int) $pdo->query('SELECT count(*) FROM global_temp.bancos_reserva_recurso')->fetchColumn(),
];
comprobarBusquedaRecursos($antes === $despues, 'La búsqueda modificó datos operativos.');

echo "Busqueda asistida de recursos ERP OK\n";
