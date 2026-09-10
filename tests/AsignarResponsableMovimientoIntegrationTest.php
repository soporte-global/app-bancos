<?php

require_once dirname(__DIR__) . '/src/config.php';
require_once RUTA . '/src/autoload.php';

use AppBancos\Application\AsignarResponsableMovimientoMensual;
use AppBancos\Application\EjecutorComandoIdempotente;
use AppBancos\Application\RecursoNoDisponibleException;
use AppBancos\Infrastructure\EsquemaBancos;
use AppBancos\Repository\AsignacionMovimientoRepository;
use AppBancos\Repository\AuditoriaRepository;
use AppBancos\Repository\ContextoBandejaMensualRepository;
use AppBancos\Repository\IdempotenciaRepository;

function comprobarAsignacion($condicion, $mensaje)
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
comprobarAsignacion(count($usuarios) === 2, 'Faltan los administradores de APP BANCOS.');
$operadorId = (int) $usuarios['mcaballero'];
$responsableId = (int) $usuarios['hvega'];
$noAutorizadoId = (int) $pdo->query(
    "SELECT rl.id
     FROM global_prod.rrhh_login rl
     WHERE rl.habilitado IS TRUE AND rl.fecha_eliminacion IS NULL
       AND NOT EXISTS (
           SELECT 1
           FROM global_prod.hub_permisos_efectivos_usuario e
           JOIN global_prod.hub_permisos p ON p.id=e.permiso
           WHERE e.usuario=rl.id AND p.aplicacion=12 AND p.tipo_permiso=1 AND p.ignorado IS NULL
       )
     ORDER BY rl.id LIMIT 1"
)->fetchColumn();
comprobarAsignacion($noAutorizadoId > 0, 'Falta un usuario sin acceso para probar el rechazo.');
$importacion = $pdo->query(
    "SELECT i.id, i.estado_id, m.id_periodo, i.cuenta_bancaria_zetti_id, i.inicio_periodo
     FROM global_temp.bancos_importacion_extracto i
     JOIN global_temp.bancos_movimiento_extracto m ON m.importacion_id=i.id
     WHERE i.version_origen='FIXTURE-015'
     ORDER BY i.id,m.id LIMIT 1"
)->fetch(PDO::FETCH_ASSOC);
comprobarAsignacion($importacion !== false, 'Falta una importacion de fixture.');

$marca = bin2hex(random_bytes(8));
$claves = [
    'asignar' => 'test-asignar-' . $marca,
    'reasignar' => 'test-reasignar-' . $marca,
    'rechazar' => 'test-asignar-rechazo-' . $marca,
];
$numeroFila = random_int(1000000, 1900000000);
$insertar = $pdo->prepare(
    "INSERT INTO global_temp.bancos_movimiento_extracto
        (importacion_id,id_periodo,serial_seq,numero_fila_origen,fecha_operacion,
         referencia,descripcion,credito,debito,usuario_creacion,usuario_modificacion)
     VALUES (:importacion_id,:id_periodo,:serial_seq,:numero_fila,:fecha,
         :referencia,'Prueba aislada de asignacion',19.50,0,:usuario_id,:usuario_id)
     RETURNING id"
);
$insertar->execute([
    ':importacion_id' => $importacion['id'],
    ':id_periodo' => $importacion['id_periodo'],
    ':serial_seq' => $numeroFila,
    ':numero_fila' => $numeroFila,
    ':fecha' => $importacion['inicio_periodo'],
    ':referencia' => 'TEST-ASIGNAR-' . $marca,
    ':usuario_id' => $operadorId,
]);
$movimientoId = (int) $insertar->fetchColumn();
$esquema = EsquemaBancos::desdeConfiguracion(['bancos_debug' => true]);
$casoDeUso = new AsignarResponsableMovimientoMensual(
    new AsignacionMovimientoRepository($pdo, $esquema),
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
    $primera = $casoDeUso->ejecutar($contexto + ['responsable_id' => $responsableId], $operadorId, $claves['asignar']);
    $repetida = $casoDeUso->ejecutar($contexto + ['responsable_id' => $responsableId], $operadorId, $claves['asignar']);
    comprobarAsignacion($primera['respuesta']['cambio'] === true && $repetida['repetida'] === true, 'La asignacion inicial no fue idempotente.');
    comprobarAsignacion($primera['respuesta']['responsable'] === 'hvega', 'La asignacion no devolvio el usuario destino.');

    $segunda = $casoDeUso->ejecutar($contexto + ['responsable_id' => $operadorId], $responsableId, $claves['reasignar']);
    comprobarAsignacion($segunda['respuesta']['responsable_anterior_id'] === $responsableId, 'La reasignacion perdio el responsable anterior.');
    comprobarAsignacion($segunda['respuesta']['responsable_id'] === $operadorId, 'La reasignacion no aplico el destino.');

    try {
        $casoDeUso->ejecutar($contexto + ['responsable_id' => $noAutorizadoId], $operadorId, $claves['rechazar']);
        throw new RuntimeException('Se asigno un usuario sin acceso a APP BANCOS.');
    } catch (RecursoNoDisponibleException $esperada) {
    }

    $consulta = $pdo->prepare(
        "SELECT h.estado_id,h.usuario_hub_id,h.observacion
         FROM global_temp.bancos_historial_asignacion h
         WHERE h.movimiento_id=:movimiento_id ORDER BY h.id"
    );
    $consulta->execute([':movimiento_id' => $movimientoId]);
    $historial = $consulta->fetchAll(PDO::FETCH_ASSOC);
    comprobarAsignacion(count($historial) === 2, 'La asignacion no creo exactamente dos eventos.');
    comprobarAsignacion((int) $historial[0]['estado_id'] === (int) $importacion['estado_id'] && (int) $historial[1]['estado_id'] === (int) $importacion['estado_id'], 'La asignacion modifico el estado.');
    comprobarAsignacion(strpos($historial[1]['observacion'], 'OPERADOR:' . $responsableId) !== false, 'El historial no conserva al operador de la reasignacion.');

    $responsables = (new ContextoBandejaMensualRepository($pdo, $esquema))->consultar()['responsables'];
    $ids = array_map(static function (array $fila) { return (int) $fila['id']; }, $responsables);
    sort($ids);
    $esperados = [$operadorId, $responsableId];
    sort($esperados);
    comprobarAsignacion($ids === $esperados, 'El selector no esta limitado a usuarios con acceso efectivo.');
} finally {
    $pdo->beginTransaction();
    $consulta = $pdo->prepare(
        "DELETE FROM global_temp.bancos_evento_auditoria
         WHERE solicitud_id IN (SELECT id FROM global_temp.bancos_solicitud_idempotente WHERE clave IN (:asignar,:reasignar,:rechazar))"
    );
    $consulta->execute($claves);
    $consulta = $pdo->prepare('DELETE FROM global_temp.bancos_solicitud_idempotente WHERE clave IN (:asignar,:reasignar,:rechazar)');
    $consulta->execute($claves);
    $consulta = $pdo->prepare('DELETE FROM global_temp.bancos_historial_asignacion WHERE movimiento_id=:movimiento_id');
    $consulta->execute([':movimiento_id' => $movimientoId]);
    $consulta = $pdo->prepare('DELETE FROM global_temp.bancos_movimiento_extracto WHERE id=:movimiento_id');
    $consulta->execute([':movimiento_id' => $movimientoId]);
    $pdo->commit();
}

echo "Asignacion de responsable mensual debug OK\n";
