BEGIN;

ALTER TABLE global_prod.bancos_migracion_periodo_legacy
    ADD COLUMN IF NOT EXISTS configuracion_id bigint
        REFERENCES global_prod.bancos_configuracion(id);

-- Cada período debe resolver una única configuración a partir de su cuenta y
-- del banco informado en sus movimientos. Los omitidos no se cargan.
WITH candidatos AS (
    SELECT p.id_periodo_legacy, min(mc.configuracion_id) AS configuracion_id
    FROM global_prod.bancos_migracion_periodo_legacy p
    JOIN public.bancos_mes_movimientos_cargados_periodo m ON m.id_periodo = p.id_periodo_legacy
    JOIN public.bancos_mes_guardado_listabancos lb
      ON lb.cuenta = p.cuenta_legacy AND btrim(lb.banco) = btrim(m.banco)
    JOIN global_prod.bancos_migracion_configuracion_legacy mc
      ON mc.origen = 'MENSUAL'
     AND mc.id_legacy = lb.id
     AND mc.cuenta_legacy = lb.cuenta
     AND mc.banco_zetti_id::varchar = lb.banco_id
    WHERE NOT p.omitir
    GROUP BY p.id_periodo_legacy
    HAVING count(DISTINCT mc.configuracion_id) = 1
)
UPDATE global_prod.bancos_migracion_periodo_legacy p
SET configuracion_id = c.configuracion_id
FROM candidatos c
WHERE c.id_periodo_legacy = p.id_periodo_legacy;

DO $$
BEGIN
    IF EXISTS (
        SELECT 1
        FROM global_prod.bancos_migracion_periodo_legacy p
        JOIN global_prod.bancos_migracion_cuenta_legacy c ON c.cuenta_legacy = p.cuenta_legacy
        WHERE NOT p.omitir AND (p.configuracion_id IS NULL OR c.cuenta_bancaria_zetti_id IS NULL OR c.omitir)
    ) THEN
        RAISE EXCEPTION 'Hay períodos no omitidos sin configuración o cuenta bancaria ERP unívoca';
    END IF;
END;
$$;

INSERT INTO global_prod.bancos_importacion_extracto
    (configuracion_id, cuenta_bancaria_zetti_id, inicio_periodo, total_movimientos,
     estado_id, archivo_origen, version_origen, observacion)
SELECT p.configuracion_id, c.cuenta_bancaria_zetti_id,
       make_date(p.ano, p.num_mes, 1), NULL,
       CASE WHEN src.validado THEN 3 ELSE 1 END,
       'LEGACY:BANCOS_MENSUAL', p.id_periodo_legacy,
       'MIGRACION-011|' || p.id_periodo_legacy
FROM global_prod.bancos_migracion_periodo_legacy p
JOIN global_prod.bancos_migracion_cuenta_legacy c ON c.cuenta_legacy = p.cuenta_legacy
JOIN public.bancos_mes_periodos_cargados_cuenta src ON src.id_periodo = p.id_periodo_legacy
WHERE NOT p.omitir;

UPDATE global_prod.bancos_migracion_periodo_legacy p
SET importacion_id = i.id
FROM global_prod.bancos_importacion_extracto i
WHERE i.observacion = 'MIGRACION-011|' || p.id_periodo_legacy;

INSERT INTO global_prod.bancos_movimiento_extracto
    (importacion_id, id_periodo, serial_seq, numero_fila_origen, fecha_operacion,
     referencia, descripcion, credito, debito)
SELECT p.importacion_id, m.id_periodo_legacy, m.serial_seq_legacy,
       row_number() OVER (PARTITION BY m.id_periodo_legacy ORDER BY m.serial_seq_legacy)::integer,
       src.fecha, NULLIF(src.referencia, ''), NULLIF(src.observacion, ''),
       m.credito_normalizado, m.debito_normalizado
FROM global_prod.bancos_migracion_movimiento_legacy m
JOIN global_prod.bancos_migracion_periodo_legacy p ON p.id_periodo_legacy = m.id_periodo_legacy
JOIN public.bancos_mes_movimientos_cargados_periodo src
  ON src.id_periodo = m.id_periodo_legacy AND src.serial_seq = m.serial_seq_legacy
WHERE m.es_canonico AND NOT p.omitir;

UPDATE global_prod.bancos_migracion_movimiento_legacy traza
SET movimiento_id = destino.id
FROM global_prod.bancos_migracion_movimiento_legacy canonico
JOIN global_prod.bancos_movimiento_extracto destino
  ON destino.id_periodo = canonico.id_periodo_legacy
 AND destino.serial_seq = canonico.serial_seq_legacy
WHERE canonico.es_canonico
  AND traza.id_movimiento_legacy = canonico.id_movimiento_legacy;

-- El legado conserva sólo el estado actual; se lo materializa como el primer
-- evento del historial nuevo.
INSERT INTO global_prod.bancos_historial_asignacion
    (movimiento_id, estado_id, usuario_hub_id, observacion)
SELECT DISTINCT t.movimiento_id,
       e.id,
       a.id_usuario::bigint,
       'MIGRACION-011|estado actual legacy'
FROM public.bancos_mes_usuario_asignado_movimiento a
JOIN global_prod.bancos_migracion_movimiento_legacy t
  ON t.id_movimiento_legacy = a.id_movimiento AND t.es_canonico
JOIN global_prod.rrhh_login u ON u.id = a.id_usuario::bigint
JOIN global_prod.bancos_estado e
  ON e.codigo = CASE btrim(a.estado)
        WHEN 'PARA CERRAR' THEN 'PARA_CERRAR'
        ELSE btrim(a.estado)
    END
WHERE a.id_usuario ~ '^[0-9]+$';

COMMIT;
