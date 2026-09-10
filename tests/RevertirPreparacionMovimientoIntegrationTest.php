<?php

require_once dirname(__DIR__) . '/src/config.php';
require_once RUTA . '/src/autoload.php';

use AppBancos\Application\EjecutorComandoIdempotente;
use AppBancos\Application\RevertirPreparacionMovimientoMensual;
use AppBancos\Application\TransicionMovimientoException;
use AppBancos\Infrastructure\EsquemaBancos;
use AppBancos\Repository\AuditoriaRepository;
use AppBancos\Repository\IdempotenciaRepository;
use AppBancos\Repository\MovimientoMensualRepository;

function comprobarReversion($condicion, $mensaje)
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
$estadoParaCerrar = (int) $pdo->query(
    "SELECT id FROM global_temp.bancos_estado WHERE codigo = 'PARA_CERRAR'"
)->fetchColumn();
comprobarReversion(
    $usuarioId > 0 && $nodoId > 0 && $estadoParaCerrar > 0 && $importacion !== false,
    'Faltan datos base para probar la reversion.'
);

$marca = bin2hex(random_bytes(8));
$clave = 'test-revertir-' . $marca;
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
             :referencia, 'Prueba aislada de reversion', 321.45, 0, :usuario_id, :usuario_id)
         RETURNING id"
    );
    $consulta->execute([
        ':importacion_id' => $importacion['id'],
        ':id_periodo' => $importacion['id_periodo'],
        ':serial_seq' => $numeroFila,
        ':numero_fila' => $numeroFila,
        ':fecha' => $importacion['inicio_periodo'],
        ':referencia' => 'TEST-REVERTIR-' . $marca,
        ':usuario_id' => $usuarioId,
    ]);
    $movimientoId = (int) $consulta->fetchColumn();

    $consulta = $pdo->prepare(
        "INSERT INTO global_temp.bancos_historial_asignacion
            (movimiento_id, estado_id, usuario_hub_id, observacion)
         VALUES (:movimiento_id, :estado_id, :usuario_id, :observacion)"
    );
    $consulta->execute([
        ':movimiento_id' => $movimientoId,
        ':estado_id' => $estadoParaCerrar,
        ':usuario_id' => $usuarioId,
        ':observacion' => 'TEST|PREPARACION|' . $marca,
    ]);

    $consulta = $pdo->prepare(
        "INSERT INTO global_temp.bancos_borrador_asiento
            (movimiento_id, nodo_zetti_id, fecha_contable, estado_id, clave_idempotencia,
             modelo, usuario_creacion, usuario_modificacion)
         VALUES
            (:movimiento_id, :nodo_id, :fecha, :estado_id, :clave,
             'TEST-REVERSA', :usuario_id, :usuario_id)
         RETURNING id"
    );
    $consulta->execute([
        ':movimiento_id' => $movimientoId,
        ':nodo_id' => $nodoId,
        ':fecha' => $importacion['inicio_periodo'],
        ':estado_id' => $estadoParaCerrar,
        ':clave' => 'test-borrador-' . $marca,
        ':usuario_id' => $usuarioId,
    ]);
    $borradorId = (int) $consulta->fetchColumn();

    $consulta = $pdo->prepare(
        "INSERT INTO global_temp.bancos_asociacion_movimiento
            (movimiento_id, borrador_asiento_id, usuario_creacion, observacion)
         VALUES (:movimiento_id, :borrador_id, :usuario_id, :observacion)"
    );
    $consulta->execute([
        ':movimiento_id' => $movimientoId,
        ':borrador_id' => $borradorId,
        ':usuario_id' => $usuarioId,
        ':observacion' => 'TEST|REVERSA|' . $marca,
    ]);
    $consulta = $pdo->prepare(
        "INSERT INTO global_temp.bancos_reserva_recurso
            (movimiento_id, borrador_asiento_id, reservado_por, observacion)
         VALUES (:movimiento_id, :borrador_id, :usuario_id, :observacion)"
    );
    $consulta->execute([
        ':movimiento_id' => $movimientoId,
        ':borrador_id' => $borradorId,
        ':usuario_id' => $usuarioId,
        ':observacion' => 'TEST|REVERSA|' . $marca,
    ]);

    $esquema = EsquemaBancos::desdeConfiguracion(['bancos_debug' => true]);
    $casoDeUso = new RevertirPreparacionMovimientoMensual(
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
        'motivo' => 'La preparacion necesita correcciones',
    ];
    try {
        $entradaSinMotivo = $entrada;
        $entradaSinMotivo['motivo'] = '  ';
        $casoDeUso->ejecutar($entradaSinMotivo, $usuarioId, 'test-sin-motivo-' . $marca);
        throw new RuntimeException('Se permitio revertir sin motivo.');
    } catch (InvalidArgumentException $esperada) {
    }
    $primera = $casoDeUso->ejecutar($entrada, $usuarioId, $clave);
    $segunda = $casoDeUso->ejecutar($entrada, $usuarioId, $clave);
    comprobarReversion($primera['repetida'] === false && $segunda['repetida'] === true, 'La reversion no fue idempotente.');
    comprobarReversion($primera['respuesta']['estado'] === 'ABIERTO', 'La reversion no devolvio ABIERTO.');
    comprobarReversion(
        $primera['respuesta']['descartados'] === ['asociaciones' => 1, 'reservas' => 1, 'borradores' => 1],
        'La reversion no informo todos los descartes.'
    );

    foreach (['bancos_asociacion_movimiento', 'bancos_reserva_recurso', 'bancos_borrador_asiento'] as $tabla) {
        $consulta = $pdo->prepare("SELECT count(*) FROM global_temp.{$tabla} WHERE movimiento_id = :id AND activo IS TRUE");
        $consulta->execute([':id' => $movimientoId]);
        comprobarReversion((int) $consulta->fetchColumn() === 0, 'Quedaron filas activas en ' . $tabla . '.');
    }
    $consulta = $pdo->prepare(
        "SELECT e.codigo, h.motivo, h.usuario_hub_id
         FROM global_temp.bancos_historial_asignacion h
         JOIN global_temp.bancos_estado e ON e.id = h.estado_id
         WHERE h.movimiento_id = :id
         ORDER BY h.registrado_en DESC, h.id DESC LIMIT 1"
    );
    $consulta->execute([':id' => $movimientoId]);
    $ultimo = $consulta->fetch(PDO::FETCH_ASSOC);
    comprobarReversion($ultimo['codigo'] === 'ABIERTO', 'El ultimo historial no quedo ABIERTO.');
    comprobarReversion($ultimo['motivo'] === $entrada['motivo'], 'El historial no conservo el motivo.');
    comprobarReversion((int) $ultimo['usuario_hub_id'] === $usuarioId, 'El historial no conservo al operador.');

    $consulta = $pdo->prepare(
        "SELECT a.detalles->'descartados'
         FROM global_temp.bancos_evento_auditoria a
         JOIN global_temp.bancos_solicitud_idempotente s ON s.id = a.solicitud_id
         WHERE s.operacion = 'movimiento.revertir-preparacion' AND s.clave = :clave"
    );
    $consulta->execute([':clave' => $clave]);
    $descartesAuditados = json_decode((string) $consulta->fetchColumn(), true);
    comprobarReversion(
        is_array($descartesAuditados)
        && ($descartesAuditados['asociaciones'] ?? null) === 1
        && ($descartesAuditados['reservas'] ?? null) === 1
        && ($descartesAuditados['borradores'] ?? null) === 1,
        'La auditoria no conservo los conteos descartados.'
    );

    try {
        $casoDeUso->ejecutar($entrada, $usuarioId, 'test-revertir-segunda-' . $marca);
        throw new RuntimeException('Se permitio revertir nuevamente un movimiento ABIERTO.');
    } catch (TransicionMovimientoException $esperada) {
    }
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
        $consulta->execute([':clave' => $clave, ':segunda' => 'test-revertir-segunda-' . $marca]);
        $consulta = $pdo->prepare(
            "DELETE FROM global_temp.bancos_solicitud_idempotente WHERE clave IN (:clave, :segunda)"
        );
        $consulta->execute([':clave' => $clave, ':segunda' => 'test-revertir-segunda-' . $marca]);
        foreach (['bancos_asociacion_movimiento', 'bancos_reserva_recurso'] as $tabla) {
            $consulta = $pdo->prepare("DELETE FROM global_temp.{$tabla} WHERE movimiento_id = :id");
            $consulta->execute([':id' => $movimientoId]);
        }
        if ($borradorId !== null) {
            $consulta = $pdo->prepare('DELETE FROM global_temp.bancos_borrador_asiento WHERE id = :id');
            $consulta->execute([':id' => $borradorId]);
        }
        $consulta = $pdo->prepare('DELETE FROM global_temp.bancos_historial_asignacion WHERE movimiento_id = :id');
        $consulta->execute([':id' => $movimientoId]);
        $consulta = $pdo->prepare('DELETE FROM global_temp.bancos_movimiento_extracto WHERE id = :id');
        $consulta->execute([':id' => $movimientoId]);
        $pdo->commit();
    }
}

echo "Reversion de preparacion mensual debug OK\n";
