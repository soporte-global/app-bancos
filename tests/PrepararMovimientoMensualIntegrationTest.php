<?php

require_once dirname(__DIR__) . '/src/config.php';
require_once RUTA . '/src/autoload.php';

use AppBancos\Application\EjecutorComandoIdempotente;
use AppBancos\Application\MovimientoNoEncontradoException;
use AppBancos\Application\PrepararMovimientoMensual;
use AppBancos\Application\TransicionMovimientoException;
use AppBancos\Infrastructure\EsquemaBancos;
use AppBancos\Repository\AuditoriaRepository;
use AppBancos\Repository\IdempotenciaRepository;
use AppBancos\Repository\MovimientoMensualRepository;

function comprobarPreparacion($condicion, $mensaje)
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
$usuarioId = (int) $pdo->query(
    "SELECT id FROM global_prod.rrhh_login
     WHERE lower(usuario) = 'hvega' AND habilitado IS TRUE AND fecha_eliminacion IS NULL"
)->fetchColumn();
$importacion = $pdo->query(
    "SELECT i.id, m.id_periodo, i.cuenta_bancaria_zetti_id, i.inicio_periodo
     FROM global_temp.bancos_importacion_extracto i
     JOIN global_temp.bancos_movimiento_extracto m ON m.importacion_id = i.id
     WHERE i.version_origen = 'FIXTURE-015'
     ORDER BY i.id, m.id LIMIT 1"
)->fetch(PDO::FETCH_ASSOC);
comprobarPreparacion($usuarioId > 0 && $importacion !== false, 'Faltan los datos base para probar la preparacion.');

$marca = bin2hex(random_bytes(8));
$clave = 'test-preparar-' . $marca;
$claveInvalida = 'test-contexto-' . $marca;
$numeroFila = random_int(1000000, 1900000000);
$insertar = $pdo->prepare(
    "INSERT INTO global_temp.bancos_movimiento_extracto
        (importacion_id, id_periodo, serial_seq, numero_fila_origen, fecha_operacion,
         referencia, descripcion, credito, debito, usuario_creacion, usuario_modificacion)
     VALUES
        (:importacion_id, :id_periodo, :serial_seq, :numero_fila, :fecha,
         :referencia, 'Prueba aislada de preparacion', 123.45, 0, :usuario_id, :usuario_id)
     RETURNING id"
);
$insertar->execute([
    ':importacion_id' => $importacion['id'],
    ':id_periodo' => $importacion['id_periodo'],
    ':serial_seq' => $numeroFila,
    ':numero_fila' => $numeroFila,
    ':fecha' => $importacion['inicio_periodo'],
    ':referencia' => 'TEST-PREPARAR-' . $marca,
    ':usuario_id' => $usuarioId,
]);
$movimientoId = (int) $insertar->fetchColumn();
$esquema = EsquemaBancos::desdeConfiguracion(['bancos_debug' => true]);
$casoDeUso = new PrepararMovimientoMensual(
    new MovimientoMensualRepository($pdo, $esquema),
    new EjecutorComandoIdempotente(
        $pdo,
        new IdempotenciaRepository($pdo, $esquema),
        new AuditoriaRepository($pdo, $esquema)
    )
);
$entrada = [
    'movimiento_id' => $movimientoId,
    'cuenta_bancaria_id' => (string) $importacion['cuenta_bancaria_zetti_id'],
    'inicio_periodo' => $importacion['inicio_periodo'],
];

try {
    $primera = $casoDeUso->ejecutar($entrada, $usuarioId, $clave);
    $segunda = $casoDeUso->ejecutar($entrada, $usuarioId, $clave);
    comprobarPreparacion($primera['repetida'] === false, 'La primera preparacion fue marcada como repetida.');
    comprobarPreparacion($segunda['repetida'] === true, 'El reintento no fue reconocido.');
    comprobarPreparacion($primera['respuesta']['estado'] === 'PARA_CERRAR', 'La transicion no devolvio el estado esperado.');

    $consulta = $pdo->prepare(
        "SELECT e.codigo, h.usuario_hub_id, h.observacion
         FROM global_temp.bancos_historial_asignacion h
         JOIN global_temp.bancos_estado e ON e.id = h.estado_id
         WHERE h.movimiento_id = :movimiento_id
         ORDER BY h.registrado_en DESC, h.id DESC"
    );
    $consulta->execute([':movimiento_id' => $movimientoId]);
    $historial = $consulta->fetchAll(PDO::FETCH_ASSOC);
    comprobarPreparacion(count($historial) === 1, 'La preparacion repetida creo mas de un evento.');
    comprobarPreparacion($historial[0]['codigo'] === 'PARA_CERRAR', 'El historial no registro PARA_CERRAR.');
    comprobarPreparacion((int) $historial[0]['usuario_hub_id'] === $usuarioId, 'El historial perdio al operador real.');

    try {
        $casoDeUso->ejecutar($entrada, $usuarioId, 'test-segunda-' . $marca);
        throw new RuntimeException('Se permitio preparar nuevamente un movimiento ya preparado.');
    } catch (TransicionMovimientoException $esperada) {
    }
    try {
        $entradaFueraContexto = $entrada;
        $entradaFueraContexto['cuenta_bancaria_id'] = '1';
        $casoDeUso->ejecutar($entradaFueraContexto, $usuarioId, $claveInvalida);
        throw new RuntimeException('Se permitio preparar el movimiento desde otro contexto.');
    } catch (MovimientoNoEncontradoException $esperada) {
    }

    $consulta = $pdo->prepare(
        "SELECT count(*)
         FROM global_temp.bancos_evento_auditoria a
         JOIN global_temp.bancos_solicitud_idempotente s ON s.id = a.solicitud_id
         WHERE s.operacion = 'movimiento.preparar' AND s.clave = :clave"
    );
    $consulta->execute([':clave' => $clave]);
    comprobarPreparacion((int) $consulta->fetchColumn() === 1, 'La preparacion no dejo una unica auditoria.');
} finally {
    $pdo->beginTransaction();
    $borrar = $pdo->prepare(
        "DELETE FROM global_temp.bancos_evento_auditoria
         WHERE solicitud_id IN (
             SELECT id FROM global_temp.bancos_solicitud_idempotente
             WHERE clave IN (:clave, :segunda, :invalida)
         )"
    );
    $borrar->execute([
        ':clave' => $clave,
        ':segunda' => 'test-segunda-' . $marca,
        ':invalida' => $claveInvalida,
    ]);
    $borrar = $pdo->prepare(
        "DELETE FROM global_temp.bancos_solicitud_idempotente
         WHERE clave IN (:clave, :segunda, :invalida)"
    );
    $borrar->execute([
        ':clave' => $clave,
        ':segunda' => 'test-segunda-' . $marca,
        ':invalida' => $claveInvalida,
    ]);
    $borrar = $pdo->prepare(
        "DELETE FROM global_temp.bancos_historial_asignacion WHERE movimiento_id = :movimiento_id"
    );
    $borrar->execute([':movimiento_id' => $movimientoId]);
    $borrar = $pdo->prepare(
        "DELETE FROM global_temp.bancos_movimiento_extracto WHERE id = :movimiento_id"
    );
    $borrar->execute([':movimiento_id' => $movimientoId]);
    $pdo->commit();
}

echo "Preparacion de movimiento mensual debug OK\n";
