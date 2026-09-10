<?php

require_once dirname(__DIR__) . '/src/config.php';
require_once RUTA . '/src/autoload.php';

use AppBancos\Application\CrearBorradorMovimientoMensual;
use AppBancos\Application\EjecutorComandoIdempotente;
use AppBancos\Application\RecursoNoDisponibleException;
use AppBancos\Infrastructure\EsquemaBancos;
use AppBancos\Repository\AuditoriaRepository;
use AppBancos\Repository\IdempotenciaRepository;
use AppBancos\Repository\MovimientoMensualRepository;

function comprobarBorrador($condicion, $mensaje)
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
$nodoId = (int) $pdo->query('SELECT id FROM public.nodo ORDER BY id LIMIT 1')->fetchColumn();
$cuentas = $pdo->query('SELECT id FROM public.cuenta ORDER BY id LIMIT 2')->fetchAll(PDO::FETCH_COLUMN);
comprobarBorrador(
    $usuarioId > 0 && $nodoId > 0 && count($cuentas) === 2 && $importacion !== false,
    'Faltan datos base para probar el borrador.'
);

$marca = bin2hex(random_bytes(8));
$clave = 'test-crear-borrador-' . $marca;
$claveSegunda = 'test-borrador-segundo-' . $marca;
$numeroFila = random_int(1000000, 1900000000);
$movimientoId = null;
$borradorId = null;

try {
    $consulta = $pdo->prepare(
        "INSERT INTO global_temp.bancos_movimiento_extracto
            (importacion_id, id_periodo, serial_seq, numero_fila_origen, fecha_operacion,
             referencia, descripcion, credito, debito, usuario_creacion, usuario_modificacion)
         VALUES
            (:importacion_id, :id_periodo, :serial_seq, :numero_fila, :fecha,
             :referencia, 'Prueba aislada de borrador', 10.00, 0, :usuario_id, :usuario_id)
         RETURNING id"
    );
    $consulta->execute([
        ':importacion_id' => $importacion['id'],
        ':id_periodo' => $importacion['id_periodo'],
        ':serial_seq' => $numeroFila,
        ':numero_fila' => $numeroFila,
        ':fecha' => $importacion['inicio_periodo'],
        ':referencia' => 'TEST-BORRADOR-' . $marca,
        ':usuario_id' => $usuarioId,
    ]);
    $movimientoId = (int) $consulta->fetchColumn();

    $esquema = EsquemaBancos::desdeConfiguracion(['bancos_debug' => true]);
    $casoDeUso = new CrearBorradorMovimientoMensual(
        new MovimientoMensualRepository($pdo, $esquema),
        new EjecutorComandoIdempotente(
            $pdo,
            new IdempotenciaRepository($pdo, $esquema),
            new AuditoriaRepository($pdo, $esquema)
        )
    );
    $entrada = [
        'movimiento_id' => $movimientoId,
        'cuenta_bancaria_id' => (string) $importacion['cuenta_bancaria_zetti_id'],
        'inicio_periodo' => $importacion['inicio_periodo'],
        'nodo_zetti_id' => $nodoId,
        'fecha_contable' => $importacion['inicio_periodo'],
        'modelo' => 'TEST BALANCEADO',
        'lineas' => [
            ['cuenta_zetti_id' => $cuentas[0], 'debe' => '10,12345', 'haber' => '0', 'observacion' => 'Debe'],
            ['cuenta_zetti_id' => $cuentas[1], 'debe' => '0', 'haber' => '10.12345', 'observacion' => 'Haber'],
        ],
    ];
    $desbalanceada = $entrada;
    $desbalanceada['lineas'][1]['haber'] = '10.12344';
    try {
        $casoDeUso->ejecutar($desbalanceada, $usuarioId, 'test-desbalanceado-' . $marca);
        throw new RuntimeException('Se permitio crear un borrador desbalanceado.');
    } catch (InvalidArgumentException $esperada) {
    }

    $primera = $casoDeUso->ejecutar($entrada, $usuarioId, $clave);
    $reintento = $casoDeUso->ejecutar($entrada, $usuarioId, $clave);
    comprobarBorrador($primera['repetida'] === false && $reintento['repetida'] === true, 'El borrador no fue idempotente.');
    comprobarBorrador($primera['codigo_http'] === 201, 'La creacion no devolvio HTTP 201.');
    comprobarBorrador($primera['respuesta']['cantidad_lineas'] === 2, 'La respuesta perdio las lineas.');
    $borradorId = $primera['respuesta']['borrador_id'];

    $consulta = $pdo->prepare(
        "SELECT b.activo, count(l.id) lineas, sum(l.debe) debe, sum(l.haber) haber
         FROM global_temp.bancos_borrador_asiento b
         JOIN global_temp.bancos_linea_borrador_asiento l ON l.borrador_asiento_id = b.id
         WHERE b.id = :id
         GROUP BY b.activo"
    );
    $consulta->execute([':id' => $borradorId]);
    $guardado = $consulta->fetch(PDO::FETCH_ASSOC);
    comprobarBorrador($guardado !== false && (bool) $guardado['activo'], 'El borrador no quedo activo.');
    comprobarBorrador((int) $guardado['lineas'] === 2, 'No se guardaron exactamente dos lineas.');
    comprobarBorrador($guardado['debe'] === $guardado['haber'], 'El borrador persistido no esta balanceado.');

    foreach (['bancos_reserva_recurso', 'bancos_asociacion_movimiento'] as $tabla) {
        $consulta = $pdo->prepare(
            "SELECT count(*) FROM global_temp.{$tabla}
             WHERE movimiento_id = :movimiento_id AND borrador_asiento_id = :borrador_id AND activo"
        );
        $consulta->execute([':movimiento_id' => $movimientoId, ':borrador_id' => $borradorId]);
        comprobarBorrador((int) $consulta->fetchColumn() === 1, 'Falta la reserva o asociacion del borrador.');
    }

    try {
        $casoDeUso->ejecutar($entrada, $usuarioId, $claveSegunda);
        throw new RuntimeException('Se permitio crear un segundo borrador activo.');
    } catch (RecursoNoDisponibleException $esperada) {
    }

    $consulta = $pdo->prepare(
        "SELECT detalles->>'total_debe', detalles->>'total_haber', detalles->>'borrador_id'
         FROM global_temp.bancos_evento_auditoria a
         JOIN global_temp.bancos_solicitud_idempotente s ON s.id = a.solicitud_id
         WHERE s.operacion = 'movimiento.crear-borrador' AND s.clave = :clave"
    );
    $consulta->execute([':clave' => $clave]);
    $auditoria = $consulta->fetch(PDO::FETCH_NUM);
    comprobarBorrador(
        $auditoria !== false && $auditoria[0] === '10.12345' && $auditoria[1] === '10.12345'
        && (int) $auditoria[2] === $borradorId,
        'La auditoria no conservo balance e identificadores.'
    );
} finally {
    if ($movimientoId !== null) {
        $pdo->beginTransaction();
        $consulta = $pdo->prepare(
            "DELETE FROM global_temp.bancos_evento_auditoria
             WHERE solicitud_id IN (
                 SELECT id FROM global_temp.bancos_solicitud_idempotente
                 WHERE clave IN (:clave, :segunda)
             )"
        );
        $consulta->execute([':clave' => $clave, ':segunda' => $claveSegunda]);
        $consulta = $pdo->prepare(
            "DELETE FROM global_temp.bancos_solicitud_idempotente WHERE clave IN (:clave, :segunda)"
        );
        $consulta->execute([':clave' => $clave, ':segunda' => $claveSegunda]);
        foreach (['bancos_asociacion_movimiento', 'bancos_reserva_recurso'] as $tabla) {
            $consulta = $pdo->prepare("DELETE FROM global_temp.{$tabla} WHERE movimiento_id = :id");
            $consulta->execute([':id' => $movimientoId]);
        }
        if ($borradorId !== null) {
            $consulta = $pdo->prepare('DELETE FROM global_temp.bancos_linea_borrador_asiento WHERE borrador_asiento_id = :id');
            $consulta->execute([':id' => $borradorId]);
            $consulta = $pdo->prepare('DELETE FROM global_temp.bancos_borrador_asiento WHERE id = :id');
            $consulta->execute([':id' => $borradorId]);
        }
        $consulta = $pdo->prepare('DELETE FROM global_temp.bancos_movimiento_extracto WHERE id = :id');
        $consulta->execute([':id' => $movimientoId]);
        $pdo->commit();
    }
}

echo "Creacion de borrador multlinea debug OK\n";
