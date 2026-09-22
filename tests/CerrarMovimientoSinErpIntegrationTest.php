<?php

require_once dirname(__DIR__) . '/src/config.php';
require_once RUTA . '/src/autoload.php';

use AppBancos\Application\CerrarMovimientoMensualSinErp;
use AppBancos\Application\EjecutorComandoIdempotente;
use AppBancos\Application\RecursoNoDisponibleException;
use AppBancos\Infrastructure\EsquemaBancos;
use AppBancos\Repository\AuditoriaRepository;
use AppBancos\Repository\CierreMovimientoRepository;
use AppBancos\Repository\IdempotenciaRepository;
use AppBancos\Repository\PreflightCierreMovimientoRepository;

function comprobarCierreSinErp($condicion, $mensaje)
{
    if (!$condicion) {
        throw new RuntimeException($mensaje);
    }
}

function conexionCierreSinErp()
{
    return new PDO(
        'pgsql:host=' . HOST . ';port=' . PORT . ';dbname=' . DBASE,
        USER,
        PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
}

function casoCierreSinErp(PDO $pdo)
{
    $esquema = EsquemaBancos::desdeConfiguracion(['bancos_debug' => true]);
    return new CerrarMovimientoMensualSinErp(
        new CierreMovimientoRepository($pdo, $esquema),
        new PreflightCierreMovimientoRepository($pdo, $esquema),
        new EjecutorComandoIdempotente(
            $pdo,
            new IdempotenciaRepository($pdo, $esquema),
            new AuditoriaRepository($pdo, $esquema)
        )
    );
}

$pdo = conexionCierreSinErp();
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
comprobarCierreSinErp($usuarioId > 0 && $importacion !== false, 'Faltan datos base para probar el cierre.');

$asientoId = $pdo->query(
    "SELECT a.id
     FROM public.asiento a
     JOIN public.movimiento m ON m.asiento = a.id
     WHERE COALESCE(a.rev, false) IS FALSE
       AND NOT EXISTS (SELECT 1 FROM global_temp.bancos_asociacion_movimiento x WHERE x.asiento_zetti_id=a.id AND x.activo)
       AND NOT EXISTS (SELECT 1 FROM global_temp.bancos_reserva_recurso x WHERE x.asiento_zetti_id=a.id AND x.activo)
     GROUP BY a.id
     HAVING count(m.id)>=2
        AND sum(CASE WHEN m.debita THEN abs(m.monto) ELSE 0 END)=sum(CASE WHEN NOT m.debita THEN abs(m.monto) ELSE 0 END)
     ORDER BY a.id DESC LIMIT 1"
)->fetchColumn();
$valorId = $pdo->query(
    "SELECT v.id
     FROM public.valor v
     LEFT JOIN public.subtipo_valor s ON s.id=v.subtipo_valor
     WHERE v.estado NOT IN (7,20,36)
       AND upper(COALESCE(s.nombre,'')) NOT LIKE '%CHEQUE%'
       AND NOT EXISTS (SELECT 1 FROM global_temp.bancos_asociacion_movimiento x WHERE x.valor_zetti_id=v.id AND x.activo)
       AND NOT EXISTS (SELECT 1 FROM global_temp.bancos_reserva_recurso x WHERE x.valor_zetti_id=v.id AND x.activo)
     ORDER BY v.id DESC LIMIT 1"
)->fetchColumn();
comprobarCierreSinErp($asientoId !== false && $valorId !== false, 'Faltan recursos ERP para la prueba.');

$marca = bin2hex(random_bytes(6));
$claves = [
    'sin' => 'test-cierre-sin-' . $marca,
    'asiento' => 'test-cierre-asiento-' . $marca,
    'valor' => 'test-cierre-valor-' . $marca,
    'contencion' => 'test-cierre-contencion-' . $marca,
];
$movimientos = [];
try {
    $insertar = $pdo->prepare(
        "INSERT INTO global_temp.bancos_movimiento_extracto
            (importacion_id,id_periodo,serial_seq,numero_fila_origen,fecha_operacion,
             referencia,descripcion,credito,debito,usuario_creacion,usuario_modificacion)
         VALUES
            (:importacion_id,:id_periodo,:serial_seq,:numero_fila,:fecha,
             :referencia,:descripcion,200,0,:usuario_id,:usuario_id)
         RETURNING id"
    );
    foreach (['SIN_ASOCIACION', 'ASIENTO', 'VALOR', 'CONTENCION'] as $indice => $caso) {
        $numeroFila = random_int(1000000, 1900000000);
        $insertar->execute([
            ':importacion_id' => $importacion['id'],
            ':id_periodo' => $importacion['id_periodo'],
            ':serial_seq' => $numeroFila,
            ':numero_fila' => $numeroFila,
            ':fecha' => $importacion['inicio_periodo'],
            ':referencia' => 'TEST-CIERRE-' . $marca . '-' . $indice,
            ':descripcion' => $caso,
            ':usuario_id' => $usuarioId,
        ]);
        $movimientos[$caso] = (int) $insertar->fetchColumn();
    }
    $estadoParaCerrar = (int) $pdo->query(
        "SELECT id FROM global_temp.bancos_estado WHERE codigo='PARA_CERRAR'"
    )->fetchColumn();
    $estado = $pdo->prepare(
        "INSERT INTO global_temp.bancos_historial_asignacion
            (movimiento_id,estado_id,usuario_hub_id,observacion)
         VALUES (:movimiento_id,:estado_id,:usuario_id,'TEST|CIERRE_SIN_ERP')"
    );
    foreach ($movimientos as $movimientoId) {
        $estado->execute([
            ':movimiento_id' => $movimientoId,
            ':estado_id' => $estadoParaCerrar,
            ':usuario_id' => $usuarioId,
        ]);
    }
    $asociarAsiento = $pdo->prepare(
        "INSERT INTO global_temp.bancos_asociacion_movimiento
            (movimiento_id,asiento_zetti_id,activo,compartido,observacion,usuario_creacion)
         VALUES (:movimiento_id,:recurso_id,true,false,'TEST|CIERRE_SIN_ERP',:usuario_id)"
    );
    $reservarAsiento = $pdo->prepare(
        "INSERT INTO global_temp.bancos_reserva_recurso
            (movimiento_id,asiento_zetti_id,activo,compartido,reservado_por,motivo)
         VALUES (:movimiento_id,:recurso_id,true,false,:usuario_id,'TEST|CIERRE_SIN_ERP')"
    );
    foreach ([$asociarAsiento, $reservarAsiento] as $consulta) {
        $consulta->execute([
            ':movimiento_id' => $movimientos['ASIENTO'],
            ':recurso_id' => $asientoId,
            ':usuario_id' => $usuarioId,
        ]);
    }
    $asociarValor = $pdo->prepare(
        "INSERT INTO global_temp.bancos_asociacion_movimiento
            (movimiento_id,valor_zetti_id,activo,observacion,usuario_creacion)
         VALUES (:movimiento_id,:recurso_id,true,'TEST|CIERRE_SIN_ERP',:usuario_id)"
    );
    $reservarValor = $pdo->prepare(
        "INSERT INTO global_temp.bancos_reserva_recurso
            (movimiento_id,valor_zetti_id,activo,reservado_por,motivo)
         VALUES (:movimiento_id,:recurso_id,true,:usuario_id,'TEST|CIERRE_SIN_ERP')"
    );
    foreach ([$asociarValor, $reservarValor] as $consulta) {
        $consulta->execute([
            ':movimiento_id' => $movimientos['VALOR'],
            ':recurso_id' => $valorId,
            ':usuario_id' => $usuarioId,
        ]);
    }

    $entrada = function ($movimientoId) use ($importacion) {
        return [
            'movimiento_id' => $movimientoId,
            'cuenta_bancaria_id' => (string) $importacion['cuenta_bancaria_zetti_id'],
            'inicio_periodo' => $importacion['inicio_periodo'],
        ];
    };
    $casoDeUso = casoCierreSinErp($pdo);
    $primera = $casoDeUso->ejecutar($entrada($movimientos['SIN_ASOCIACION']), $usuarioId, $claves['sin']);
    $repetida = $casoDeUso->ejecutar($entrada($movimientos['SIN_ASOCIACION']), $usuarioId, $claves['sin']);
    comprobarCierreSinErp($primera['repetida'] === false && $repetida['repetida'] === true, 'El cierre no fue idempotente.');
    comprobarCierreSinErp($primera['respuesta']['estado'] === 'CERRADO', 'El movimiento no quedo CERRADO.');
    comprobarCierreSinErp($primera['respuesta']['efectos_erp'] === 0, 'El cierre declaro efectos ERP.');

    $cierreAsiento = $casoDeUso->ejecutar($entrada($movimientos['ASIENTO']), $usuarioId, $claves['asiento']);
    comprobarCierreSinErp($cierreAsiento['respuesta']['estado'] === 'CERRADO', 'No se cerro el caso con asiento existente.');
    comprobarCierreSinErp(
        in_array('SIN_CAMBIOS', array_column($cierreAsiento['respuesta']['preflight']['efectos_previstos'], 'accion'), true),
        'El asiento existente no se mantuvo sin cambios.'
    );

    try {
        $casoDeUso->ejecutar($entrada($movimientos['VALOR']), $usuarioId, $claves['valor']);
        throw new RuntimeException('Se permitio un cierre que requiere modificar un valor ERP.');
    } catch (RecursoNoDisponibleException $esperada) {
    }
    $consulta = $pdo->prepare(
        "SELECT count(*) FROM global_temp.bancos_solicitud_idempotente
         WHERE operacion='movimiento.cerrar-sin-erp' AND clave=:clave"
    );
    $consulta->execute([':clave' => $claves['valor']]);
    comprobarCierreSinErp((int) $consulta->fetchColumn() === 0, 'El cierre bloqueado dejo una solicitud parcial.');

    $pdoBloqueo = conexionCierreSinErp();
    $pdoCompetidor = conexionCierreSinErp();
    $pdoBloqueo->beginTransaction();
    $bloquear = $pdoBloqueo->prepare(
        "SELECT id FROM global_temp.bancos_movimiento_extracto WHERE id=:id FOR UPDATE"
    );
    $bloquear->execute([':id' => $movimientos['CONTENCION']]);
    $pdoCompetidor->exec("SET lock_timeout TO '250ms'");
    try {
        casoCierreSinErp($pdoCompetidor)->ejecutar(
            $entrada($movimientos['CONTENCION']),
            $usuarioId,
            $claves['contencion']
        );
        throw new RuntimeException('La segunda conexion ignoro el bloqueo concurrente.');
    } catch (PDOException $esperada) {
        comprobarCierreSinErp($esperada->getCode() === '55P03', 'La contencion fallo por una causa inesperada.');
    }
    comprobarCierreSinErp(!$pdoCompetidor->inTransaction(), 'La contencion dejo una transaccion abierta.');
    $pdoBloqueo->rollBack();
    $cierreTrasContencion = casoCierreSinErp($pdoCompetidor)->ejecutar(
        $entrada($movimientos['CONTENCION']),
        $usuarioId,
        $claves['contencion']
    );
    comprobarCierreSinErp($cierreTrasContencion['respuesta']['estado'] === 'CERRADO', 'El reintento tras liberar el bloqueo no cerro.');

    $ids = implode(',', array_map('intval', array_values($movimientos)));
    $cierres = (int) $pdo->query(
        "SELECT count(*) FROM global_temp.bancos_historial_asignacion h
         JOIN global_temp.bancos_estado e ON e.id=h.estado_id
         WHERE h.movimiento_id IN ({$ids}) AND e.codigo='CERRADO'"
    )->fetchColumn();
    comprobarCierreSinErp($cierres === 3, 'La cantidad de cierres efectivos no coincide.');
    $consulta = $pdo->prepare("SELECT estado FROM public.valor WHERE id=:id");
    $consulta->execute([':id' => $valorId]);
    comprobarCierreSinErp((int) $consulta->fetchColumn() !== 7, 'El caso bloqueado modifico el valor ERP.');
} finally {
    if (isset($pdoBloqueo) && $pdoBloqueo->inTransaction()) {
        $pdoBloqueo->rollBack();
    }
    if ($movimientos) {
        $ids = implode(',', array_map('intval', array_values($movimientos)));
        $marcadores = [];
        $parametros = [];
        foreach (array_values($claves) as $indice => $clave) {
            $marcador = ':clave_' . $indice;
            $marcadores[] = $marcador;
            $parametros[$marcador] = $clave;
        }
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
        $pdo->commit();
    }
}

echo "Cierre mensual sin efectos ERP y contencion OK\n";
