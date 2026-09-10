<?php

require_once dirname(__DIR__) . '/src/config.php';
require_once RUTA . '/src/autoload.php';

use AppBancos\Application\AsociarValorMovimientoMensual;
use AppBancos\Application\EjecutorComandoIdempotente;
use AppBancos\Application\RecursoNoDisponibleException;
use AppBancos\Infrastructure\EsquemaBancos;
use AppBancos\Repository\AuditoriaRepository;
use AppBancos\Repository\IdempotenciaRepository;
use AppBancos\Repository\MovimientoMensualRepository;

function comprobarAsociacionValor($condicion, $mensaje)
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
$valorId = (int) $pdo->query(
    "SELECT v.id
     FROM public.valor v
     WHERE v.estado NOT IN (7, 20, 36)
       AND abs(v.monto_principal) >= 10
       AND NOT EXISTS (
           SELECT 1 FROM global_temp.bancos_reserva_recurso r
           WHERE r.valor_zetti_id = v.id AND r.activo
       )
       AND NOT EXISTS (
           SELECT 1 FROM global_temp.bancos_asociacion_movimiento a
           WHERE a.valor_zetti_id = v.id AND a.activo
       )
     ORDER BY v.id LIMIT 1"
)->fetchColumn();
comprobarAsociacionValor(
    $usuarioId > 0 && $valorId > 0 && $importacion !== false,
    'Faltan datos base para probar la asociacion de valor.'
);

$marca = bin2hex(random_bytes(8));
$clavePrimera = 'test-asociar-valor-' . $marca;
$claveCompetidora = 'test-asociar-competidor-' . $marca;
$movimientos = [];

try {
    foreach ([1, 2] as $numero) {
        $numeroFila = random_int(1000000, 1900000000);
        $consulta = $pdo->prepare(
            "INSERT INTO global_temp.bancos_movimiento_extracto
                (importacion_id, id_periodo, serial_seq, numero_fila_origen, fecha_operacion,
                 referencia, descripcion, credito, debito, usuario_creacion, usuario_modificacion)
             VALUES
                (:importacion_id, :id_periodo, :serial_seq, :numero_fila, :fecha,
                 :referencia, 'Prueba aislada de asociacion', 10.00, 0, :usuario_id, :usuario_id)
             RETURNING id"
        );
        $consulta->execute([
            ':importacion_id' => $importacion['id'],
            ':id_periodo' => $importacion['id_periodo'],
            ':serial_seq' => $numeroFila,
            ':numero_fila' => $numeroFila,
            ':fecha' => $importacion['inicio_periodo'],
            ':referencia' => 'TEST-ASOCIAR-' . $numero . '-' . $marca,
            ':usuario_id' => $usuarioId,
        ]);
        $movimientos[] = (int) $consulta->fetchColumn();
    }

    $esquema = EsquemaBancos::desdeConfiguracion(['bancos_debug' => true]);
    $casoDeUso = new AsociarValorMovimientoMensual(
        new MovimientoMensualRepository($pdo, $esquema),
        new EjecutorComandoIdempotente(
            $pdo,
            new IdempotenciaRepository($pdo, $esquema),
            new AuditoriaRepository($pdo, $esquema)
        )
    );
    $entrada = [
        'movimiento_id' => $movimientos[0],
        'valor_zetti_id' => $valorId,
        'cuenta_bancaria_id' => (string) $importacion['cuenta_bancaria_zetti_id'],
        'inicio_periodo' => $importacion['inicio_periodo'],
    ];
    $primera = $casoDeUso->ejecutar($entrada, $usuarioId, $clavePrimera);
    $reintento = $casoDeUso->ejecutar($entrada, $usuarioId, $clavePrimera);
    comprobarAsociacionValor($primera['repetida'] === false && $reintento['repetida'] === true, 'La asociacion no fue idempotente.');
    comprobarAsociacionValor($primera['codigo_http'] === 201, 'La primera asociacion no devolvio HTTP 201.');
    comprobarAsociacionValor($primera['respuesta']['monto_asociado'] === '10.00000', 'El importe no se derivo del movimiento.');

    $entradaCompetidora = $entrada;
    $entradaCompetidora['movimiento_id'] = $movimientos[1];
    try {
        $casoDeUso->ejecutar($entradaCompetidora, $usuarioId, $claveCompetidora);
        throw new RuntimeException('Dos movimientos reservaron el mismo valor.');
    } catch (RecursoNoDisponibleException $esperada) {
    }

    $consulta = $pdo->prepare(
        "SELECT
             (SELECT count(*) FROM global_temp.bancos_reserva_recurso WHERE valor_zetti_id = :valor_id AND activo) reservas,
             (SELECT count(*) FROM global_temp.bancos_asociacion_movimiento WHERE valor_zetti_id = :valor_id AND activo) asociaciones"
    );
    $consulta->execute([':valor_id' => $valorId]);
    $conteos = $consulta->fetch(PDO::FETCH_ASSOC);
    comprobarAsociacionValor((int) $conteos['reservas'] === 1, 'La exclusividad no dejo una unica reserva.');
    comprobarAsociacionValor((int) $conteos['asociaciones'] === 1, 'La exclusividad no dejo una unica asociacion.');

    $consulta = $pdo->prepare(
        "SELECT detalles->>'reserva_id', detalles->>'asociacion_id', detalles->>'monto_asociado'
         FROM global_temp.bancos_evento_auditoria a
         JOIN global_temp.bancos_solicitud_idempotente s ON s.id = a.solicitud_id
         WHERE s.operacion = 'movimiento.asociar-valor' AND s.clave = :clave"
    );
    $consulta->execute([':clave' => $clavePrimera]);
    $auditoria = $consulta->fetch(PDO::FETCH_NUM);
    comprobarAsociacionValor($auditoria !== false && $auditoria[2] === '10.00000', 'La auditoria no conservo la evidencia de asociacion.');
} finally {
    if ($movimientos) {
        $pdo->beginTransaction();
        $consulta = $pdo->prepare(
            "DELETE FROM global_temp.bancos_evento_auditoria
             WHERE solicitud_id IN (
                 SELECT id FROM global_temp.bancos_solicitud_idempotente
                 WHERE clave IN (:primera, :competidora)
             )"
        );
        $consulta->execute([':primera' => $clavePrimera, ':competidora' => $claveCompetidora]);
        $consulta = $pdo->prepare(
            "DELETE FROM global_temp.bancos_solicitud_idempotente
             WHERE clave IN (:primera, :competidora)"
        );
        $consulta->execute([':primera' => $clavePrimera, ':competidora' => $claveCompetidora]);
        foreach (['bancos_asociacion_movimiento', 'bancos_reserva_recurso'] as $tabla) {
            $consulta = $pdo->prepare(
                "DELETE FROM global_temp.{$tabla} WHERE movimiento_id IN (:primero, :segundo)"
            );
            $consulta->execute([':primero' => $movimientos[0], ':segundo' => $movimientos[1] ?? $movimientos[0]]);
        }
        $consulta = $pdo->prepare(
            'DELETE FROM global_temp.bancos_movimiento_extracto WHERE id IN (:primero, :segundo)'
        );
        $consulta->execute([':primero' => $movimientos[0], ':segundo' => $movimientos[1] ?? $movimientos[0]]);
        $pdo->commit();
    }
}

echo "Asociacion segura de valor debug OK\n";
