<?php

require_once dirname(__DIR__) . '/src/config.php';
require_once RUTA . '/src/autoload.php';

use AppBancos\Application\ConciliarCheque;
use AppBancos\Application\EjecutorComandoIdempotente;
use AppBancos\Application\RecursoNoDisponibleException;
use AppBancos\Infrastructure\EsquemaBancos;
use AppBancos\Repository\AuditoriaRepository;
use AppBancos\Repository\CierreMovimientoRepository;
use AppBancos\Repository\ConciliacionChequeErpGateway;
use AppBancos\Repository\IdempotenciaRepository;
use AppBancos\Repository\PreflightCierreMovimientoRepository;
use AppBancos\Repository\PreflightConciliacionChequeRepository;

function comprobarConciliacionCheque($condicion, $mensaje)
{
    if (!$condicion) {
        throw new RuntimeException($mensaje);
    }
}

function casoConciliacionCheque(PDO $pdo, EsquemaBancos $esquema)
{
    return new ConciliarCheque(
        new CierreMovimientoRepository($pdo, $esquema),
        new PreflightConciliacionChequeRepository($pdo, $esquema),
        new ConciliacionChequeErpGateway($pdo, $esquema),
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
$base = $pdo->query(
    "SELECT m.id_periodo,i.inicio_periodo
     FROM global_temp.bancos_importacion_extracto i
     JOIN global_temp.bancos_movimiento_extracto m ON m.importacion_id=i.id
     WHERE i.version_origen='FIXTURE-015' ORDER BY i.id,m.id LIMIT 1"
)->fetch(PDO::FETCH_ASSOC);
$cheque = $pdo->query(
    "SELECT v.id::text,v.estado,v.monto_principal::text,v.moneda,v.subtipo_valor
     FROM public.valor v
     WHERE v.subtipo_valor IN (13,14,10070,10071) AND v.estado NOT IN (7,20,36)
       AND NOT EXISTS (SELECT 1 FROM global_temp.valor t WHERE t.id=v.id)
       AND NOT EXISTS (SELECT 1 FROM global_temp.bancos_asociacion_movimiento a WHERE a.valor_zetti_id=v.id AND a.activo)
     ORDER BY v.id DESC LIMIT 1"
)->fetch(PDO::FETCH_ASSOC);
$cuentaBancaria = $pdo->query(
    "SELECT cb.id::text,e.nodo_creacion
     FROM public.cuenta_bancaria cb JOIN public.entidad e ON e.id=cb.id
     WHERE e.nodo_creacion IS NOT NULL ORDER BY cb.id LIMIT 1"
)->fetch(PDO::FETCH_ASSOC);
$cuentas = $pdo->query(
    "SELECT id::text FROM public.cuenta WHERE imputable IS TRUE ORDER BY id LIMIT 2"
)->fetchAll(PDO::FETCH_COLUMN);
comprobarConciliacionCheque(
    $usuarioId > 0 && $base !== false && $cheque !== false && $cuentaBancaria !== false && count($cuentas) === 2,
    'Faltan datos base para probar la conciliacion de cheque.'
);

$marca = bin2hex(random_bytes(6));
$clave = 'test-conciliar-cheque-' . $marca;
$claveRollback = 'test-conciliar-rollback-' . $marca;
$claveMisma = 'test-conciliar-misma-' . $marca;
$claveContencion = 'test-conciliar-contencion-' . $marca;
$claveCompetidor = 'test-conciliar-competidor-' . $marca;
$funcionRollback = 'bancos_test_fallar_conciliacion_' . $marca;
$triggerRollback = 'bancos_test_fallar_conciliacion_' . $marca;
$configuracionId = null;
$mapeoId = null;
$importacionId = null;
$movimientoId = null;
$resultado = null;
try {
    $consulta = $pdo->prepare(
        "INSERT INTO global_temp.bancos_configuracion
            (alcance,nodo_zetti_id,moneda,activo,observacion,cuenta_contable_zetti_id)
         VALUES ('CUENTA',:nodo,:moneda,true,'TEST|CONCILIAR_CHEQUE',:cuenta)
         RETURNING id"
    );
    $consulta->execute([
        ':nodo' => (int) $cuentaBancaria['nodo_creacion'],
        ':moneda' => (int) $cheque['moneda'],
        ':cuenta' => $cuentas[0],
    ]);
    $configuracionId = (int) $consulta->fetchColumn();
    $consulta = $pdo->prepare(
        "INSERT INTO global_temp.bancos_mapeo_cuenta_contable
            (configuracion_id,subtipo_valor_zetti_id,cuenta_zetti_id,activo,version,origen,observacion)
         VALUES (:configuracion,:subtipo,:cuenta,true,1,'MANUAL','TEST|CONCILIAR_CHEQUE') RETURNING id"
    );
    $consulta->execute([
        ':configuracion' => $configuracionId,
        ':subtipo' => (int) $cheque['subtipo_valor'],
        ':cuenta' => $cuentas[1],
    ]);
    $mapeoId = (int) $consulta->fetchColumn();
    $estadoAbierto = (int) $pdo->query(
        "SELECT id FROM global_temp.bancos_estado WHERE codigo='ABIERTO'"
    )->fetchColumn();
    $consulta = $pdo->prepare(
        "INSERT INTO global_temp.bancos_importacion_extracto
            (configuracion_id,cuenta_bancaria_zetti_id,inicio_periodo,total_movimientos,
             estado_id,archivo_origen,version_origen,observacion,usuario_creacion,usuario_modificacion)
         VALUES (:configuracion,:cuenta,:periodo,1,:estado,:archivo,'TEST-CHEQUE-V1',
                 'TEST|CONCILIAR_CHEQUE',:usuario,:usuario) RETURNING id"
    );
    $consulta->execute([
        ':configuracion' => $configuracionId,
        ':cuenta' => $cuentaBancaria['id'],
        ':periodo' => $base['inicio_periodo'],
        ':estado' => $estadoAbierto,
        ':archivo' => 'test-cheque-' . $marca . '.csv',
        ':usuario' => $usuarioId,
    ]);
    $importacionId = (int) $consulta->fetchColumn();
    $numeroFila = random_int(1000000, 1900000000);
    $consulta = $pdo->prepare(
        "INSERT INTO global_temp.bancos_movimiento_extracto
            (importacion_id,id_periodo,serial_seq,numero_fila_origen,fecha_operacion,referencia,
             descripcion,credito,debito,moneda,subtipo_valor_zetti_id,usuario_creacion,usuario_modificacion)
         VALUES (:importacion,:id_periodo,:serial,:fila,:fecha,:referencia,'TEST CONCILIAR CHEQUE',
                 :monto,0,:moneda,:subtipo,:usuario,:usuario) RETURNING id"
    );
    $consulta->execute([
        ':importacion' => $importacionId,
        ':id_periodo' => $base['id_periodo'],
        ':serial' => $numeroFila,
        ':fila' => $numeroFila,
        ':fecha' => $base['inicio_periodo'],
        ':referencia' => 'TEST-CHEQUE-' . $marca,
        ':monto' => $cheque['monto_principal'],
        ':moneda' => (int) $cheque['moneda'],
        ':subtipo' => (int) $cheque['subtipo_valor'],
        ':usuario' => $usuarioId,
    ]);
    $movimientoId = (int) $consulta->fetchColumn();
    $pdo->prepare("INSERT INTO global_temp.valor SELECT v.* FROM public.valor v WHERE v.id=:id")
        ->execute([':id' => $cheque['id']]);
    $consulta = $pdo->prepare(
        "INSERT INTO global_temp.bancos_asociacion_movimiento
            (movimiento_id,valor_zetti_id,monto_asociado,activo,observacion,usuario_creacion)
         VALUES (:movimiento,:valor,:monto,true,'TEST|CONCILIAR_CHEQUE',:usuario)"
    );
    $consulta->execute([
        ':movimiento' => $movimientoId, ':valor' => $cheque['id'],
        ':monto' => $cheque['monto_principal'], ':usuario' => $usuarioId,
    ]);
    $consulta = $pdo->prepare(
        "INSERT INTO global_temp.bancos_reserva_recurso
            (movimiento_id,valor_zetti_id,activo,reservado_por,motivo)
         VALUES (:movimiento,:valor,true,:usuario,'TEST|CONCILIAR_CHEQUE')"
    );
    $consulta->execute([':movimiento' => $movimientoId, ':valor' => $cheque['id'], ':usuario' => $usuarioId]);
    $estadoParaCerrar = (int) $pdo->query(
        "SELECT id FROM global_temp.bancos_estado WHERE codigo='PARA_CERRAR'"
    )->fetchColumn();
    $consulta = $pdo->prepare(
        "INSERT INTO global_temp.bancos_historial_asignacion
            (movimiento_id,estado_id,usuario_hub_id,observacion)
         VALUES (:movimiento,:estado,:usuario,'TEST|CONCILIAR_CHEQUE')"
    );
    $consulta->execute([':movimiento' => $movimientoId, ':estado' => $estadoParaCerrar, ':usuario' => $usuarioId]);

    $esquema = EsquemaBancos::desdeConfiguracion(['bancos_debug' => true]);
    $preflight = new PreflightConciliacionChequeRepository($pdo, $esquema);
    $casoDeUso = casoConciliacionCheque($pdo, $esquema);
    $entrada = [
        'movimiento_id' => $movimientoId,
        'cuenta_bancaria_id' => $cuentaBancaria['id'],
        'inicio_periodo' => $base['inicio_periodo'],
    ];

    $pdo->exec(
        "CREATE FUNCTION global_temp.{$funcionRollback}() RETURNS trigger AS \$\$
         BEGIN
             RAISE EXCEPTION 'fallo deliberado de conciliacion';
         END
         \$\$ LANGUAGE plpgsql"
    );
    $pdo->exec(
        "CREATE TRIGGER {$triggerRollback}
         BEFORE INSERT ON global_temp.bancos_conciliacion_cheque
         FOR EACH ROW WHEN (NEW.clave_idempotencia = " . $pdo->quote($claveRollback) . ")
         EXECUTE PROCEDURE global_temp.{$funcionRollback}()"
    );
    try {
        $casoDeUso->ejecutar($entrada, $usuarioId, $claveRollback);
        throw new RuntimeException('La conciliacion ignoro el fallo deliberado del ultimo efecto.');
    } catch (PDOException $esperada) {
        comprobarConciliacionCheque(
            strpos($esperada->getMessage(), 'fallo deliberado de conciliacion') !== false,
            'El rollback fallo por una causa inesperada.'
        );
    }
    $pdo->exec("DROP TRIGGER {$triggerRollback} ON global_temp.bancos_conciliacion_cheque");
    $pdo->exec("DROP FUNCTION global_temp.{$funcionRollback}()");
    $consulta = $pdo->prepare(
        "SELECT
            (SELECT count(*) FROM global_temp.bancos_solicitud_idempotente WHERE clave=:clave),
            (SELECT count(*) FROM global_temp.bancos_conciliacion_cheque WHERE movimiento_id=:movimiento),
            (SELECT count(*) FROM global_temp.operacion WHERE observa=:observa),
            (SELECT count(*) FROM global_temp.valor WHERE codificacion=:observa),
            (SELECT count(*) FROM global_temp.asiento WHERE nombre=:observa)"
    );
    $consulta->execute([
        ':clave' => $claveRollback,
        ':movimiento' => $movimientoId,
        ':observa' => 'CONCILIACION BANCOS - TEST-CHEQUE-' . $marca,
    ]);
    $residuosRollback = array_map('intval', $consulta->fetch(PDO::FETCH_NUM));
    comprobarConciliacionCheque(
        $residuosRollback === [0, 0, 0, 0, 0],
        'El fallo final dejo idempotencia o efectos sandbox parciales.'
    );
    $consulta = $pdo->prepare('SELECT estado FROM global_temp.valor WHERE id=:id');
    $consulta->execute([':id' => $cheque['id']]);
    comprobarConciliacionCheque(
        (int) $consulta->fetchColumn() === (int) $cheque['estado'],
        'El rollback no restauro el estado sandbox del cheque.'
    );

    $pdoBloqueo = new PDO(
        'pgsql:host=' . HOST . ';port=' . PORT . ';dbname=' . DBASE,
        USER,
        PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $pdoCompetidor = new PDO(
        'pgsql:host=' . HOST . ';port=' . PORT . ';dbname=' . DBASE,
        USER,
        PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $pdoIdempotencia = new PDO(
        'pgsql:host=' . HOST . ';port=' . PORT . ';dbname=' . DBASE,
        USER,
        PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $pdoIdempotencia->beginTransaction();
    (new IdempotenciaRepository($pdoIdempotencia, $esquema))->iniciar(
        'cheque.conciliar',
        $claveMisma,
        $usuarioId,
        str_repeat('0', 64)
    );
    $pdoCompetidor->exec("SET lock_timeout TO '250ms'");
    try {
        casoConciliacionCheque($pdoCompetidor, $esquema)->ejecutar(
            $entrada,
            $usuarioId,
            $claveMisma
        );
        throw new RuntimeException('Dos conexiones procesaron simultaneamente la misma clave.');
    } catch (PDOException $esperada) {
        comprobarConciliacionCheque(
            $esperada->getCode() === '55P03',
            'La exclusion de una misma clave fallo por una causa inesperada.'
        );
    }
    comprobarConciliacionCheque(
        !$pdoCompetidor->inTransaction(),
        'La contencion idempotente dejo una transaccion abierta.'
    );
    $pdoIdempotencia->rollBack();
    $consulta = $pdo->prepare(
        "SELECT count(*) FROM global_temp.bancos_solicitud_idempotente WHERE clave=:clave"
    );
    $consulta->execute([':clave' => $claveMisma]);
    comprobarConciliacionCheque(
        (int) $consulta->fetchColumn() === 0,
        'La contencion idempotente dejo una solicitud parcial.'
    );

    $pdoBloqueo->beginTransaction();
    $bloqueoCheque = $pdoBloqueo->prepare('SELECT pg_advisory_xact_lock(CAST(:id AS bigint))');
    $bloqueoCheque->execute([':id' => $cheque['id']]);
    $pdoCompetidor->exec("SET lock_timeout TO '250ms'");
    try {
        casoConciliacionCheque($pdoCompetidor, $esquema)->ejecutar(
            $entrada,
            $usuarioId,
            $claveContencion
        );
        throw new RuntimeException('La solicitud competidora ignoro el bloqueo del cheque.');
    } catch (PDOException $esperada) {
        comprobarConciliacionCheque(
            $esperada->getCode() === '55P03',
            'La contencion del cheque fallo por una causa inesperada.'
        );
    }
    comprobarConciliacionCheque(
        !$pdoCompetidor->inTransaction(),
        'La solicitud contenida dejo una transaccion abierta.'
    );
    $consulta = $pdo->prepare(
        "SELECT count(*) FROM global_temp.bancos_solicitud_idempotente WHERE clave=:clave"
    );
    $consulta->execute([':clave' => $claveContencion]);
    comprobarConciliacionCheque(
        (int) $consulta->fetchColumn() === 0,
        'La solicitud contenida dejo idempotencia parcial.'
    );
    $pdoBloqueo->rollBack();

    $primera = $casoDeUso->ejecutar($entrada, $usuarioId, $clave);
    $repetida = $casoDeUso->ejecutar($entrada, $usuarioId, $clave);
    comprobarConciliacionCheque(!$primera['repetida'] && $repetida['repetida'], 'La conciliacion no fue idempotente.');
    comprobarConciliacionCheque($primera['codigo_http'] === 201, 'La conciliacion no devolvio HTTP 201.');
    $resultado = $primera['respuesta'];
    comprobarConciliacionCheque($resultado['efectos_erp'] === 8 && $resultado['sandbox'] === true, 'El resumen de efectos es incorrecto.');
    try {
        casoConciliacionCheque($pdoCompetidor, $esquema)->ejecutar(
            $entrada,
            $usuarioId,
            $claveCompetidor
        );
        throw new RuntimeException('Una segunda solicitud creo otra conciliacion para el mismo cheque.');
    } catch (RecursoNoDisponibleException $esperada) {
    }
    $consulta = $pdo->prepare(
        "SELECT
            (SELECT count(*) FROM global_temp.bancos_conciliacion_cheque
             WHERE movimiento_id=:movimiento OR valor_origen_zetti_id=:valor),
            (SELECT count(*) FROM global_temp.bancos_solicitud_idempotente WHERE clave=:clave)"
    );
    $consulta->execute([
        ':movimiento' => $movimientoId,
        ':valor' => $cheque['id'],
        ':clave' => $claveCompetidor,
    ]);
    $duplicados = array_map('intval', $consulta->fetch(PDO::FETCH_NUM));
    comprobarConciliacionCheque(
        $duplicados === [1, 0],
        'La exclusion concurrente dejo una conciliacion o solicitud duplicada.'
    );

    $consulta = $pdo->prepare(
        "SELECT o.tipo_operacion,o.estado_operacion,o.discriminador,o.usuario_creacion,
                a.fecha::date::text,a.numero,a.usuario_creacion asiento_usuario,
                count(m.id) lineas,
                sum(CASE WHEN m.debita THEN m.monto ELSE 0 END) debe,
                sum(CASE WHEN NOT m.debita THEN m.monto ELSE 0 END) haber
         FROM global_temp.operacion o
         JOIN global_temp.asiento a ON a.id=o.asiento
         JOIN global_temp.movimiento m ON m.asiento=a.id
         WHERE o.id=:operacion
         GROUP BY o.id,o.tipo_operacion,o.estado_operacion,o.discriminador,o.usuario_creacion,
                  a.id,a.fecha,a.numero,a.usuario_creacion"
    );
    $consulta->execute([':operacion' => $resultado['operacion_zetti_id']]);
    $contable = $consulta->fetch(PDO::FETCH_ASSOC);
    comprobarConciliacionCheque(
        (int) $contable['tipo_operacion'] === 101 && (int) $contable['estado_operacion'] === 1
        && (int) $contable['discriminador'] === 101,
        'La operacion no conserva el contrato legado 101/1/101.'
    );
    comprobarConciliacionCheque((int) $contable['lineas'] === 2 && $contable['debe'] === $contable['haber'], 'El asiento no tiene dos lineas balanceadas.');
    comprobarConciliacionCheque($contable['fecha'] === $base['inicio_periodo'], 'La fecha contable no coincide con el movimiento.');
    comprobarConciliacionCheque(
        $contable['numero'] === null && $contable['usuario_creacion'] === null && $contable['asiento_usuario'] === null,
        'Se invento numero o identidad ERP.'
    );
    $consulta = $pdo->prepare(
        "SELECT v.tipo_valor,v.subtipo_valor,v.estado,v.entidad::text,v.monto_principal,
                vc.concepto,vc.monto concepto_monto
         FROM global_temp.valor v JOIN global_temp.valor_concepto vc ON vc.valor=v.id
         WHERE v.id=:id"
    );
    $consulta->execute([':id' => $resultado['valor_resultante_zetti_id']]);
    $valorResultante = $consulta->fetch(PDO::FETCH_ASSOC);
    comprobarConciliacionCheque(
        (int) $valorResultante['tipo_valor'] === 124 && (int) $valorResultante['subtipo_valor'] === 79
        && (int) $valorResultante['estado'] === 4 && (int) $valorResultante['concepto'] === 376,
        'El valor resultante no conserva el contrato 124/79/4/376.'
    );
    comprobarConciliacionCheque($valorResultante['entidad'] === $cuentaBancaria['id'], 'El valor resultante no pertenece a la cuenta bancaria.');
    $consulta = $pdo->prepare('SELECT estado FROM global_temp.valor WHERE id=:id');
    $consulta->execute([':id' => $cheque['id']]);
    comprobarConciliacionCheque((int) $consulta->fetchColumn() === 7, 'El cheque sandbox no quedo liquidado.');
    $consulta = $pdo->prepare('SELECT estado FROM public.valor WHERE id=:id');
    $consulta->execute([':id' => $cheque['id']]);
    comprobarConciliacionCheque((int) $consulta->fetchColumn() === (int) $cheque['estado'], 'La conciliacion modifico public.valor.');

    $despues = $preflight->evaluar($movimientoId, $cuentaBancaria['id'], $base['inicio_periodo']);
    comprobarConciliacionCheque($despues['resultado'] === 'YA_CONCILIADO', 'El preflight no reconoce la conciliacion creada.');
    $cierre = (new PreflightCierreMovimientoRepository($pdo, $esquema))->evaluar(
        $movimientoId,
        $cuentaBancaria['id'],
        $base['inicio_periodo']
    );
    comprobarConciliacionCheque($cierre['ejecutable_ahora'] === true, 'El cierre no reconoce el cheque conciliado en sandbox.');
    comprobarConciliacionCheque(
        in_array('SIN_CAMBIOS_YA_CONCILIADO', array_column($cierre['efectos_previstos'], 'accion'), true),
        'El cierre no conserva evidencia de la conciliacion.'
    );
} finally {
    if (isset($pdoIdempotencia) && $pdoIdempotencia->inTransaction()) {
        $pdoIdempotencia->rollBack();
    }
    if (isset($pdoBloqueo) && $pdoBloqueo->inTransaction()) {
        $pdoBloqueo->rollBack();
    }
    try {
        $pdo->exec("DROP TRIGGER IF EXISTS {$triggerRollback} ON global_temp.bancos_conciliacion_cheque");
        $pdo->exec("DROP FUNCTION IF EXISTS global_temp.{$funcionRollback}()");
    } catch (Throwable $ignorada) {
    }
    if ($movimientoId !== null) {
        $pdo->beginTransaction();
        $pdo->prepare(
            "DELETE FROM global_temp.bancos_evento_auditoria WHERE solicitud_id IN (
                SELECT id FROM global_temp.bancos_solicitud_idempotente
                WHERE clave IN (:exito,:rollback,:misma,:contencion,:competidor))"
        )->execute([
            ':exito' => $clave,
            ':rollback' => $claveRollback,
            ':misma' => $claveMisma,
            ':contencion' => $claveContencion,
            ':competidor' => $claveCompetidor,
        ]);
        $pdo->prepare(
            'DELETE FROM global_temp.bancos_solicitud_idempotente
             WHERE clave IN (:exito,:rollback,:misma,:contencion,:competidor)'
        )->execute([
            ':exito' => $clave,
            ':rollback' => $claveRollback,
            ':misma' => $claveMisma,
            ':contencion' => $claveContencion,
            ':competidor' => $claveCompetidor,
        ]);
        $pdo->prepare('DELETE FROM global_temp.bancos_conciliacion_cheque WHERE movimiento_id=:id')
            ->execute([':id' => $movimientoId]);
        if (is_array($resultado)) {
            $pdo->prepare('DELETE FROM global_temp.operacion_valor WHERE operacion=:id')
                ->execute([':id' => $resultado['operacion_zetti_id']]);
            $pdo->prepare('DELETE FROM global_temp.valor_concepto WHERE valor=:id')
                ->execute([':id' => $resultado['valor_resultante_zetti_id']]);
            $pdo->prepare('DELETE FROM global_temp.movimiento WHERE asiento=:id')
                ->execute([':id' => $resultado['asiento_zetti_id']]);
            $pdo->prepare('DELETE FROM global_temp.operacion WHERE id=:id')
                ->execute([':id' => $resultado['operacion_zetti_id']]);
            $pdo->prepare('DELETE FROM global_temp.asiento WHERE id=:id')
                ->execute([':id' => $resultado['asiento_zetti_id']]);
            $pdo->prepare('DELETE FROM global_temp.valor WHERE id=:id')
                ->execute([':id' => $resultado['valor_resultante_zetti_id']]);
        }
        $pdo->prepare('DELETE FROM global_temp.bancos_reserva_recurso WHERE movimiento_id=:id')->execute([':id' => $movimientoId]);
        $pdo->prepare('DELETE FROM global_temp.bancos_asociacion_movimiento WHERE movimiento_id=:id')->execute([':id' => $movimientoId]);
        $pdo->prepare('DELETE FROM global_temp.bancos_historial_asignacion WHERE movimiento_id=:id')->execute([':id' => $movimientoId]);
        $pdo->prepare('DELETE FROM global_temp.bancos_movimiento_extracto WHERE id=:id')->execute([':id' => $movimientoId]);
        $pdo->prepare('DELETE FROM global_temp.valor WHERE id=:id')->execute([':id' => $cheque['id']]);
        if ($importacionId !== null) {
            $pdo->prepare('DELETE FROM global_temp.bancos_importacion_extracto WHERE id=:id')->execute([':id' => $importacionId]);
        }
        if ($mapeoId !== null) {
            $pdo->prepare('DELETE FROM global_temp.bancos_mapeo_cuenta_contable WHERE id=:id')->execute([':id' => $mapeoId]);
        }
        if ($configuracionId !== null) {
            $pdo->prepare('DELETE FROM global_temp.bancos_configuracion WHERE id=:id')->execute([':id' => $configuracionId]);
        }
        $pdo->commit();
    }
}

echo "Conciliacion transaccional de cheque en sandbox OK\n";
