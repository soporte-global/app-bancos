-- Casos de sombra mínimos que cubren mensaje+valor y mensaje+borrador.
-- Origen de sólo lectura: global_prod; no modifica public ni global_prod.
BEGIN;

DO $$
BEGIN
    IF (SELECT count(*) FROM global_prod.bancos_movimiento_extracto WHERE id IN (13017123, 13211640)) <> 2 THEN
        RAISE EXCEPTION '017 preflight: faltan los movimientos fuente';
    END IF;
    IF (SELECT count(*) FROM global_prod.bancos_mensaje_movimiento WHERE movimiento_id IN (13017123, 13211640)) <> 2 THEN
        RAISE EXCEPTION '017 preflight: se esperaban dos mensajes';
    END IF;
    IF (SELECT count(*) FROM global_prod.bancos_asociacion_movimiento WHERE movimiento_id = 13017123 AND valor_zetti_id IS NOT NULL) <> 1 THEN
        RAISE EXCEPTION '017 preflight: se esperaba una asociación a valor';
    END IF;
    IF (SELECT count(*) FROM global_prod.bancos_borrador_asiento WHERE movimiento_id = 13211640) <> 1 THEN
        RAISE EXCEPTION '017 preflight: se esperaba un borrador';
    END IF;
END;
$$;

INSERT INTO global_temp.bancos_estado (codigo, descripcion, activo)
SELECT codigo, descripcion, activo FROM global_prod.bancos_estado
ON CONFLICT (codigo) DO UPDATE SET descripcion = EXCLUDED.descripcion, activo = EXCLUDED.activo;

INSERT INTO global_temp.bancos_configuracion
    (alcance, banco_zetti_id, nodo_zetti_id, moneda, activo, observacion,
     fecha_creacion, usuario_creacion, fecha_modificacion, usuario_modificacion,
     cuenta_contable_zetti_id)
SELECT c.alcance, c.banco_zetti_id, c.nodo_zetti_id, c.moneda, c.activo,
       'SOMBRA-017|CONFIGURACION|' || c.id,
       c.fecha_creacion, c.usuario_creacion, c.fecha_modificacion, c.usuario_modificacion,
       c.cuenta_contable_zetti_id
FROM global_prod.bancos_configuracion c
JOIN global_prod.bancos_importacion_extracto i ON i.configuracion_id = c.id
JOIN global_prod.bancos_movimiento_extracto m ON m.importacion_id = i.id
WHERE m.id IN (13017123, 13211640)
  AND NOT EXISTS (SELECT 1 FROM global_temp.bancos_configuracion d WHERE d.observacion = 'SOMBRA-017|CONFIGURACION|' || c.id);

INSERT INTO global_temp.bancos_importacion_extracto
    (configuracion_id, cuenta_bancaria_zetti_id, inicio_periodo, total_movimientos,
     estado_id, archivo_origen, hash_origen, version_origen, observacion)
SELECT cd.id, i.cuenta_bancaria_zetti_id, i.inicio_periodo, 1, ed.id,
       i.archivo_origen, i.hash_origen, 'SOMBRA-017|' || i.id, 'SOMBRA-017|IMPORTACION|' || i.id
FROM global_prod.bancos_importacion_extracto i
JOIN global_prod.bancos_configuracion c ON c.id = i.configuracion_id
JOIN global_temp.bancos_configuracion cd ON cd.observacion = 'SOMBRA-017|CONFIGURACION|' || c.id
JOIN global_prod.bancos_estado eo ON eo.id = i.estado_id
JOIN global_temp.bancos_estado ed ON ed.codigo = eo.codigo
WHERE i.id IN (32999, 33029)
  AND NOT EXISTS (SELECT 1 FROM global_temp.bancos_importacion_extracto d WHERE d.observacion = 'SOMBRA-017|IMPORTACION|' || i.id);

INSERT INTO global_temp.bancos_movimiento_extracto
    (importacion_id, id_periodo, serial_seq, numero_fila_origen, fecha_operacion,
     referencia, descripcion, codigo_extracto, credito, debito, moneda, subtipo_valor_zetti_id,
     fecha_creacion, usuario_creacion, fecha_modificacion, usuario_modificacion)
SELECT id.id, m.id_periodo, m.serial_seq, m.numero_fila_origen, m.fecha_operacion,
       m.referencia, m.descripcion, m.codigo_extracto, m.credito, m.debito, m.moneda,
       m.subtipo_valor_zetti_id, m.fecha_creacion, m.usuario_creacion,
       m.fecha_modificacion, m.usuario_modificacion
FROM global_prod.bancos_movimiento_extracto m
JOIN global_temp.bancos_importacion_extracto id ON id.observacion = 'SOMBRA-017|IMPORTACION|' || m.importacion_id
WHERE m.id IN (13017123, 13211640)
ON CONFLICT (id_periodo, serial_seq) DO UPDATE SET importacion_id=EXCLUDED.importacion_id,
 fecha_operacion=EXCLUDED.fecha_operacion, referencia=EXCLUDED.referencia,
 descripcion=EXCLUDED.descripcion, credito=EXCLUDED.credito, debito=EXCLUDED.debito;

INSERT INTO global_temp.bancos_mensaje_movimiento (movimiento_id, emisor_hub_id, tipo_mensaje, cuerpo, emitido_en)
SELECT md.id, mm.emisor_hub_id, mm.tipo_mensaje, mm.cuerpo, mm.emitido_en
FROM global_prod.bancos_mensaje_movimiento mm
JOIN global_prod.bancos_movimiento_extracto mo ON mo.id=mm.movimiento_id
JOIN global_temp.bancos_movimiento_extracto md ON md.id_periodo=mo.id_periodo AND md.serial_seq=mo.serial_seq
WHERE mm.movimiento_id IN (13017123,13211640)
  AND NOT EXISTS (SELECT 1 FROM global_temp.bancos_mensaje_movimiento d WHERE d.movimiento_id=md.id AND d.emitido_en=mm.emitido_en AND d.cuerpo=mm.cuerpo);

INSERT INTO global_temp.bancos_asociacion_movimiento (movimiento_id, valor_zetti_id, monto_asociado, activo, observacion, fecha_creacion, usuario_creacion, compartido)
SELECT md.id, a.valor_zetti_id, a.monto_asociado, a.activo, a.observacion, a.fecha_creacion, a.usuario_creacion, a.compartido
FROM global_prod.bancos_asociacion_movimiento a
JOIN global_prod.bancos_movimiento_extracto mo ON mo.id=a.movimiento_id
JOIN global_temp.bancos_movimiento_extracto md ON md.id_periodo=mo.id_periodo AND md.serial_seq=mo.serial_seq
WHERE a.movimiento_id=13017123 AND a.valor_zetti_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM global_temp.bancos_asociacion_movimiento d WHERE d.movimiento_id=md.id AND d.valor_zetti_id=a.valor_zetti_id);

INSERT INTO global_temp.bancos_borrador_asiento
    (movimiento_id,nodo_zetti_id,fecha_contable,estado_id,operacion_zetti_id,asiento_zetti_id,
     clave_idempotencia,modelo,activo,observacion,fecha_creacion,usuario_creacion,fecha_modificacion,usuario_modificacion)
SELECT md.id,b.nodo_zetti_id,b.fecha_contable,ed.id,b.operacion_zetti_id,b.asiento_zetti_id,
       'SOMBRA-017|BORRADOR|'||b.id,b.modelo,b.activo,'SOMBRA-017|BORRADOR|'||b.id,
       b.fecha_creacion,b.usuario_creacion,b.fecha_modificacion,b.usuario_modificacion
FROM global_prod.bancos_borrador_asiento b
JOIN global_prod.bancos_movimiento_extracto mo ON mo.id=b.movimiento_id
JOIN global_temp.bancos_movimiento_extracto md ON md.id_periodo=mo.id_periodo AND md.serial_seq=mo.serial_seq
JOIN global_prod.bancos_estado eo ON eo.id=b.estado_id
JOIN global_temp.bancos_estado ed ON ed.codigo=eo.codigo
WHERE b.movimiento_id=13211640
  AND NOT EXISTS (SELECT 1 FROM global_temp.bancos_borrador_asiento d WHERE d.clave_idempotencia='SOMBRA-017|BORRADOR|'||b.id);

INSERT INTO global_temp.bancos_linea_borrador_asiento (borrador_asiento_id,cuenta_zetti_id,debe,haber,observacion)
SELECT bd.id,l.cuenta_zetti_id,l.debe,l.haber,l.observacion
FROM global_prod.bancos_linea_borrador_asiento l
JOIN global_temp.bancos_borrador_asiento bd ON bd.clave_idempotencia='SOMBRA-017|BORRADOR|'||l.borrador_asiento_id
WHERE l.borrador_asiento_id IN (SELECT id FROM global_prod.bancos_borrador_asiento WHERE movimiento_id=13211640)
  AND NOT EXISTS (SELECT 1 FROM global_temp.bancos_linea_borrador_asiento d WHERE d.borrador_asiento_id=bd.id AND d.cuenta_zetti_id=l.cuenta_zetti_id AND d.debe=l.debe AND d.haber=l.haber);

DO $$
BEGIN
 IF (SELECT count(*) FROM global_temp.bancos_movimiento_extracto m JOIN global_temp.bancos_importacion_extracto i ON i.id=m.importacion_id WHERE i.observacion LIKE 'SOMBRA-017|IMPORTACION|%') <> 2 THEN RAISE EXCEPTION '017 validación: movimientos'; END IF;
 IF (SELECT count(*) FROM global_temp.bancos_mensaje_movimiento m JOIN global_temp.bancos_movimiento_extracto d ON d.id=m.movimiento_id JOIN global_temp.bancos_importacion_extracto i ON i.id=d.importacion_id WHERE i.observacion LIKE 'SOMBRA-017|IMPORTACION|%') <> 2 THEN RAISE EXCEPTION '017 validación: mensajes'; END IF;
 IF (SELECT count(*) FROM global_temp.bancos_borrador_asiento WHERE clave_idempotencia LIKE 'SOMBRA-017|BORRADOR|%') <> 1 THEN RAISE EXCEPTION '017 validación: borrador'; END IF;
END $$;
COMMIT;
