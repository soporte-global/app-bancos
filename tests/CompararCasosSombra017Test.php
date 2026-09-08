<?php

require_once dirname(__DIR__) . '/src/config.php';

function comprobar017($condicion, $mensaje)
{
    if (!$condicion) {
        throw new RuntimeException($mensaje);
    }
}

function sinDiferencias017(PDO $pdo, $origen, $destino, $nombre)
{
    $sql = 'SELECT count(*) FROM ((' . $origen . ') EXCEPT (' . $destino . ')) a
            UNION ALL SELECT count(*) FROM ((' . $destino . ') EXCEPT (' . $origen . ')) b';
    $diferencias = array_sum(array_map('intval', $pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN)));
    comprobar017($diferencias === 0, $nombre . ': ' . $diferencias . ' diferencias.');
}

$pdo = new PDO(
    'pgsql:host=' . HOST . ';port=' . PORT . ';dbname=' . DBASE,
    USER, PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$origenMovimientos = "SELECT id_periodo, serial_seq, fecha_operacion, referencia, descripcion, credito, debito
    FROM global_prod.bancos_movimiento_extracto WHERE id IN (13017123,13211640)";
$destinoMovimientos = "SELECT m.id_periodo, m.serial_seq, m.fecha_operacion, m.referencia, m.descripcion, m.credito, m.debito
    FROM global_temp.bancos_movimiento_extracto m JOIN global_temp.bancos_importacion_extracto i ON i.id=m.importacion_id
    WHERE i.observacion IN ('SOMBRA-017|IMPORTACION|32999','SOMBRA-017|IMPORTACION|33029')";
sinDiferencias017($pdo, $origenMovimientos, $destinoMovimientos, 'Movimientos 017');

$origenMensajes = "SELECT mo.id_periodo, mo.serial_seq, mm.emisor_hub_id, mm.tipo_mensaje, mm.cuerpo, mm.emitido_en
    FROM global_prod.bancos_mensaje_movimiento mm JOIN global_prod.bancos_movimiento_extracto mo ON mo.id=mm.movimiento_id
    WHERE mm.movimiento_id IN (13017123,13211640)";
$destinoMensajes = "SELECT md.id_periodo, md.serial_seq, mm.emisor_hub_id, mm.tipo_mensaje, mm.cuerpo, mm.emitido_en
    FROM global_temp.bancos_mensaje_movimiento mm JOIN global_temp.bancos_movimiento_extracto md ON md.id=mm.movimiento_id
    JOIN global_temp.bancos_importacion_extracto i ON i.id=md.importacion_id
    WHERE i.observacion IN ('SOMBRA-017|IMPORTACION|32999','SOMBRA-017|IMPORTACION|33029')";
sinDiferencias017($pdo, $origenMensajes, $destinoMensajes, 'Mensajes 017');

$origenValor = "SELECT mo.id_periodo, mo.serial_seq, a.valor_zetti_id, a.monto_asociado, a.activo, a.compartido
    FROM global_prod.bancos_asociacion_movimiento a JOIN global_prod.bancos_movimiento_extracto mo ON mo.id=a.movimiento_id
    WHERE a.movimiento_id=13017123 AND a.valor_zetti_id IS NOT NULL";
$destinoValor = "SELECT md.id_periodo, md.serial_seq, a.valor_zetti_id, a.monto_asociado, a.activo, a.compartido
    FROM global_temp.bancos_asociacion_movimiento a JOIN global_temp.bancos_movimiento_extracto md ON md.id=a.movimiento_id
    WHERE md.id_periodo=(SELECT id_periodo FROM global_prod.bancos_movimiento_extracto WHERE id=13017123)
      AND md.serial_seq=(SELECT serial_seq FROM global_prod.bancos_movimiento_extracto WHERE id=13017123)";
sinDiferencias017($pdo, $origenValor, $destinoValor, 'Asociación a valor 017');

$origenLineas = "SELECT l.cuenta_zetti_id,l.debe,l.haber,l.observacion FROM global_prod.bancos_linea_borrador_asiento l
    JOIN global_prod.bancos_borrador_asiento b ON b.id=l.borrador_asiento_id WHERE b.movimiento_id=13211640";
$destinoLineas = "SELECT l.cuenta_zetti_id,l.debe,l.haber,l.observacion FROM global_temp.bancos_linea_borrador_asiento l
    JOIN global_temp.bancos_borrador_asiento b ON b.id=l.borrador_asiento_id WHERE b.clave_idempotencia LIKE 'SOMBRA-017|BORRADOR|%'";
sinDiferencias017($pdo, $origenLineas, $destinoLineas, 'Líneas de borrador 017');

$excepcion = $pdo->query("SELECT 1 FROM global_prod.bancos_migracion_recurso_omitido
 WHERE tipo_recurso='ASIENTO' AND recurso_zetti_id=103500000000565635
   AND id_movimiento_legacy='M000000P1911510057629102019'")->fetchColumn();
comprobar017($excepcion !== false, 'No se encontró la excepción documentada de migración.');

echo "Comparación de casos sombra 017 OK: mensajes, valor, borrador y excepción documentada.\n";
