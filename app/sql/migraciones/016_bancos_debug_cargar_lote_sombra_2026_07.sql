-- Lote de lectura para validar la bandeja contra datos ya migrados.
-- Origen: global_prod. No escribe public ni global_prod.
-- Alcance deliberadamente acotado: cuenta 103500000000515822, período 2026-07-01,
-- importación canónica 35333 (51 movimientos y sus asociaciones a asientos).
-- La reversión está en 016_bancos_debug_cargar_lote_sombra_2026_07_rollback.sql.

BEGIN;

DO $$
DECLARE
    cantidad_importaciones integer;
    cantidad_movimientos integer;
BEGIN
    SELECT count(*), coalesce(sum(total), 0)::integer
      INTO cantidad_importaciones, cantidad_movimientos
    FROM (
        SELECT i.id, count(m.id) AS total
        FROM global_prod.bancos_importacion_extracto i
        LEFT JOIN global_prod.bancos_movimiento_extracto m ON m.importacion_id = i.id
        WHERE i.id = 35333
          AND i.cuenta_bancaria_zetti_id = 103500000000515822
          AND i.inicio_periodo = DATE '2026-07-01'
        GROUP BY i.id
    ) origen;

    IF cantidad_importaciones <> 1 OR cantidad_movimientos <> 51 THEN
        RAISE EXCEPTION
            '016 preflight falló: se esperaba la importación 35333 con 51 movimientos (importaciones %, movimientos %)',
            cantidad_importaciones, cantidad_movimientos;
    END IF;
END;
$$;

-- Los IDs internos de global_temp no se copian: cada relación se reconstruye
-- mediante el marcador SOMBRA-016 y las claves naturales del movimiento.
INSERT INTO global_temp.bancos_estado (codigo, descripcion, activo)
SELECT codigo, descripcion, activo
FROM global_prod.bancos_estado
ON CONFLICT (codigo) DO UPDATE
SET descripcion = EXCLUDED.descripcion,
    activo = EXCLUDED.activo;

INSERT INTO global_temp.bancos_configuracion
    (alcance, banco_zetti_id, nodo_zetti_id, moneda, activo, observacion,
     fecha_creacion, usuario_creacion, fecha_modificacion, usuario_modificacion,
     cuenta_contable_zetti_id)
SELECT c.alcance, c.banco_zetti_id, c.nodo_zetti_id, c.moneda, c.activo,
       'SOMBRA-016|CONFIGURACION|' || c.id,
       c.fecha_creacion, c.usuario_creacion, c.fecha_modificacion, c.usuario_modificacion,
       c.cuenta_contable_zetti_id
FROM global_prod.bancos_configuracion c
JOIN global_prod.bancos_importacion_extracto i ON i.configuracion_id = c.id
WHERE i.id = 35333
  AND NOT EXISTS (
      SELECT 1
      FROM global_temp.bancos_configuracion destino
      WHERE destino.observacion = 'SOMBRA-016|CONFIGURACION|' || c.id
  );

INSERT INTO global_temp.bancos_importacion_extracto
    (configuracion_id, cuenta_bancaria_zetti_id, inicio_periodo, total_movimientos,
     estado_id, archivo_origen, hash_origen, version_origen, observacion,
     fecha_creacion, usuario_creacion, fecha_modificacion, usuario_modificacion)
SELECT configuracion_destino.id, origen.cuenta_bancaria_zetti_id, origen.inicio_periodo,
       origen.total_movimientos, estado_destino.id,
       origen.archivo_origen, origen.hash_origen,
       'SOMBRA-016|' || origen.id, 'SOMBRA-016|IMPORTACION|' || origen.id,
       origen.fecha_creacion, origen.usuario_creacion,
       origen.fecha_modificacion, origen.usuario_modificacion
FROM global_prod.bancos_importacion_extracto origen
JOIN global_prod.bancos_configuracion configuracion_origen
  ON configuracion_origen.id = origen.configuracion_id
JOIN global_temp.bancos_configuracion configuracion_destino
  ON configuracion_destino.observacion = 'SOMBRA-016|CONFIGURACION|' || configuracion_origen.id
JOIN global_prod.bancos_estado estado_origen ON estado_origen.id = origen.estado_id
JOIN global_temp.bancos_estado estado_destino ON estado_destino.codigo = estado_origen.codigo
WHERE origen.id = 35333
  AND NOT EXISTS (
      SELECT 1
      FROM global_temp.bancos_importacion_extracto destino
      WHERE destino.observacion = 'SOMBRA-016|IMPORTACION|' || origen.id
  );

INSERT INTO global_temp.bancos_movimiento_extracto
    (importacion_id, id_periodo, serial_seq, numero_fila_origen, fecha_operacion,
     referencia, descripcion, codigo_extracto, credito, debito, moneda,
     subtipo_valor_zetti_id, fecha_creacion, usuario_creacion,
     fecha_modificacion, usuario_modificacion)
SELECT lote_destino.id, origen.id_periodo, origen.serial_seq,
       origen.numero_fila_origen, origen.fecha_operacion, origen.referencia,
       origen.descripcion, origen.codigo_extracto, origen.credito, origen.debito,
       origen.moneda, origen.subtipo_valor_zetti_id, origen.fecha_creacion,
       origen.usuario_creacion, origen.fecha_modificacion, origen.usuario_modificacion
FROM global_prod.bancos_movimiento_extracto origen
JOIN global_temp.bancos_importacion_extracto lote_destino
  ON lote_destino.observacion = 'SOMBRA-016|IMPORTACION|35333'
WHERE origen.importacion_id = 35333
ON CONFLICT (id_periodo, serial_seq) DO UPDATE
SET importacion_id = EXCLUDED.importacion_id,
    numero_fila_origen = EXCLUDED.numero_fila_origen,
    fecha_operacion = EXCLUDED.fecha_operacion,
    referencia = EXCLUDED.referencia,
    descripcion = EXCLUDED.descripcion,
    codigo_extracto = EXCLUDED.codigo_extracto,
    credito = EXCLUDED.credito,
    debito = EXCLUDED.debito,
    moneda = EXCLUDED.moneda,
    subtipo_valor_zetti_id = EXCLUDED.subtipo_valor_zetti_id,
    fecha_modificacion = EXCLUDED.fecha_modificacion,
    usuario_modificacion = EXCLUDED.usuario_modificacion;

INSERT INTO global_temp.bancos_asociacion_movimiento
    (movimiento_id, valor_zetti_id, asiento_zetti_id, borrador_asiento_id,
     monto_asociado, activo, observacion, fecha_creacion, usuario_creacion, compartido)
SELECT movimiento_destino.id, origen.valor_zetti_id, origen.asiento_zetti_id,
       NULL, origen.monto_asociado, origen.activo, origen.observacion,
       origen.fecha_creacion, origen.usuario_creacion, origen.compartido
FROM global_prod.bancos_asociacion_movimiento origen
JOIN global_prod.bancos_movimiento_extracto movimiento_origen
  ON movimiento_origen.id = origen.movimiento_id
JOIN global_temp.bancos_movimiento_extracto movimiento_destino
  ON movimiento_destino.id_periodo = movimiento_origen.id_periodo
 AND movimiento_destino.serial_seq = movimiento_origen.serial_seq
JOIN global_temp.bancos_importacion_extracto lote_destino
  ON lote_destino.id = movimiento_destino.importacion_id
 AND lote_destino.observacion = 'SOMBRA-016|IMPORTACION|35333'
WHERE movimiento_origen.importacion_id = 35333
  AND origen.borrador_asiento_id IS NULL
  AND NOT EXISTS (
      SELECT 1
      FROM global_temp.bancos_asociacion_movimiento existente
      WHERE existente.movimiento_id = movimiento_destino.id
        AND existente.valor_zetti_id IS NOT DISTINCT FROM origen.valor_zetti_id
        AND existente.asiento_zetti_id IS NOT DISTINCT FROM origen.asiento_zetti_id
        AND existente.observacion IS NOT DISTINCT FROM origen.observacion
  );

DO $$
DECLARE
    movimientos_cargados integer;
    asociaciones_cargadas integer;
BEGIN
    SELECT count(*) INTO movimientos_cargados
    FROM global_temp.bancos_movimiento_extracto m
    JOIN global_temp.bancos_importacion_extracto i ON i.id = m.importacion_id
    WHERE i.observacion = 'SOMBRA-016|IMPORTACION|35333';

    SELECT count(*) INTO asociaciones_cargadas
    FROM global_temp.bancos_asociacion_movimiento a
    JOIN global_temp.bancos_movimiento_extracto m ON m.id = a.movimiento_id
    JOIN global_temp.bancos_importacion_extracto i ON i.id = m.importacion_id
    WHERE i.observacion = 'SOMBRA-016|IMPORTACION|35333';

    IF movimientos_cargados <> 51 OR asociaciones_cargadas <> 47 THEN
        RAISE EXCEPTION
            '016 validación falló: se esperaban 51 movimientos y 47 asociaciones (movimientos %, asociaciones %)',
            movimientos_cargados, asociaciones_cargadas;
    END IF;
END;
$$;

COMMIT;
