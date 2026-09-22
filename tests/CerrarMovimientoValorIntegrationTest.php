<?php

require_once dirname(__DIR__) . '/src/config.php';
require_once RUTA . '/src/autoload.php';

use AppBancos\Application\CerrarMovimientoMensual;
use AppBancos\Application\EjecutorComandoIdempotente;
use AppBancos\Application\RecursoNoDisponibleException;
use AppBancos\Infrastructure\EsquemaBancos;
use AppBancos\Repository\AuditoriaRepository;
use AppBancos\Repository\BorradorAsientoErpGateway;
use AppBancos\Repository\CierreMovimientoRepository;
use AppBancos\Repository\IdempotenciaRepository;
use AppBancos\Repository\PreflightCierreMovimientoRepository;
use AppBancos\Repository\ValorErpGateway;

function comprobarCierreValor($condicion, $mensaje)
{
    if (!$condicion) {
        throw new RuntimeException($mensaje);
    }
}

function casoCierreValor(PDO $pdo)
{
    $esquema = EsquemaBancos::desdeConfiguracion(['bancos_debug' => true]);
    return new CerrarMovimientoMensual(
        new CierreMovimientoRepository($pdo, $esquema),
        new PreflightCierreMovimientoRepository($pdo, $esquema),
        new ValorErpGateway($pdo, $esquema),
        new BorradorAsientoErpGateway($pdo, $esquema),
        new EjecutorComandoIdempotente(
            $pdo,
            new IdempotenciaRepository($pdo, $esquema),
            new AuditoriaRepository($pdo, $esquema)
        )
    );
}

$pdo = new PDO(
    'pgsql:host=' . HOST . ';port=' . PORT . ';dbname=' . DBASE,
    USER,
    PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$usuarioId = (int) $pdo->query(
    "SELECT id FROM global_prod.rrhh_login
     WHERE lower(usuario)='hvega' AND habilitado IS TRUE AND fecha_eliminacion IS NULL"
)->fetchColumn();
$importacion = $pdo->query(
    "SELECT i.id, m.id_periodo, i.cuenta_bancaria_zetti_id, i.inicio_periodo
     FROM global_temp.bancos_importacion_extracto i
     JOIN global_temp.bancos_movimiento_extracto m ON m.importacion_id=i.id
     WHERE i.version_origen='FIXTURE-015'
     ORDER BY i.id, m.id LIMIT 1"
)->fetch(PDO::FETCH_ASSOC);
$valores = $pdo->query(
    "SELECT v.id::text, v.estado
     FROM public.valor v
     LEFT JOIN public.subtipo_valor s ON s.id=v.subtipo_valor
     WHERE v.estado NOT IN (7,20,36)
       AND upper(COALESCE(s.nombre,'')) NOT LIKE '%CHEQUE%'
       AND NOT EXISTS (SELECT 1 FROM global_temp.valor t WHERE t.id=v.id)
       AND NOT EXISTS (
           SELECT 1 FROM global_temp.bancos_asociacion_movimiento a
           WHERE a.valor_zetti_id=v.id AND a.activo
       )
       AND NOT EXISTS (
           SELECT 1 FROM global_temp.bancos_reserva_recurso r
           WHERE r.valor_zetti_id=v.id AND r.activo
       )
     ORDER BY v.id DESC LIMIT 2"
)->fetchAll(PDO::FETCH_ASSOC);
comprobarCierreValor($usuarioId > 0 && $importacion !== false && count($valores) === 2, 'Faltan datos base para probar el cierre de valores.');

$marca = bin2hex(random_bytes(6));
$claves = [
    'exito' => 'test-cierre-valor-ok-' . $marca,
    'desfasado' => 'test-cierre-valor-stale-' . $marca,
];
$movimientos = [];
$valorIds = array_column($valores, 'id');
try {
    $copiar = $pdo->prepare("INSERT INTO global_temp.valor SELECT * FROM public.valor WHERE id=:id");
    foreach ($valorIds as $valorId) {
        $copiar->execute([':id' => $valorId]);
    }

    $insertar = $pdo->prepare(
        "INSERT INTO global_temp.bancos_movimiento_extracto
            (importacion_id,id_periodo,serial_seq,numero_fila_origen,fecha_operacion,
             referencia,descripcion,credito,debito,usuario_creacion,usuario_modificacion)
         VALUES
            (:importacion_id,:id_periodo,:serial_seq,:numero_fila,:fecha,
             :referencia,:descripcion,200,0,:usuario_id,:usuario_id)
         RETURNING id"
    );
    foreach (['EXITO', 'DESFASADO'] as $indice => $caso) {
        $numeroFila = random_int(1000000, 1900000000);
        $insertar->execute([
            ':importacion_id' => $importacion['id'],
            ':id_periodo' => $importacion['id_periodo'],
            ':serial_seq' => $numeroFila,
            ':numero_fila' => $numeroFila,
            ':fecha' => $importacion['inicio_periodo'],
            ':referencia' => 'TEST-CIERRE-VALOR-' . $marca . '-' . $indice,
            ':descripcion' => $caso,
            ':usuario_id' => $usuarioId,
        ]);
        $movimientos[$caso] = (int) $insertar->fetchColumn();
    }
    $estadoParaCerrar = (int) $pdo->query(
        "SELECT id FROM global_temp.bancos_estado WHERE codigo='PARA_CERRAR'"
    )->fetchColumn();
    $insertarEstado = $pdo->prepare(
        "INSERT INTO global_temp.bancos_historial_asignacion
            (movimiento_id,estado_id,usuario_hub_id,observacion)
         VALUES (:movimiento_id,:estado_id,:usuario_id,'TEST|CIERRE_VALOR')"
    );
    $asociar = $pdo->prepare(
        "INSERT INTO global_temp.bancos_asociacion_movimiento
            (movimiento_id,valor_zetti_id,activo,observacion,usuario_creacion)
         VALUES (:movimiento_id,:valor_id,true,'TEST|CIERRE_VALOR',:usuario_id)"
    );
    $reservar = $pdo->prepare(
        "INSERT INTO global_temp.bancos_reserva_recurso
            (movimiento_id,valor_zetti_id,activo,reservado_por,motivo)
         VALUES (:movimiento_id,:valor_id,true,:usuario_id,'TEST|CIERRE_VALOR')"
    );
    foreach ([['EXITO', 0], ['DESFASADO', 1]] as $caso) {
        $parametros = [
            ':movimiento_id' => $movimientos[$caso[0]],
            ':valor_id' => $valorIds[$caso[1]],
            ':usuario_id' => $usuarioId,
        ];
        $insertarEstado->execute([
            ':movimiento_id' => $parametros[':movimiento_id'],
            ':estado_id' => $estadoParaCerrar,
            ':usuario_id' => $usuarioId,
        ]);
        $asociar->execute($parametros);
        $reservar->execute($parametros);
    }

    $entrada = function ($movimientoId) use ($importacion) {
        return [
            'movimiento_id' => $movimientoId,
            'cuenta_bancaria_id' => (string) $importacion['cuenta_bancaria_zetti_id'],
            'inicio_periodo' => $importacion['inicio_periodo'],
        ];
    };
    $casoDeUso = casoCierreValor($pdo);
    $primera = $casoDeUso->ejecutar($entrada($movimientos['EXITO']), $usuarioId, $claves['exito']);
    $repetida = $casoDeUso->ejecutar($entrada($movimientos['EXITO']), $usuarioId, $claves['exito']);
    comprobarCierreValor(!$primera['repetida'] && $repetida['repetida'], 'El cierre de valor no fue idempotente.');
    comprobarCierreValor($primera['respuesta']['estado'] === 'CERRADO', 'El movimiento con valor no quedo CERRADO.');
    comprobarCierreValor($primera['respuesta']['efectos_erp'] === 1, 'No se informo el efecto ERP aplicado.');
    comprobarCierreValor($primera['respuesta']['alcance'] === 'VALOR_NO_CHEQUE', 'El alcance del cierre es incorrecto.');

    $estadoTemporal = $pdo->prepare("SELECT estado FROM global_temp.valor WHERE id=:id");
    $estadoTemporal->execute([':id' => $valorIds[0]]);
    comprobarCierreValor((int) $estadoTemporal->fetchColumn() === 7, 'La copia sandbox del valor no fue liquidada.');
    $estadoPublico = $pdo->prepare("SELECT estado FROM public.valor WHERE id=:id");
    $estadoPublico->execute([':id' => $valorIds[0]]);
    comprobarCierreValor((int) $estadoPublico->fetchColumn() === (int) $valores[0]['estado'], 'El cierre modifico public.valor.');

    $desfasar = $pdo->prepare("UPDATE global_temp.valor SET estado=7 WHERE id=:id");
    $desfasar->execute([':id' => $valorIds[1]]);
    try {
        $casoDeUso->ejecutar($entrada($movimientos['DESFASADO']), $usuarioId, $claves['desfasado']);
        throw new RuntimeException('Se cerro un movimiento con una copia ERP desfasada.');
    } catch (RecursoNoDisponibleException $esperada) {
    }
    $estadoActual = $pdo->prepare(
        "SELECT e.codigo FROM global_temp.bancos_historial_asignacion h
         JOIN global_temp.bancos_estado e ON e.id=h.estado_id
         WHERE h.movimiento_id=:id ORDER BY h.registrado_en DESC,h.id DESC LIMIT 1"
    );
    $estadoActual->execute([':id' => $movimientos['DESFASADO']]);
    comprobarCierreValor($estadoActual->fetchColumn() === 'PARA_CERRAR', 'El rollback no preservo PARA_CERRAR.');
    $solicitud = $pdo->prepare(
        "SELECT count(*) FROM global_temp.bancos_solicitud_idempotente
         WHERE operacion='movimiento.cerrar' AND clave=:clave"
    );
    $solicitud->execute([':clave' => $claves['desfasado']]);
    comprobarCierreValor((int) $solicitud->fetchColumn() === 0, 'El cierre rechazado dejo una solicitud parcial.');

    $auditoria = $pdo->prepare(
        "SELECT e.detalles FROM global_temp.bancos_evento_auditoria e
         JOIN global_temp.bancos_solicitud_idempotente s ON s.id=e.solicitud_id
         WHERE s.operacion='movimiento.cerrar' AND s.clave=:clave"
    );
    $auditoria->execute([':clave' => $claves['exito']]);
    $detalles = json_decode((string) $auditoria->fetchColumn(), true);
    comprobarCierreValor(
        $detalles['efectos_aplicados'][0]['resultado']['valor_zetti_id'] === $valorIds[0],
        'La auditoria no identifica el valor liquidado.'
    );
} finally {
    if ($movimientos) {
        $ids = implode(',', array_map('intval', array_values($movimientos)));
        $marcadores = [':clave_0', ':clave_1'];
        $parametros = [':clave_0' => $claves['exito'], ':clave_1' => $claves['desfasado']];
        $pdo->beginTransaction();
        $borrar = $pdo->prepare(
            "DELETE FROM global_temp.bancos_evento_auditoria WHERE solicitud_id IN (
                 SELECT id FROM global_temp.bancos_solicitud_idempotente
                 WHERE clave IN (" . implode(',', $marcadores) . ")
             )"
        );
        $borrar->execute($parametros);
        $borrar = $pdo->prepare(
            "DELETE FROM global_temp.bancos_solicitud_idempotente WHERE clave IN (" . implode(',', $marcadores) . ")"
        );
        $borrar->execute($parametros);
        $pdo->exec("DELETE FROM global_temp.bancos_reserva_recurso WHERE movimiento_id IN ({$ids})");
        $pdo->exec("DELETE FROM global_temp.bancos_asociacion_movimiento WHERE movimiento_id IN ({$ids})");
        $pdo->exec("DELETE FROM global_temp.bancos_historial_asignacion WHERE movimiento_id IN ({$ids})");
        $pdo->exec("DELETE FROM global_temp.bancos_movimiento_extracto WHERE id IN ({$ids})");
        $borrarValores = $pdo->prepare(
            "DELETE FROM global_temp.valor WHERE id IN (:valor_0,:valor_1)"
        );
        $borrarValores->execute([':valor_0' => $valorIds[0], ':valor_1' => $valorIds[1]]);
        $pdo->commit();
    }
}

echo "Cierre mensual de valores no cheque en sandbox OK\n";
