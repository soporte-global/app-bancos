<?php

require_once dirname(__DIR__) . '/src/config.php';
require_once RUTA . '/src/autoload.php';

use AppBancos\Application\AsociarAsientoMovimientoMensual;
use AppBancos\Application\EjecutorComandoIdempotente;
use AppBancos\Application\RecursoNoDisponibleException;
use AppBancos\Infrastructure\EsquemaBancos;
use AppBancos\Repository\AuditoriaRepository;
use AppBancos\Repository\IdempotenciaRepository;
use AppBancos\Repository\MovimientoMensualRepository;

function comprobarAsociacionAsiento($condicion, $mensaje)
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
$asientos = $pdo->query(
    "WITH candidatos AS (
         SELECT a.id, max(abs(m.monto))::numeric(20,5) importe,
                (max(abs(m.monto)) + 0.00001)::numeric(20,5) importe_distinto
         FROM public.asiento a
         JOIN public.movimiento m ON m.asiento = a.id
         WHERE NOT EXISTS (
                   SELECT 1 FROM global_temp.bancos_asociacion_movimiento x
                   WHERE x.asiento_zetti_id = a.id AND x.activo
               )
           AND NOT EXISTS (
                   SELECT 1 FROM global_temp.bancos_reserva_recurso r
                   WHERE r.asiento_zetti_id = a.id AND r.activo
               )
         GROUP BY a.id
         HAVING count(*) >= 2
            AND sum(CASE WHEN m.debita THEN abs(m.monto) ELSE 0 END)
                = sum(CASE WHEN NOT m.debita THEN abs(m.monto) ELSE 0 END)
            AND max(abs(m.monto)) > 0
     )
     SELECT * FROM candidatos ORDER BY id LIMIT 2"
)->fetchAll(PDO::FETCH_ASSOC);
comprobarAsociacionAsiento(
    $usuarioId > 0 && $importacion !== false && count($asientos) === 2,
    'Faltan datos base para probar la asociacion de asientos.'
);

$marca = bin2hex(random_bytes(8));
$claves = [
    'test-asiento-exclusivo-' . $marca,
    'test-asiento-competidor-' . $marca,
    'test-asiento-compartido-1-' . $marca,
    'test-asiento-compartido-2-' . $marca,
    'test-asiento-mezcla-' . $marca,
];
$movimientos = [];

try {
    $importes = [
        $asientos[0]['importe'],
        $asientos[0]['importe'],
        $asientos[1]['importe_distinto'],
        $asientos[1]['importe_distinto'],
        $asientos[1]['importe'],
    ];
    foreach ($importes as $indice => $importe) {
        $numeroFila = random_int(1000000, 1900000000);
        $consulta = $pdo->prepare(
            "INSERT INTO global_temp.bancos_movimiento_extracto
                (importacion_id, id_periodo, serial_seq, numero_fila_origen, fecha_operacion,
                 referencia, descripcion, credito, debito, usuario_creacion, usuario_modificacion)
             VALUES
                (:importacion_id, :id_periodo, :serial_seq, :numero_fila, :fecha,
                 :referencia, 'Prueba aislada de asiento', :importe, 0, :usuario_id, :usuario_id)
             RETURNING id"
        );
        $consulta->execute([
            ':importacion_id' => $importacion['id'],
            ':id_periodo' => $importacion['id_periodo'],
            ':serial_seq' => $numeroFila,
            ':numero_fila' => $numeroFila,
            ':fecha' => $importacion['inicio_periodo'],
            ':referencia' => 'TEST-ASIENTO-' . ($indice + 1) . '-' . $marca,
            ':importe' => $importe,
            ':usuario_id' => $usuarioId,
        ]);
        $movimientos[] = (int) $consulta->fetchColumn();
    }

    $esquema = EsquemaBancos::desdeConfiguracion(['bancos_debug' => true]);
    $casoDeUso = new AsociarAsientoMovimientoMensual(
        new MovimientoMensualRepository($pdo, $esquema),
        new EjecutorComandoIdempotente(
            $pdo,
            new IdempotenciaRepository($pdo, $esquema),
            new AuditoriaRepository($pdo, $esquema)
        )
    );
    $base = [
        'cuenta_bancaria_id' => (string) $importacion['cuenta_bancaria_zetti_id'],
        'inicio_periodo' => $importacion['inicio_periodo'],
    ];

    $entradaExclusiva = $base + [
        'movimiento_id' => $movimientos[0],
        'asiento_zetti_id' => (int) $asientos[0]['id'],
        'compartido' => false,
    ];
    $primera = $casoDeUso->ejecutar($entradaExclusiva, $usuarioId, $claves[0]);
    $reintento = $casoDeUso->ejecutar($entradaExclusiva, $usuarioId, $claves[0]);
    comprobarAsociacionAsiento(!$primera['repetida'] && $reintento['repetida'], 'La asociacion exclusiva no fue idempotente.');
    comprobarAsociacionAsiento($primera['respuesta']['monto_asociado'] === $asientos[0]['importe'], 'El importe exclusivo no coincide.');
    comprobarAsociacionAsiento($primera['respuesta']['compartido'] === false, 'La asociacion exclusiva cambio de modalidad.');

    try {
        $casoDeUso->ejecutar($base + [
            'movimiento_id' => $movimientos[1],
            'asiento_zetti_id' => (int) $asientos[0]['id'],
            'compartido' => false,
        ], $usuarioId, $claves[1]);
        throw new RuntimeException('Dos movimientos usaron el mismo asiento exclusivo.');
    } catch (RecursoNoDisponibleException $esperada) {
    }

    foreach ([2, 3] as $indice) {
        $respuesta = $casoDeUso->ejecutar($base + [
            'movimiento_id' => $movimientos[$indice],
            'asiento_zetti_id' => (int) $asientos[1]['id'],
            'compartido' => true,
        ], $usuarioId, $claves[$indice]);
        comprobarAsociacionAsiento($respuesta['respuesta']['compartido'] === true, 'No se conservo la modalidad compartida.');
        comprobarAsociacionAsiento($respuesta['respuesta']['cantidad_lineas_asiento'] >= 2, 'No se valido la estructura del asiento.');
    }

    try {
        $casoDeUso->ejecutar($base + [
            'movimiento_id' => $movimientos[4],
            'asiento_zetti_id' => (int) $asientos[1]['id'],
            'compartido' => false,
        ], $usuarioId, $claves[4]);
        throw new RuntimeException('Se mezclaron modalidades compartida y exclusiva.');
    } catch (RecursoNoDisponibleException $esperada) {
    }

    $consulta = $pdo->prepare(
        "SELECT
             (SELECT count(*) FROM global_temp.bancos_reserva_recurso
              WHERE asiento_zetti_id = :asiento_id AND activo AND compartido) reservas,
             (SELECT count(*) FROM global_temp.bancos_asociacion_movimiento
              WHERE asiento_zetti_id = :asiento_id AND activo AND compartido) asociaciones"
    );
    $consulta->execute([':asiento_id' => (int) $asientos[1]['id']]);
    $conteos = $consulta->fetch(PDO::FETCH_ASSOC);
    comprobarAsociacionAsiento((int) $conteos['reservas'] === 2, 'Faltan las dos reservas compartidas.');
    comprobarAsociacionAsiento((int) $conteos['asociaciones'] === 2, 'Faltan las dos asociaciones compartidas.');

    $consulta = $pdo->prepare(
        "SELECT detalles->>'asiento_zetti_id', detalles->>'compartido',
                detalles->>'reserva_id', detalles->>'asociacion_id'
         FROM global_temp.bancos_evento_auditoria a
         JOIN global_temp.bancos_solicitud_idempotente s ON s.id = a.solicitud_id
         WHERE s.operacion = 'movimiento.asociar-asiento' AND s.clave = :clave"
    );
    $consulta->execute([':clave' => $claves[2]]);
    $auditoria = $consulta->fetch(PDO::FETCH_NUM);
    comprobarAsociacionAsiento(
        $auditoria !== false && $auditoria[0] === (string) $asientos[1]['id']
        && $auditoria[1] === 'true' && (int) $auditoria[2] > 0 && (int) $auditoria[3] > 0,
        'La auditoria no conservo la evidencia del asiento compartido.'
    );
} finally {
    if ($movimientos) {
        $pdo->beginTransaction();
        $marcadores = [];
        $parametros = [];
        foreach ($claves as $indice => $clave) {
            $marcador = ':clave_' . $indice;
            $marcadores[] = $marcador;
            $parametros[$marcador] = $clave;
        }
        $pdo->prepare(
            "DELETE FROM global_temp.bancos_evento_auditoria
             WHERE solicitud_id IN (SELECT id FROM global_temp.bancos_solicitud_idempotente
             WHERE clave IN (" . implode(', ', $marcadores) . '))'
        )->execute($parametros);
        $pdo->prepare(
            "DELETE FROM global_temp.bancos_solicitud_idempotente WHERE clave IN (" . implode(', ', $marcadores) . ')'
        )->execute($parametros);

        $marcadores = [];
        $parametros = [];
        foreach ($movimientos as $indice => $movimientoId) {
            $marcador = ':movimiento_' . $indice;
            $marcadores[] = $marcador;
            $parametros[$marcador] = $movimientoId;
        }
        foreach (['bancos_asociacion_movimiento', 'bancos_reserva_recurso'] as $tabla) {
            $pdo->prepare(
                "DELETE FROM global_temp.{$tabla} WHERE movimiento_id IN (" . implode(', ', $marcadores) . ')'
            )->execute($parametros);
        }
        $pdo->prepare(
            "DELETE FROM global_temp.bancos_movimiento_extracto WHERE id IN (" . implode(', ', $marcadores) . ')'
        )->execute($parametros);
        $pdo->commit();
    }
}

echo "Asociacion segura de asiento debug OK\n";
