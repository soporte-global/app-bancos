<?php

require_once dirname(__DIR__) . '/src/config.php';
require_once RUTA . '/src/autoload.php';

use AppBancos\Application\PrevalidarConciliacionCheque;
use AppBancos\Infrastructure\EsquemaBancos;
use AppBancos\Repository\PreflightConciliacionChequeRepository;

function comprobarPreflightCheque($condicion, $mensaje)
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
    "SELECT i.id, i.configuracion_id, m.id_periodo, i.cuenta_bancaria_zetti_id, i.inicio_periodo
     FROM global_temp.bancos_importacion_extracto i
     JOIN global_temp.bancos_movimiento_extracto m ON m.importacion_id = i.id
     WHERE i.version_origen = 'FIXTURE-015'
     ORDER BY i.id, m.id LIMIT 1"
)->fetch(PDO::FETCH_ASSOC);
comprobarPreflightCheque($usuarioId > 0 && $importacion !== false, 'Faltan datos base para el preflight de cheque.');

$buscarCheque = $pdo->prepare(
    "SELECT v.id::text, v.monto_principal::text, v.estado
     FROM public.valor v
     WHERE v.subtipo_valor IN (13, 14, 10070, 10071)
       AND v.estado = :estado
       AND NOT EXISTS (
           SELECT 1 FROM global_temp.bancos_asociacion_movimiento a
           WHERE a.valor_zetti_id = v.id AND a.activo
       )
       AND NOT EXISTS (
           SELECT 1 FROM global_temp.bancos_reserva_recurso r
           WHERE r.valor_zetti_id = v.id AND r.activo
       )
       AND NOT EXISTS (
           SELECT 1 FROM global_temp.bancos_conciliacion_cheque c
           WHERE c.valor_origen_zetti_id = v.id
       )
     ORDER BY v.id DESC LIMIT 1"
);
$buscarCheque->execute([':estado' => 43]);
$pendiente = $buscarCheque->fetch(PDO::FETCH_ASSOC);
if ($pendiente === false) {
    $pendiente = $pdo->query(
        "SELECT v.id::text, v.monto_principal::text, v.estado
         FROM public.valor v
         WHERE v.subtipo_valor IN (13, 14, 10070, 10071)
           AND v.estado NOT IN (7, 20, 36)
           AND NOT EXISTS (SELECT 1 FROM global_temp.bancos_asociacion_movimiento a WHERE a.valor_zetti_id=v.id AND a.activo)
           AND NOT EXISTS (SELECT 1 FROM global_temp.bancos_reserva_recurso r WHERE r.valor_zetti_id=v.id AND r.activo)
         ORDER BY v.id DESC LIMIT 1"
    )->fetch(PDO::FETCH_ASSOC);
}
$buscarCheque->execute([':estado' => 7]);
$liquidado = $buscarCheque->fetch(PDO::FETCH_ASSOC);
$buscarCheque->execute([':estado' => 36]);
$excluido = $buscarCheque->fetch(PDO::FETCH_ASSOC);
comprobarPreflightCheque(
    $pendiente !== false && $liquidado !== false && $excluido !== false,
    'No se encontraron cheques ERP para cubrir los estados del contrato.'
);

$pdo->beginTransaction();
try {
    $marca = bin2hex(random_bytes(6));
    $consultaCuentas = $pdo->prepare(
        "SELECT c.id::text
         FROM public.cuenta c
         WHERE c.imputable IS TRUE
         ORDER BY c.id LIMIT 2"
    );
    $consultaCuentas->execute();
    $cuentasContables = $consultaCuentas->fetchAll(PDO::FETCH_COLUMN);
    comprobarPreflightCheque(count($cuentasContables) === 2, 'Faltan cuentas contables imputables para la prueba.');
    $actualizarConfiguracion = $pdo->prepare(
        "UPDATE global_temp.bancos_configuracion
         SET cuenta_contable_zetti_id = :cuenta_id
         WHERE id = :configuracion_id"
    );
    $actualizarConfiguracion->execute([
        ':cuenta_id' => $cuentasContables[0],
        ':configuracion_id' => $importacion['configuracion_id'],
    ]);
    $configuracionId = (int) $importacion['configuracion_id'];
    $insertarMapeo = $pdo->prepare(
        "INSERT INTO global_temp.bancos_mapeo_cuenta_contable
            (configuracion_id, subtipo_valor_zetti_id, cuenta_zetti_id, activo, version, origen, observacion)
         VALUES (:configuracion_id, :subtipo_id, :cuenta_id, true, 1, 'MANUAL', 'TEST|PREFLIGHT-CHEQUE')"
    );
    $insertarMapeo->execute([
        ':configuracion_id' => $configuracionId,
        ':subtipo_id' => (int) $pdo->query(
            'SELECT subtipo_valor FROM public.valor WHERE id = ' . $pdo->quote($pendiente['id'])
        )->fetchColumn(),
        ':cuenta_id' => $cuentasContables[1],
    ]);
    $insertarMovimiento = $pdo->prepare(
        "INSERT INTO global_temp.bancos_movimiento_extracto
            (importacion_id, id_periodo, serial_seq, numero_fila_origen, fecha_operacion,
             referencia, descripcion, credito, debito, usuario_creacion, usuario_modificacion)
         VALUES
            (:importacion_id, :id_periodo, :serial_seq, :numero_fila, :fecha,
             :referencia, :descripcion, :monto, 0, :usuario_id, :usuario_id)
         RETURNING id"
    );
    $insertarEstado = $pdo->prepare(
        "INSERT INTO global_temp.bancos_historial_asignacion
            (movimiento_id, estado_id, usuario_hub_id, observacion)
         VALUES (:movimiento_id, :estado_id, :usuario_id, 'TEST|PREFLIGHT-CHEQUE')"
    );
    $insertarAsociacion = $pdo->prepare(
        "INSERT INTO global_temp.bancos_asociacion_movimiento
            (movimiento_id, valor_zetti_id, activo, compartido, observacion, usuario_creacion)
         VALUES (:movimiento_id, :valor_id, true, false, 'TEST|PREFLIGHT-CHEQUE', :usuario_id)"
    );
    $insertarReserva = $pdo->prepare(
        "INSERT INTO global_temp.bancos_reserva_recurso
            (movimiento_id, valor_zetti_id, activo, compartido, reservado_por, motivo)
         VALUES (:movimiento_id, :valor_id, true, false, :usuario_id, 'TEST|PREFLIGHT-CHEQUE')"
    );
    $estadoParaCerrar = (int) $pdo->query(
        "SELECT id FROM global_temp.bancos_estado WHERE codigo = 'PARA_CERRAR'"
    )->fetchColumn();
    $movimientos = [];
    foreach (['PENDIENTE' => $pendiente, 'LIQUIDADO' => $liquidado, 'EXCLUIDO' => $excluido] as $indice => $valor) {
        $numeroFila = random_int(1000000, 1900000000);
        $insertarMovimiento->execute([
            ':importacion_id' => $importacion['id'],
            ':id_periodo' => $importacion['id_periodo'],
            ':serial_seq' => $numeroFila,
            ':numero_fila' => $numeroFila,
            ':fecha' => $importacion['inicio_periodo'],
            ':referencia' => 'TEST-CHEQUE-' . $marca . '-' . $indice,
            ':descripcion' => 'Preflight cheque ' . $indice,
            ':monto' => $valor['monto_principal'],
            ':usuario_id' => $usuarioId,
        ]);
        $movimientoId = (int) $insertarMovimiento->fetchColumn();
        $movimientos[$indice] = $movimientoId;
        $insertarEstado->execute([
            ':movimiento_id' => $movimientoId,
            ':estado_id' => $estadoParaCerrar,
            ':usuario_id' => $usuarioId,
        ]);
        $insertarAsociacion->execute([
            ':movimiento_id' => $movimientoId,
            ':valor_id' => $valor['id'],
            ':usuario_id' => $usuarioId,
        ]);
        $insertarReserva->execute([
            ':movimiento_id' => $movimientoId,
            ':valor_id' => $valor['id'],
            ':usuario_id' => $usuarioId,
        ]);
    }

    $ids = implode(',', array_map('intval', array_values($movimientos)));
    $contar = static function () use ($pdo, $ids) {
        return [
            'asociaciones' => (int) $pdo->query(
                "SELECT count(*) FROM global_temp.bancos_asociacion_movimiento WHERE movimiento_id IN ({$ids})"
            )->fetchColumn(),
            'reservas' => (int) $pdo->query(
                "SELECT count(*) FROM global_temp.bancos_reserva_recurso WHERE movimiento_id IN ({$ids})"
            )->fetchColumn(),
            'conciliaciones' => (int) $pdo->query(
                "SELECT count(*) FROM global_temp.bancos_conciliacion_cheque WHERE movimiento_id IN ({$ids})"
            )->fetchColumn(),
        ];
    };
    $antes = $contar();
    $casoDeUso = new PrevalidarConciliacionCheque(
        new PreflightConciliacionChequeRepository(
            $pdo,
            EsquemaBancos::desdeConfiguracion(['bancos_debug' => true])
        )
    );
    $contexto = [
        'cuenta_bancaria_id' => (string) $importacion['cuenta_bancaria_zetti_id'],
        'inicio_periodo' => $importacion['inicio_periodo'],
    ];

    $listo = $casoDeUso->ejecutar(array_merge($contexto, ['movimiento_id' => $movimientos['PENDIENTE']]));
    comprobarPreflightCheque($listo['resultado'] === 'LISTO_CON_ADVERTENCIAS', 'Se rechazo un cheque conciliable.');
    comprobarPreflightCheque($listo['listo_para_conciliar'] === true, 'El cheque pendiente no quedo listo para conciliar.');
    comprobarPreflightCheque($listo['ejecutable_ahora'] === false, 'El preflight habilito una escritura ERP inexistente.');
    comprobarPreflightCheque($listo['cheque']['diferencia'] === '0.00000', 'La diferencia del cheque no fue calculada correctamente.');
    comprobarPreflightCheque(count($listo['efectos_previstos']) === 8, 'El plan de efectos de conciliacion esta incompleto.');
    comprobarPreflightCheque(
        $listo['contexto_contable']['cuenta_banco']['cuenta_zetti_id'] === $cuentasContables[0]
        && $listo['contexto_contable']['cuenta_valor']['cuenta_zetti_id'] === $cuentasContables[1],
        'El preflight no resolvio las dos cuentas contables configuradas.'
    );
    comprobarPreflightCheque(
        $listo['politica_diferencia']['tratamiento'] === 'SIN_DIFERENCIA'
        && $listo['politica_diferencia']['dentro_tolerancia_legacy'] === true,
        'La politica de diferencia exacta es incorrecta.'
    );
    comprobarPreflightCheque(
        in_array('CREAR_OPERACION_CONCILIACION', array_column($listo['efectos_previstos'], 'accion'), true),
        'El plan no declara la operacion ERP de conciliacion.'
    );

    $pdo->exec(
        'UPDATE global_temp.bancos_movimiento_extracto SET credito = credito - 1 '
        . 'WHERE id = ' . (int) $movimientos['PENDIENTE']
    );
    $conDiferencia = $casoDeUso->ejecutar(array_merge($contexto, ['movimiento_id' => $movimientos['PENDIENTE']]));
    comprobarPreflightCheque($conDiferencia['resultado'] === 'BLOQUEADO', 'Se admitio una diferencia contable sin regla.');
    comprobarPreflightCheque(
        in_array('DIFERENCIA_CONTABLE_NO_DEFINIDA', array_column($conDiferencia['bloqueos'], 'codigo'), true),
        'La diferencia contable no quedo explicada por el preflight.'
    );
    comprobarPreflightCheque(
        $conDiferencia['politica_diferencia']['tratamiento'] === 'BLOQUEAR_HASTA_DEFINIR',
        'La politica no bloqueo la diferencia pendiente de definicion.'
    );

    $yaLiquidado = $casoDeUso->ejecutar(array_merge($contexto, ['movimiento_id' => $movimientos['LIQUIDADO']]));
    comprobarPreflightCheque($yaLiquidado['resultado'] === 'NO_REQUIERE_CONCILIACION', 'Se intento reconciliar un cheque ya liquidado.');
    comprobarPreflightCheque(
        in_array('CHEQUE_YA_LIQUIDADO', array_column($yaLiquidado['advertencias'], 'codigo'), true),
        'No se explico que el cheque ya estaba liquidado.'
    );

    $bloqueado = $casoDeUso->ejecutar(array_merge($contexto, ['movimiento_id' => $movimientos['EXCLUIDO']]));
    comprobarPreflightCheque($bloqueado['resultado'] === 'BLOQUEADO', 'Se admitio un cheque en estado ERP excluido.');
    comprobarPreflightCheque(
        in_array('CHEQUE_ESTADO_NO_CONCILIABLE', array_column($bloqueado['bloqueos'], 'codigo'), true),
        'El bloqueo no identifica el estado ERP no conciliable.'
    );

    comprobarPreflightCheque($contar() === $antes, 'El preflight modifico tablas operativas.');
    $estadoOrigen = (int) $pdo->query(
        'SELECT estado FROM public.valor WHERE id = ' . $pdo->quote($pendiente['id'])
    )->fetchColumn();
    comprobarPreflightCheque($estadoOrigen === (int) $pendiente['estado'], 'El preflight modifico el cheque ERP.');
    comprobarPreflightCheque($listo['solo_lectura'] === true, 'El contrato no declara que es de solo lectura.');

    $pdo->rollBack();
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $error;
}

echo "Preflight de conciliacion de cheque de solo lectura OK\n";
