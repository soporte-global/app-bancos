<?php

require_once dirname(__DIR__) . '/src/config.php';
require_once RUTA . '/src/autoload.php';

use AppBancos\Application\EjecutorComandoIdempotente;
use AppBancos\Application\GestionarMensajesMovimientoMensual;
use AppBancos\Application\MovimientoNoEncontradoException;
use AppBancos\Infrastructure\EsquemaBancos;
use AppBancos\Repository\AuditoriaRepository;
use AppBancos\Repository\BandejaMensualRepository;
use AppBancos\Repository\IdempotenciaRepository;
use AppBancos\Repository\MensajeriaMovimientoRepository;

function comprobarMensajeria($condicion, $mensaje)
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
$usuarios = $pdo->query(
    "SELECT lower(usuario) usuario, id
     FROM global_prod.rrhh_login
     WHERE lower(usuario) IN ('mcaballero', 'hvega')
       AND habilitado IS TRUE AND fecha_eliminacion IS NULL"
)->fetchAll(PDO::FETCH_KEY_PAIR);
comprobarMensajeria(count($usuarios) === 2, 'Faltan los administradores para probar la mensajeria.');
$emisorId = (int) $usuarios['mcaballero'];
$receptorId = (int) $usuarios['hvega'];
$importacion = $pdo->query(
    "SELECT i.id, m.id_periodo, i.cuenta_bancaria_zetti_id, i.inicio_periodo
     FROM global_temp.bancos_importacion_extracto i
     JOIN global_temp.bancos_movimiento_extracto m ON m.importacion_id = i.id
     WHERE i.version_origen = 'FIXTURE-015'
     ORDER BY i.id, m.id LIMIT 1"
)->fetch(PDO::FETCH_ASSOC);
comprobarMensajeria($importacion !== false, 'Falta una importacion de fixture.');

$marca = bin2hex(random_bytes(8));
$claves = [
    'agregar' => 'test-mensaje-' . $marca,
    'leer' => 'test-lectura-' . $marca,
    'contexto' => 'test-mensaje-contexto-' . $marca,
];
$numeroFila = random_int(1000000, 1900000000);
$insertar = $pdo->prepare(
    "INSERT INTO global_temp.bancos_movimiento_extracto
        (importacion_id, id_periodo, serial_seq, numero_fila_origen, fecha_operacion,
         referencia, descripcion, credito, debito, usuario_creacion, usuario_modificacion)
     VALUES
        (:importacion_id, :id_periodo, :serial_seq, :numero_fila, :fecha,
         :referencia, 'Prueba aislada de mensajeria', 37.25, 0, :usuario_id, :usuario_id)
     RETURNING id"
);
$insertar->execute([
    ':importacion_id' => $importacion['id'],
    ':id_periodo' => $importacion['id_periodo'],
    ':serial_seq' => $numeroFila,
    ':numero_fila' => $numeroFila,
    ':fecha' => $importacion['inicio_periodo'],
    ':referencia' => 'TEST-MENSAJE-' . $marca,
    ':usuario_id' => $emisorId,
]);
$movimientoId = (int) $insertar->fetchColumn();
$esquema = EsquemaBancos::desdeConfiguracion(['bancos_debug' => true]);
$casoDeUso = new GestionarMensajesMovimientoMensual(
    new MensajeriaMovimientoRepository($pdo, $esquema),
    new EjecutorComandoIdempotente(
        $pdo,
        new IdempotenciaRepository($pdo, $esquema),
        new AuditoriaRepository($pdo, $esquema)
    )
);
$contexto = [
    'movimiento_id' => $movimientoId,
    'cuenta_bancaria_id' => (string) $importacion['cuenta_bancaria_zetti_id'],
    'inicio_periodo' => $importacion['inicio_periodo'],
];

try {
    $primera = $casoDeUso->agregar($contexto + ['cuerpo' => '  Mensaje colaborativo de prueba.  '], $emisorId, $claves['agregar']);
    $repetida = $casoDeUso->agregar($contexto + ['cuerpo' => 'Mensaje colaborativo de prueba.'], $emisorId, $claves['agregar']);
    comprobarMensajeria($primera['repetida'] === false && $repetida['repetida'] === true, 'El mensaje no fue idempotente.');
    comprobarMensajeria($primera['respuesta']['tipo_mensaje'] === 'USUARIO', 'El tipo de mensaje no fue derivado por servidor.');
    $mensajeId = (int) $primera['respuesta']['mensaje_id'];

    $consulta = $pdo->prepare(
        "SELECT m.cuerpo, m.emisor_hub_id, r.leido_en
         FROM global_temp.bancos_mensaje_movimiento m
         JOIN global_temp.bancos_recepcion_mensaje r
           ON r.mensaje_id = m.id AND r.receptor_hub_id = :emisor_id
         WHERE m.id = :mensaje_id"
    );
    $consulta->execute([':emisor_id' => $emisorId, ':mensaje_id' => $mensajeId]);
    $mensaje = $consulta->fetch(PDO::FETCH_ASSOC);
    comprobarMensajeria($mensaje !== false && $mensaje['cuerpo'] === 'Mensaje colaborativo de prueba.', 'El mensaje no se normalizo correctamente.');
    comprobarMensajeria((int) $mensaje['emisor_hub_id'] === $emisorId && $mensaje['leido_en'] !== null, 'El emisor o su lectura propia son incorrectos.');

    $bandejaAntes = (new BandejaMensualRepository($pdo, $esquema, $receptorId))->listar(
        $contexto['cuenta_bancaria_id'],
        $contexto['inicio_periodo'],
        null,
        100
    );
    $objetivoAntes = array_values(array_filter($bandejaAntes, static function (array $fila) use ($movimientoId) {
        return (int) $fila['id'] === $movimientoId;
    }));
    comprobarMensajeria(count($objetivoAntes) === 1 && $objetivoAntes[0]['mensajes_no_leidos'] === 1, 'La bandeja no detecto el mensaje pendiente.');

    $lectura = $casoDeUso->marcarLeidos($contexto, $receptorId, $claves['leer']);
    $lecturaRepetida = $casoDeUso->marcarLeidos($contexto, $receptorId, $claves['leer']);
    comprobarMensajeria($lectura['respuesta']['mensajes_leidos'] === 1, 'La lectura no abarco el mensaje recibido.');
    comprobarMensajeria($lecturaRepetida['repetida'] === true, 'La lectura repetida no fue reconocida.');

    $bandejaDespues = (new BandejaMensualRepository($pdo, $esquema, $receptorId))->listar(
        $contexto['cuenta_bancaria_id'],
        $contexto['inicio_periodo'],
        null,
        100
    );
    $objetivoDespues = array_values(array_filter($bandejaDespues, static function (array $fila) use ($movimientoId) {
        return (int) $fila['id'] === $movimientoId;
    }));
    comprobarMensajeria(count($objetivoDespues) === 1 && $objetivoDespues[0]['mensajes_no_leidos'] === 0, 'La bandeja no reflejo la lectura.');

    try {
        $fuera = $contexto;
        $fuera['cuenta_bancaria_id'] = '1';
        $casoDeUso->agregar($fuera + ['cuerpo' => 'No debe persistirse'], $emisorId, $claves['contexto']);
        throw new RuntimeException('La mensajeria acepto un movimiento fuera del contexto.');
    } catch (MovimientoNoEncontradoException $esperada) {
    }

    $consulta = $pdo->prepare(
        "SELECT count(*)
         FROM global_temp.bancos_evento_auditoria a
         JOIN global_temp.bancos_solicitud_idempotente s ON s.id = a.solicitud_id
         WHERE s.clave IN (:agregar, :leer)"
    );
    $consulta->execute([':agregar' => $claves['agregar'], ':leer' => $claves['leer']]);
    comprobarMensajeria((int) $consulta->fetchColumn() === 2, 'La mensajeria no dejo una auditoria por comando.');
} finally {
    $pdo->beginTransaction();
    $consulta = $pdo->prepare(
        "DELETE FROM global_temp.bancos_recepcion_mensaje
         WHERE mensaje_id IN (SELECT id FROM global_temp.bancos_mensaje_movimiento WHERE movimiento_id = :movimiento_id)"
    );
    $consulta->execute([':movimiento_id' => $movimientoId]);
    $consulta = $pdo->prepare('DELETE FROM global_temp.bancos_mensaje_movimiento WHERE movimiento_id = :movimiento_id');
    $consulta->execute([':movimiento_id' => $movimientoId]);
    $consulta = $pdo->prepare(
        "DELETE FROM global_temp.bancos_evento_auditoria
         WHERE solicitud_id IN (SELECT id FROM global_temp.bancos_solicitud_idempotente WHERE clave IN (:agregar, :leer, :contexto))"
    );
    $consulta->execute($claves);
    $consulta = $pdo->prepare(
        'DELETE FROM global_temp.bancos_solicitud_idempotente WHERE clave IN (:agregar, :leer, :contexto)'
    );
    $consulta->execute($claves);
    $consulta = $pdo->prepare('DELETE FROM global_temp.bancos_movimiento_extracto WHERE id = :movimiento_id');
    $consulta->execute([':movimiento_id' => $movimientoId]);
    $pdo->commit();
}

echo "Mensajeria de movimiento mensual debug OK\n";
