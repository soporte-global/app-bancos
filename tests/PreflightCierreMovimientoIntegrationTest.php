<?php

require_once dirname(__DIR__) . '/src/config.php';
require_once RUTA . '/src/autoload.php';

use AppBancos\Application\PrevalidarCierreMovimientoMensual;
use AppBancos\Application\TransicionMovimientoException;
use AppBancos\Infrastructure\EsquemaBancos;
use AppBancos\Repository\PreflightCierreMovimientoRepository;

function comprobarPreflightCierre($condicion, $mensaje)
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
comprobarPreflightCierre($usuarioId > 0 && $importacion !== false, 'Faltan datos base para el preflight.');

$asientos = $pdo->query(
    "SELECT a.id
     FROM public.asiento a
     JOIN public.movimiento m ON m.asiento = a.id
     WHERE COALESCE(a.rev, false) IS FALSE
       AND NOT EXISTS (
           SELECT 1 FROM global_temp.bancos_asociacion_movimiento x
           WHERE x.asiento_zetti_id = a.id AND x.activo IS TRUE
       )
       AND NOT EXISTS (
           SELECT 1 FROM global_temp.bancos_reserva_recurso x
           WHERE x.asiento_zetti_id = a.id AND x.activo IS TRUE
       )
     GROUP BY a.id
     HAVING count(m.id) >= 2
        AND sum(CASE WHEN m.debita THEN abs(m.monto) ELSE 0 END)
            = sum(CASE WHEN NOT m.debita THEN abs(m.monto) ELSE 0 END)
     ORDER BY a.id DESC
     LIMIT 2"
)->fetchAll(PDO::FETCH_COLUMN);
comprobarPreflightCierre(count($asientos) === 2, 'No se encontraron asientos ERP aptos para la prueba.');

$pdo->beginTransaction();
try {
    $marca = bin2hex(random_bytes(6));
    $insertarMovimiento = $pdo->prepare(
        "INSERT INTO global_temp.bancos_movimiento_extracto
            (importacion_id, id_periodo, serial_seq, numero_fila_origen, fecha_operacion,
             referencia, descripcion, credito, debito, usuario_creacion, usuario_modificacion)
         VALUES
            (:importacion_id, :id_periodo, :serial_seq, :numero_fila, :fecha,
             :referencia, :descripcion, 125.50, 0, :usuario_id, :usuario_id)
         RETURNING id"
    );
    $movimientos = [];
    foreach (['SIN-ASOCIACION', 'ASIENTO-VALIDO', 'SIN-RESERVA', 'ABIERTO'] as $indice => $caso) {
        $numeroFila = random_int(1000000, 1900000000);
        $insertarMovimiento->execute([
            ':importacion_id' => $importacion['id'],
            ':id_periodo' => $importacion['id_periodo'],
            ':serial_seq' => $numeroFila,
            ':numero_fila' => $numeroFila,
            ':fecha' => $importacion['inicio_periodo'],
            ':referencia' => 'TEST-PREFLIGHT-' . $marca . '-' . $indice,
            ':descripcion' => $caso,
            ':usuario_id' => $usuarioId,
        ]);
        $movimientos[$caso] = (int) $insertarMovimiento->fetchColumn();
    }

    $estadoParaCerrar = (int) $pdo->query(
        "SELECT id FROM global_temp.bancos_estado WHERE codigo = 'PARA_CERRAR'"
    )->fetchColumn();
    $insertarEstado = $pdo->prepare(
        "INSERT INTO global_temp.bancos_historial_asignacion
            (movimiento_id, estado_id, usuario_hub_id, observacion)
         VALUES (:movimiento_id, :estado_id, :usuario_id, 'TEST|PREFLIGHT')"
    );
    foreach (['SIN-ASOCIACION', 'ASIENTO-VALIDO', 'SIN-RESERVA'] as $caso) {
        $insertarEstado->execute([
            ':movimiento_id' => $movimientos[$caso],
            ':estado_id' => $estadoParaCerrar,
            ':usuario_id' => $usuarioId,
        ]);
    }

    $insertarAsociacion = $pdo->prepare(
        "INSERT INTO global_temp.bancos_asociacion_movimiento
            (movimiento_id, asiento_zetti_id, activo, compartido, observacion, usuario_creacion)
         VALUES (:movimiento_id, :asiento_id, true, false, 'TEST|PREFLIGHT', :usuario_id)"
    );
    $insertarReserva = $pdo->prepare(
        "INSERT INTO global_temp.bancos_reserva_recurso
            (movimiento_id, asiento_zetti_id, activo, compartido, reservado_por, motivo)
         VALUES (:movimiento_id, :asiento_id, true, false, :usuario_id, 'TEST|PREFLIGHT')"
    );
    $insertarAsociacion->execute([
        ':movimiento_id' => $movimientos['ASIENTO-VALIDO'],
        ':asiento_id' => $asientos[0],
        ':usuario_id' => $usuarioId,
    ]);
    $insertarReserva->execute([
        ':movimiento_id' => $movimientos['ASIENTO-VALIDO'],
        ':asiento_id' => $asientos[0],
        ':usuario_id' => $usuarioId,
    ]);
    $insertarAsociacion->execute([
        ':movimiento_id' => $movimientos['SIN-RESERVA'],
        ':asiento_id' => $asientos[1],
        ':usuario_id' => $usuarioId,
    ]);

    $contarFilas = function () use ($pdo, $movimientos) {
        $ids = implode(',', array_map('intval', array_values($movimientos)));
        return [
            'asociaciones' => (int) $pdo->query(
                "SELECT count(*) FROM global_temp.bancos_asociacion_movimiento WHERE movimiento_id IN ({$ids})"
            )->fetchColumn(),
            'reservas' => (int) $pdo->query(
                "SELECT count(*) FROM global_temp.bancos_reserva_recurso WHERE movimiento_id IN ({$ids})"
            )->fetchColumn(),
            'historial' => (int) $pdo->query(
                "SELECT count(*) FROM global_temp.bancos_historial_asignacion WHERE movimiento_id IN ({$ids})"
            )->fetchColumn(),
        ];
    };
    $antes = $contarFilas();
    $casoDeUso = new PrevalidarCierreMovimientoMensual(
        new PreflightCierreMovimientoRepository(
            $pdo,
            EsquemaBancos::desdeConfiguracion(['bancos_debug' => true])
        )
    );
    $contexto = [
        'cuenta_bancaria_id' => (string) $importacion['cuenta_bancaria_zetti_id'],
        'inicio_periodo' => $importacion['inicio_periodo'],
    ];

    $sinAsociacion = $casoDeUso->ejecutar(array_merge($contexto, [
        'movimiento_id' => $movimientos['SIN-ASOCIACION'],
    ]));
    comprobarPreflightCierre($sinAsociacion['listo'] === true, 'Se bloqueo el caso historicamente admisible sin asociacion.');
    comprobarPreflightCierre($sinAsociacion['resultado'] === 'LISTO_CON_ADVERTENCIAS', 'No se advirtio el cierre sin efectos ERP.');
    comprobarPreflightCierre($sinAsociacion['solo_lectura'] === true, 'El contrato no declara su naturaleza de solo lectura.');

    $asientoValido = $casoDeUso->ejecutar(array_merge($contexto, [
        'movimiento_id' => $movimientos['ASIENTO-VALIDO'],
    ]));
    comprobarPreflightCierre($asientoValido['resultado'] === 'LISTO', 'Se rechazo un asiento existente valido.');
    comprobarPreflightCierre(
        in_array('SIN_CAMBIOS', array_column($asientoValido['efectos_previstos'], 'accion'), true),
        'El asiento existente no quedo identificado como efecto sin cambios.'
    );

    $sinReserva = $casoDeUso->ejecutar(array_merge($contexto, [
        'movimiento_id' => $movimientos['SIN-RESERVA'],
    ]));
    comprobarPreflightCierre($sinReserva['resultado'] === 'BLOQUEADO', 'Se acepto una asociacion sin reserva.');
    comprobarPreflightCierre(
        in_array('RESERVA_AUSENTE', array_column($sinReserva['bloqueos'], 'codigo'), true),
        'El bloqueo no identifico la reserva ausente.'
    );

    try {
        $casoDeUso->ejecutar(array_merge($contexto, ['movimiento_id' => $movimientos['ABIERTO']]));
        throw new RuntimeException('Se permitio prevalidar un movimiento ABIERTO.');
    } catch (TransicionMovimientoException $esperada) {
    }

    comprobarPreflightCierre($contarFilas() === $antes, 'El preflight modifico tablas operativas.');
    comprobarPreflightCierre(
        (bool) preg_match('/^[0-9]+$/', (string) $asientoValido['efectos_previstos'][0]['recurso_id']),
        'El contrato no preservo el ID ERP como texto decimal.'
    );
    $pdo->rollBack();
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $error;
}

echo "Preflight de cierre mensual de solo lectura OK\n";
