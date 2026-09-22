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

function comprobarCierreCombinado($condicion, $mensaje)
{
    if (!$condicion) {
        throw new RuntimeException($mensaje);
    }
}

function casoCierreCombinado(PDO $pdo)
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
    "SELECT i.id,m.id_periodo,i.cuenta_bancaria_zetti_id,i.inicio_periodo
     FROM global_temp.bancos_importacion_extracto i
     JOIN global_temp.bancos_movimiento_extracto m ON m.importacion_id=i.id
     WHERE i.version_origen='FIXTURE-015' ORDER BY i.id,m.id LIMIT 1"
)->fetch(PDO::FETCH_ASSOC);
$valores = $pdo->query(
    "SELECT v.id::text,v.estado
     FROM public.valor v
     LEFT JOIN public.subtipo_valor s ON s.id=v.subtipo_valor
     WHERE v.estado NOT IN (7,20,36)
       AND abs(v.monto_principal)>=25
       AND upper(COALESCE(s.nombre,'')) NOT LIKE '%CHEQUE%'
       AND NOT EXISTS (SELECT 1 FROM global_temp.valor t WHERE t.id=v.id)
       AND NOT EXISTS (SELECT 1 FROM global_temp.bancos_asociacion_movimiento a WHERE a.valor_zetti_id=v.id AND a.activo)
       AND NOT EXISTS (SELECT 1 FROM global_temp.bancos_reserva_recurso r WHERE r.valor_zetti_id=v.id AND r.activo)
     ORDER BY v.id DESC LIMIT 2"
)->fetchAll(PDO::FETCH_ASSOC);
$nodoId = (int) $pdo->query('SELECT id FROM public.nodo ORDER BY id LIMIT 1')->fetchColumn();
$cuentas = $pdo->query('SELECT id FROM public.cuenta ORDER BY id LIMIT 2')->fetchAll(PDO::FETCH_COLUMN);
$asientoDesfasado = $pdo->query('SELECT id::text FROM global_temp.asiento ORDER BY id LIMIT 1')->fetchColumn();
comprobarCierreCombinado(
    $usuarioId > 0 && $importacion !== false && count($valores) === 2
    && $nodoId > 0 && count($cuentas) === 2 && $asientoDesfasado !== false,
    'Faltan datos base para probar el cierre combinado.'
);

$marca = bin2hex(random_bytes(6));
$claves = [
    'exito' => 'test-cierre-combinado-ok-' . $marca,
    'rollback' => 'test-cierre-combinado-rb-' . $marca,
];
$movimientos = [];
$borradores = [];
$asientoCreado = null;
$valorIds = array_column($valores, 'id');
try {
    $copiar = $pdo->prepare('INSERT INTO global_temp.valor SELECT * FROM public.valor WHERE id=:id');
    foreach ($valorIds as $valorId) {
        $copiar->execute([':id' => $valorId]);
    }
    $estadoAbierto = (int) $pdo->query("SELECT id FROM global_temp.bancos_estado WHERE codigo='ABIERTO'")->fetchColumn();
    $estadoParaCerrar = (int) $pdo->query("SELECT id FROM global_temp.bancos_estado WHERE codigo='PARA_CERRAR'")->fetchColumn();
    $insertarMovimiento = $pdo->prepare(
        "INSERT INTO global_temp.bancos_movimiento_extracto
            (importacion_id,id_periodo,serial_seq,numero_fila_origen,fecha_operacion,
             referencia,descripcion,credito,debito,usuario_creacion,usuario_modificacion)
         VALUES (:importacion,:periodo,:serial,:fila,:fecha,:referencia,:descripcion,25,0,:usuario,:usuario)
         RETURNING id"
    );
    $insertarBorrador = $pdo->prepare(
        "INSERT INTO global_temp.bancos_borrador_asiento
            (movimiento_id,nodo_zetti_id,fecha_contable,estado_id,asiento_zetti_id,
             clave_idempotencia,modelo,activo,usuario_creacion,usuario_modificacion)
         VALUES (:movimiento,:nodo,:fecha,:estado,:asiento,:clave,:modelo,true,:usuario,:usuario)
         RETURNING id"
    );
    $insertarLinea = $pdo->prepare(
        "INSERT INTO global_temp.bancos_linea_borrador_asiento
            (borrador_asiento_id,cuenta_zetti_id,debe,haber,observacion)
         VALUES (:borrador,:cuenta,:debe,:haber,'TEST|CIERRE_COMBINADO')"
    );
    $reservarValor = $pdo->prepare(
        "INSERT INTO global_temp.bancos_reserva_recurso
            (movimiento_id,valor_zetti_id,activo,reservado_por,motivo)
         VALUES (:movimiento,:valor,true,:usuario,'TEST|CIERRE_COMBINADO')"
    );
    $asociarValor = $pdo->prepare(
        "INSERT INTO global_temp.bancos_asociacion_movimiento
            (movimiento_id,valor_zetti_id,activo,observacion,usuario_creacion)
         VALUES (:movimiento,:valor,true,'TEST|CIERRE_COMBINADO',:usuario)"
    );
    $reservarBorrador = $pdo->prepare(
        "INSERT INTO global_temp.bancos_reserva_recurso
            (movimiento_id,borrador_asiento_id,activo,reservado_por,motivo)
         VALUES (:movimiento,:borrador,true,:usuario,'TEST|CIERRE_COMBINADO')"
    );
    $asociarBorrador = $pdo->prepare(
        "INSERT INTO global_temp.bancos_asociacion_movimiento
            (movimiento_id,borrador_asiento_id,activo,observacion,usuario_creacion)
         VALUES (:movimiento,:borrador,true,'TEST|CIERRE_COMBINADO',:usuario)"
    );
    $insertarEstado = $pdo->prepare(
        "INSERT INTO global_temp.bancos_historial_asignacion
            (movimiento_id,estado_id,usuario_hub_id,observacion)
         VALUES (:movimiento,:estado,:usuario,'TEST|CIERRE_COMBINADO')"
    );
    foreach ([['EXITO', null], ['ROLLBACK', $asientoDesfasado]] as $indice => $caso) {
        $fila = random_int(1000000, 1900000000);
        $insertarMovimiento->execute([
            ':importacion' => $importacion['id'],
            ':periodo' => $importacion['id_periodo'],
            ':serial' => $fila,
            ':fila' => $fila,
            ':fecha' => $importacion['inicio_periodo'],
            ':referencia' => 'TEST-CIERRE-COMBINADO-' . $marca . '-' . $indice,
            ':descripcion' => $caso[0],
            ':usuario' => $usuarioId,
        ]);
        $movimientoId = (int) $insertarMovimiento->fetchColumn();
        $movimientos[$caso[0]] = $movimientoId;
        $insertarBorrador->execute([
            ':movimiento' => $movimientoId,
            ':nodo' => $nodoId,
            ':fecha' => $importacion['inicio_periodo'],
            ':estado' => $estadoAbierto,
            ':asiento' => $caso[1],
            ':clave' => 'TEST|CIERRE_COMBINADO|' . $marca . '|' . $indice,
            ':modelo' => 'TEST COMBINADO ' . $caso[0],
            ':usuario' => $usuarioId,
        ]);
        $borradorId = (int) $insertarBorrador->fetchColumn();
        $borradores[] = $borradorId;
        $insertarLinea->execute([':borrador' => $borradorId, ':cuenta' => $cuentas[0], ':debe' => '25.00000', ':haber' => '0']);
        $insertarLinea->execute([':borrador' => $borradorId, ':cuenta' => $cuentas[1], ':debe' => '0', ':haber' => '25.00000']);
        $comunes = [':movimiento' => $movimientoId, ':usuario' => $usuarioId];
        $reservarValor->execute($comunes + [':valor' => $valorIds[$indice]]);
        $asociarValor->execute($comunes + [':valor' => $valorIds[$indice]]);
        $reservarBorrador->execute($comunes + [':borrador' => $borradorId]);
        $asociarBorrador->execute($comunes + [':borrador' => $borradorId]);
        $insertarEstado->execute($comunes + [':estado' => $estadoParaCerrar]);
    }

    $entrada = function ($id) use ($importacion) {
        return [
            'movimiento_id' => $id,
            'cuenta_bancaria_id' => (string) $importacion['cuenta_bancaria_zetti_id'],
            'inicio_periodo' => $importacion['inicio_periodo'],
        ];
    };
    $casoDeUso = casoCierreCombinado($pdo);
    $primera = $casoDeUso->ejecutar($entrada($movimientos['EXITO']), $usuarioId, $claves['exito']);
    $repetida = $casoDeUso->ejecutar($entrada($movimientos['EXITO']), $usuarioId, $claves['exito']);
    comprobarCierreCombinado(!$primera['repetida'] && $repetida['repetida'], 'El cierre combinado no fue idempotente.');
    comprobarCierreCombinado($primera['respuesta']['alcance'] === 'VALOR_Y_BORRADOR_ASIENTO', 'El alcance combinado es incorrecto.');
    comprobarCierreCombinado($primera['respuesta']['efectos_erp'] === 2, 'No se informaron los dos efectos ERP.');
    comprobarCierreCombinado(
        array_column($primera['respuesta']['efectos_aplicados'], 'tipo') === ['VALOR', 'BORRADOR_ASIENTO'],
        'Los efectos combinados no se ejecutaron en el orden previsto.'
    );
    $asientoCreado = $primera['respuesta']['efectos_aplicados'][1]['resultado']['asiento_zetti_id'];
    $estadoValor = $pdo->prepare('SELECT estado FROM global_temp.valor WHERE id=:id');
    $estadoValor->execute([':id' => $valorIds[0]]);
    comprobarCierreCombinado((int) $estadoValor->fetchColumn() === 7, 'El valor del cierre combinado no fue liquidado.');

    try {
        $casoDeUso->ejecutar($entrada($movimientos['ROLLBACK']), $usuarioId, $claves['rollback']);
        throw new RuntimeException('Se acepto un asiento materializado distinto del borrador.');
    } catch (RecursoNoDisponibleException $esperada) {
    }
    $estadoValor->execute([':id' => $valorIds[1]]);
    comprobarCierreCombinado(
        (int) $estadoValor->fetchColumn() === (int) $valores[1]['estado'],
        'El fallo del borrador no revirtio la liquidacion previa del valor.'
    );
    $solicitud = $pdo->prepare(
        "SELECT count(*) FROM global_temp.bancos_solicitud_idempotente
         WHERE operacion='movimiento.cerrar' AND clave=:clave"
    );
    $solicitud->execute([':clave' => $claves['rollback']]);
    comprobarCierreCombinado((int) $solicitud->fetchColumn() === 0, 'El rollback combinado dejo idempotencia parcial.');
} finally {
    if ($movimientos) {
        $ids = implode(',', array_map('intval', array_values($movimientos)));
        $borradorIds = implode(',', array_map('intval', $borradores));
        $pdo->beginTransaction();
        $borrar = $pdo->prepare(
            "DELETE FROM global_temp.bancos_evento_auditoria WHERE solicitud_id IN (
                SELECT id FROM global_temp.bancos_solicitud_idempotente WHERE clave IN (:exito,:rollback)
             )"
        );
        $borrar->execute([':exito' => $claves['exito'], ':rollback' => $claves['rollback']]);
        $borrar = $pdo->prepare('DELETE FROM global_temp.bancos_solicitud_idempotente WHERE clave IN (:exito,:rollback)');
        $borrar->execute([':exito' => $claves['exito'], ':rollback' => $claves['rollback']]);
        $pdo->exec("DELETE FROM global_temp.bancos_reserva_recurso WHERE movimiento_id IN ({$ids})");
        $pdo->exec("DELETE FROM global_temp.bancos_asociacion_movimiento WHERE movimiento_id IN ({$ids})");
        $pdo->exec("DELETE FROM global_temp.bancos_historial_asignacion WHERE movimiento_id IN ({$ids})");
        if ($borradores) {
            $pdo->exec("DELETE FROM global_temp.bancos_linea_borrador_asiento WHERE borrador_asiento_id IN ({$borradorIds})");
            $pdo->exec("DELETE FROM global_temp.bancos_borrador_asiento WHERE id IN ({$borradorIds})");
        }
        $pdo->exec("DELETE FROM global_temp.bancos_movimiento_extracto WHERE id IN ({$ids})");
        if ($asientoCreado !== null) {
            $borrar = $pdo->prepare('DELETE FROM global_temp.movimiento WHERE asiento=:id');
            $borrar->execute([':id' => $asientoCreado]);
            $borrar = $pdo->prepare('DELETE FROM global_temp.asiento WHERE id=:id');
            $borrar->execute([':id' => $asientoCreado]);
        }
        $borrar = $pdo->prepare('DELETE FROM global_temp.valor WHERE id IN (:valor_0,:valor_1)');
        $borrar->execute([':valor_0' => $valorIds[0], ':valor_1' => $valorIds[1]]);
        $pdo->commit();
    }
}

echo "Cierre combinado valor y borrador con rollback atomico OK\n";
