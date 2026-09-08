<?php

require_once dirname(__DIR__) . '/src/config.php';

const IMPORTACION_SOMBRA_016 = 35333;
const MARCADOR_SOMBRA_016 = 'SOMBRA-016|IMPORTACION|35333';
const PERIODO_LEGACY_SOMBRA_016 = '1960052277072026';

function comprobarSombra016($condicion, $mensaje)
{
    if (!$condicion) {
        throw new RuntimeException($mensaje);
    }
}

function diferenciasSombra016(PDO $pdo, $sql, $nombre)
{
    $filas = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    if (!$filas) {
        return;
    }

    $muestra = json_encode(array_slice($filas, 0, 3), JSON_UNESCAPED_UNICODE);
    throw new RuntimeException(
        $nombre . ': se encontraron ' . count($filas) . ' diferencias. Muestra: ' . $muestra
    );
}

$pdo = new PDO(
    'pgsql:host=' . HOST . ';port=' . PORT . ';dbname=' . DBASE,
    USER,
    PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$origen = $pdo->prepare(
    'SELECT p.id_periodo_legacy, p.total_movimientos_legacy
     FROM global_prod.bancos_migracion_periodo_legacy p
     WHERE p.importacion_id = :importacion_id'
);
$origen->execute([':importacion_id' => IMPORTACION_SOMBRA_016]);
$periodo = $origen->fetch(PDO::FETCH_ASSOC);
comprobarSombra016($periodo !== false, 'No existe la traza legacy de la importación 35333.');
comprobarSombra016(
    $periodo['id_periodo_legacy'] === PERIODO_LEGACY_SOMBRA_016,
    'La traza legacy no corresponde al período esperado.'
);
comprobarSombra016(
    (int) $periodo['total_movimientos_legacy'] === 51,
    'La traza legacy no conserva 51 movimientos.'
);

diferenciasSombra016($pdo, <<<'SQL'
WITH produccion AS (
    SELECT m.id_periodo, m.serial_seq, m.numero_fila_origen,
           m.fecha_operacion, m.referencia, m.descripcion, m.codigo_extracto,
           m.credito, m.debito, m.moneda, m.subtipo_valor_zetti_id
    FROM global_prod.bancos_movimiento_extracto m
    WHERE m.importacion_id = 35333
), sandbox AS (
    SELECT m.id_periodo, m.serial_seq, m.numero_fila_origen,
           m.fecha_operacion, m.referencia, m.descripcion, m.codigo_extracto,
           m.credito, m.debito, m.moneda, m.subtipo_valor_zetti_id
    FROM global_temp.bancos_movimiento_extracto m
    JOIN global_temp.bancos_importacion_extracto i ON i.id = m.importacion_id
    WHERE i.observacion = 'SOMBRA-016|IMPORTACION|35333'
)
SELECT 'FALTA_EN_SANDBOX' AS diferencia, p.* FROM produccion p
EXCEPT
SELECT 'FALTA_EN_SANDBOX' AS diferencia, s.* FROM sandbox s
UNION ALL
SELECT 'SOBRA_EN_SANDBOX' AS diferencia, s.* FROM sandbox s
EXCEPT
SELECT 'SOBRA_EN_SANDBOX' AS diferencia, p.* FROM produccion p
SQL
, 'Movimientos global_temp frente a global_prod');

diferenciasSombra016($pdo, <<<'SQL'
WITH canonico AS (
    SELECT m.id_periodo, m.serial_seq, m.fecha_operacion, m.referencia,
           m.descripcion, m.credito, m.debito
    FROM global_prod.bancos_movimiento_extracto m
    WHERE m.importacion_id = 35333
), legacy AS (
    SELECT t.id_periodo_legacy AS id_periodo, t.serial_seq_legacy AS serial_seq,
           src.fecha AS fecha_operacion, NULLIF(src.referencia, '') AS referencia,
           NULLIF(src.observacion, '') AS descripcion,
           t.credito_normalizado AS credito, t.debito_normalizado AS debito
    FROM global_prod.bancos_migracion_movimiento_legacy t
    JOIN public.bancos_mes_movimientos_cargados_periodo src
      ON src.id_periodo = t.id_periodo_legacy
     AND src.serial_seq = t.serial_seq_legacy
    JOIN global_prod.bancos_movimiento_extracto m ON m.id = t.movimiento_id
    WHERE t.es_canonico
      AND m.importacion_id = 35333
)
SELECT 'FALTA_EN_CANONICO' AS diferencia, l.* FROM legacy l
EXCEPT
SELECT 'FALTA_EN_CANONICO' AS diferencia, c.* FROM canonico c
UNION ALL
SELECT 'SOBRA_EN_CANONICO' AS diferencia, c.* FROM canonico c
EXCEPT
SELECT 'SOBRA_EN_CANONICO' AS diferencia, l.* FROM legacy l
SQL
, 'Movimientos global_prod frente al legado');

diferenciasSombra016($pdo, <<<'SQL'
WITH produccion AS (
    SELECT m.id_periodo, m.serial_seq, a.valor_zetti_id, a.asiento_zetti_id,
           a.monto_asociado, a.activo, a.observacion, a.compartido
    FROM global_prod.bancos_asociacion_movimiento a
    JOIN global_prod.bancos_movimiento_extracto m ON m.id = a.movimiento_id
    WHERE m.importacion_id = 35333
      AND a.borrador_asiento_id IS NULL
), sandbox AS (
    SELECT m.id_periodo, m.serial_seq, a.valor_zetti_id, a.asiento_zetti_id,
           a.monto_asociado, a.activo, a.observacion, a.compartido
    FROM global_temp.bancos_asociacion_movimiento a
    JOIN global_temp.bancos_movimiento_extracto m ON m.id = a.movimiento_id
    JOIN global_temp.bancos_importacion_extracto i ON i.id = m.importacion_id
    WHERE i.observacion = 'SOMBRA-016|IMPORTACION|35333'
      AND a.borrador_asiento_id IS NULL
)
SELECT 'FALTA_EN_SANDBOX' AS diferencia, p.* FROM produccion p
EXCEPT
SELECT 'FALTA_EN_SANDBOX' AS diferencia, s.* FROM sandbox s
UNION ALL
SELECT 'SOBRA_EN_SANDBOX' AS diferencia, s.* FROM sandbox s
EXCEPT
SELECT 'SOBRA_EN_SANDBOX' AS diferencia, p.* FROM produccion p
SQL
, 'Asociaciones global_temp frente a global_prod');

diferenciasSombra016($pdo, <<<'SQL'
WITH canonico AS (
    SELECT m.id_periodo, m.serial_seq, a.asiento_zetti_id
    FROM global_prod.bancos_asociacion_movimiento a
    JOIN global_prod.bancos_movimiento_extracto m ON m.id = a.movimiento_id
    WHERE m.importacion_id = 35333
      AND a.asiento_zetti_id IS NOT NULL
      AND a.borrador_asiento_id IS NULL
), legacy AS (
    SELECT t.id_periodo_legacy AS id_periodo,
           t.serial_seq_canonico AS serial_seq,
           a.id_asiento::bigint AS asiento_zetti_id
    FROM public.bancos_mes_asiento_asignado_movimiento a
    JOIN global_prod.bancos_migracion_movimiento_legacy t
      ON t.id_movimiento_legacy = a.id_movimiento
     AND t.es_canonico
    JOIN global_prod.bancos_movimiento_extracto m ON m.id = t.movimiento_id
    LEFT JOIN global_prod.bancos_migracion_recurso_omitido omitido
      ON omitido.tipo_recurso = 'ASIENTO'
     AND omitido.recurso_zetti_id = a.id_asiento
     AND omitido.id_movimiento_legacy = a.id_movimiento
    WHERE m.importacion_id = 35333
      AND a.id_asiento <> 0
      AND omitido.id_movimiento_legacy IS NULL
)
SELECT 'FALTA_EN_CANONICO' AS diferencia, l.* FROM legacy l
EXCEPT
SELECT 'FALTA_EN_CANONICO' AS diferencia, c.* FROM canonico c
UNION ALL
SELECT 'SOBRA_EN_CANONICO' AS diferencia, c.* FROM canonico c
EXCEPT
SELECT 'SOBRA_EN_CANONICO' AS diferencia, l.* FROM legacy l
SQL
, 'Asociaciones global_prod frente al legado');

echo "Comparación de sombra 016 OK: 51 movimientos y 47 asociaciones con paridad legacy.\n";
