<?php

require_once dirname(__DIR__) . '/src/config.php';
require_once RUTA . '/src/autoload.php';

use AppBancos\Application\CerrarMovimientoMensual;
use AppBancos\Application\EjecutorComandoIdempotente;
use AppBancos\Infrastructure\EsquemaBancos;
use AppBancos\Repository\AuditoriaRepository;
use AppBancos\Repository\BorradorAsientoErpGateway;
use AppBancos\Repository\CierreMovimientoRepository;
use AppBancos\Repository\IdempotenciaRepository;
use AppBancos\Repository\PreflightCierreMovimientoRepository;
use AppBancos\Repository\ValorErpGateway;

function comprobarCierreBorrador($condicion, $mensaje)
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
     WHERE lower(usuario)='hvega' AND habilitado IS TRUE AND fecha_eliminacion IS NULL"
)->fetchColumn();
$importacion = $pdo->query(
    "SELECT i.id, m.id_periodo, i.cuenta_bancaria_zetti_id, i.inicio_periodo
     FROM global_temp.bancos_importacion_extracto i
     JOIN global_temp.bancos_movimiento_extracto m ON m.importacion_id=i.id
     WHERE i.version_origen='FIXTURE-015'
     ORDER BY i.id,m.id LIMIT 1"
)->fetch(PDO::FETCH_ASSOC);
$nodoId = (int) $pdo->query('SELECT id FROM public.nodo ORDER BY id LIMIT 1')->fetchColumn();
$cuentas = $pdo->query('SELECT id FROM public.cuenta ORDER BY id LIMIT 2')->fetchAll(PDO::FETCH_COLUMN);
comprobarCierreBorrador(
    $usuarioId > 0 && $importacion !== false && $nodoId > 0 && count($cuentas) === 2,
    'Faltan datos base para probar la materializacion del borrador.'
);

$marca = bin2hex(random_bytes(6));
$clave = 'test-cierre-borrador-' . $marca;
$movimientoId = null;
$borradorId = null;
$asientoId = null;
try {
    $numeroFila = random_int(1000000, 1900000000);
    $consulta = $pdo->prepare(
        "INSERT INTO global_temp.bancos_movimiento_extracto
            (importacion_id,id_periodo,serial_seq,numero_fila_origen,fecha_operacion,
             referencia,descripcion,credito,debito,usuario_creacion,usuario_modificacion)
         VALUES
            (:importacion_id,:id_periodo,:serial_seq,:numero_fila,:fecha,
             :referencia,'TEST CIERRE BORRADOR',25,0,:usuario,:usuario)
         RETURNING id"
    );
    $consulta->execute([
        ':importacion_id' => $importacion['id'],
        ':id_periodo' => $importacion['id_periodo'],
        ':serial_seq' => $numeroFila,
        ':numero_fila' => $numeroFila,
        ':fecha' => $importacion['inicio_periodo'],
        ':referencia' => 'TEST-CIERRE-BORRADOR-' . $marca,
        ':usuario' => $usuarioId,
    ]);
    $movimientoId = (int) $consulta->fetchColumn();
    $estadoAbierto = (int) $pdo->query(
        "SELECT id FROM global_temp.bancos_estado WHERE codigo='ABIERTO'"
    )->fetchColumn();
    $consulta = $pdo->prepare(
        "INSERT INTO global_temp.bancos_borrador_asiento
            (movimiento_id,nodo_zetti_id,fecha_contable,estado_id,clave_idempotencia,
             modelo,activo,observacion,usuario_creacion,usuario_modificacion)
         VALUES
            (:movimiento,:nodo,:fecha,:estado,:clave,'TEST ASIENTO SANDBOX',true,
             'TEST|CIERRE_BORRADOR',:usuario,:usuario)
         RETURNING id"
    );
    $consulta->execute([
        ':movimiento' => $movimientoId,
        ':nodo' => $nodoId,
        ':fecha' => $importacion['inicio_periodo'],
        ':estado' => $estadoAbierto,
        ':clave' => 'TEST|CIERRE_BORRADOR|' . $marca,
        ':usuario' => $usuarioId,
    ]);
    $borradorId = (int) $consulta->fetchColumn();
    $linea = $pdo->prepare(
        "INSERT INTO global_temp.bancos_linea_borrador_asiento
            (borrador_asiento_id,cuenta_zetti_id,debe,haber,observacion)
         VALUES (:borrador,:cuenta,:debe,:haber,'TEST|CIERRE_BORRADOR')"
    );
    $linea->execute([':borrador' => $borradorId, ':cuenta' => $cuentas[0], ':debe' => '25.00000', ':haber' => '0']);
    $linea->execute([':borrador' => $borradorId, ':cuenta' => $cuentas[1], ':debe' => '0', ':haber' => '25.00000']);
    $consulta = $pdo->prepare(
        "INSERT INTO global_temp.bancos_reserva_recurso
            (movimiento_id,borrador_asiento_id,activo,reservado_por,motivo)
         VALUES (:movimiento,:borrador,true,:usuario,'TEST|CIERRE_BORRADOR')"
    );
    $consulta->execute([':movimiento' => $movimientoId, ':borrador' => $borradorId, ':usuario' => $usuarioId]);
    $consulta = $pdo->prepare(
        "INSERT INTO global_temp.bancos_asociacion_movimiento
            (movimiento_id,borrador_asiento_id,activo,observacion,usuario_creacion)
         VALUES (:movimiento,:borrador,true,'TEST|CIERRE_BORRADOR',:usuario)"
    );
    $consulta->execute([':movimiento' => $movimientoId, ':borrador' => $borradorId, ':usuario' => $usuarioId]);
    $estadoParaCerrar = (int) $pdo->query(
        "SELECT id FROM global_temp.bancos_estado WHERE codigo='PARA_CERRAR'"
    )->fetchColumn();
    $consulta = $pdo->prepare(
        "INSERT INTO global_temp.bancos_historial_asignacion
            (movimiento_id,estado_id,usuario_hub_id,observacion)
         VALUES (:movimiento,:estado,:usuario,'TEST|CIERRE_BORRADOR')"
    );
    $consulta->execute([':movimiento' => $movimientoId, ':estado' => $estadoParaCerrar, ':usuario' => $usuarioId]);

    $esquema = EsquemaBancos::desdeConfiguracion(['bancos_debug' => true]);
    $casoDeUso = new CerrarMovimientoMensual(
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
    $entrada = [
        'movimiento_id' => $movimientoId,
        'cuenta_bancaria_id' => (string) $importacion['cuenta_bancaria_zetti_id'],
        'inicio_periodo' => $importacion['inicio_periodo'],
    ];
    $primera = $casoDeUso->ejecutar($entrada, $usuarioId, $clave);
    $repetida = $casoDeUso->ejecutar($entrada, $usuarioId, $clave);
    comprobarCierreBorrador(!$primera['repetida'] && $repetida['repetida'], 'El cierre con borrador no fue idempotente.');
    comprobarCierreBorrador($primera['respuesta']['estado'] === 'CERRADO', 'El movimiento no quedo CERRADO.');
    comprobarCierreBorrador($primera['respuesta']['alcance'] === 'BORRADOR_ASIENTO', 'El alcance del cierre es incorrecto.');
    comprobarCierreBorrador($primera['respuesta']['efectos_erp'] === 1, 'No se informo la creacion del asiento.');
    $efecto = $primera['respuesta']['efectos_aplicados'][0]['resultado'];
    $asientoId = (string) $efecto['asiento_zetti_id'];

    $consulta = $pdo->prepare(
        "SELECT a.fecha::date::text,a.nodo_creacion,a.nombre,a.numero,a.usuario_creacion,
                count(m.id) lineas,
                sum(CASE WHEN m.debita THEN m.monto ELSE 0 END) debe,
                sum(CASE WHEN NOT m.debita THEN m.monto ELSE 0 END) haber
         FROM global_temp.asiento a
         JOIN global_temp.movimiento m ON m.asiento=a.id
         WHERE a.id=:id
         GROUP BY a.id,a.fecha,a.nodo_creacion,a.nombre,a.numero,a.usuario_creacion"
    );
    $consulta->execute([':id' => $asientoId]);
    $asiento = $consulta->fetch(PDO::FETCH_ASSOC);
    comprobarCierreBorrador($asiento !== false && (int) $asiento['lineas'] === 2, 'El asiento no contiene dos lineas.');
    comprobarCierreBorrador($asiento['debe'] === $asiento['haber'], 'El asiento materializado no esta balanceado.');
    comprobarCierreBorrador($asiento['fecha'] === $importacion['inicio_periodo'], 'La fecha contable no se conservo.');
    comprobarCierreBorrador((int) $asiento['nodo_creacion'] === $nodoId, 'El nodo del borrador no se conservo.');
    comprobarCierreBorrador($asiento['nombre'] === 'TEST ASIENTO SANDBOX', 'El modelo no se uso como nombre del asiento.');
    comprobarCierreBorrador($asiento['numero'] === null && $asiento['usuario_creacion'] === null, 'Se invento numero o identidad ERP.');

    $consulta = $pdo->prepare(
        "SELECT b.asiento_zetti_id::text,e.codigo
         FROM global_temp.bancos_borrador_asiento b
         JOIN global_temp.bancos_estado e ON e.id=b.estado_id WHERE b.id=:id"
    );
    $consulta->execute([':id' => $borradorId]);
    $borrador = $consulta->fetch(PDO::FETCH_ASSOC);
    comprobarCierreBorrador($borrador['asiento_zetti_id'] === $asientoId && $borrador['codigo'] === 'CERRADO', 'El borrador no quedo materializado y cerrado.');
} finally {
    if ($movimientoId !== null) {
        $pdo->beginTransaction();
        $consulta = $pdo->prepare(
            "DELETE FROM global_temp.bancos_evento_auditoria WHERE solicitud_id IN (
                 SELECT id FROM global_temp.bancos_solicitud_idempotente WHERE clave=:clave
             )"
        );
        $consulta->execute([':clave' => $clave]);
        $consulta = $pdo->prepare('DELETE FROM global_temp.bancos_solicitud_idempotente WHERE clave=:clave');
        $consulta->execute([':clave' => $clave]);
        $consulta = $pdo->prepare('DELETE FROM global_temp.bancos_reserva_recurso WHERE movimiento_id=:id');
        $consulta->execute([':id' => $movimientoId]);
        $consulta = $pdo->prepare('DELETE FROM global_temp.bancos_asociacion_movimiento WHERE movimiento_id=:id');
        $consulta->execute([':id' => $movimientoId]);
        $consulta = $pdo->prepare('DELETE FROM global_temp.bancos_historial_asignacion WHERE movimiento_id=:id');
        $consulta->execute([':id' => $movimientoId]);
        if ($borradorId !== null) {
            $consulta = $pdo->prepare('DELETE FROM global_temp.bancos_linea_borrador_asiento WHERE borrador_asiento_id=:id');
            $consulta->execute([':id' => $borradorId]);
            $consulta = $pdo->prepare('DELETE FROM global_temp.bancos_borrador_asiento WHERE id=:id');
            $consulta->execute([':id' => $borradorId]);
        }
        $consulta = $pdo->prepare('DELETE FROM global_temp.bancos_movimiento_extracto WHERE id=:id');
        $consulta->execute([':id' => $movimientoId]);
        if ($asientoId !== null) {
            $consulta = $pdo->prepare('DELETE FROM global_temp.movimiento WHERE asiento=:id');
            $consulta->execute([':id' => $asientoId]);
            $consulta = $pdo->prepare('DELETE FROM global_temp.asiento WHERE id=:id');
            $consulta->execute([':id' => $asientoId]);
        }
        $pdo->commit();
    }
}

echo "Cierre con materializacion de borrador en sandbox OK\n";
