BEGIN;

-- Fixtures mínimos, idempotentes y exclusivos de global_temp. No reproducen
-- datos productivos ni requieren escribir en public/global_prod. Las FK que
-- ya existen en este sandbox leen la cuenta ERP desde public; no se clona ni
-- se modifica esa cuenta.
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM public.cuenta_bancaria) THEN
        RAISE EXCEPTION '015 requiere al menos una cuenta bancaria ERP legible';
    END IF;
END;
$$;

INSERT INTO global_temp.bancos_estado (codigo, descripcion)
VALUES
    ('ABIERTO', 'Fixture de depuración'),
    ('PARA_CERRAR', 'Fixture de depuración'),
    ('CERRADO', 'Fixture de depuración')
ON CONFLICT (codigo) DO UPDATE
SET descripcion = EXCLUDED.descripcion;

INSERT INTO global_temp.bancos_configuracion
    (alcance, banco_zetti_id, moneda, activo, observacion)
SELECT 'CUENTA', NULL, 1, true, 'FIXTURE-015|CONFIGURACION'
WHERE NOT EXISTS (
    SELECT 1
    FROM global_temp.bancos_configuracion
    WHERE observacion = 'FIXTURE-015|CONFIGURACION'
);

INSERT INTO global_temp.bancos_importacion_extracto
    (configuracion_id, cuenta_bancaria_zetti_id, inicio_periodo,
     total_movimientos, estado_id, archivo_origen, hash_origen,
     version_origen, observacion)
SELECT c.id, cb.id, DATE '2026-09-01', 5,
       e.id, 'FIXTURE-015',
       '0150150150150150150150150150150150150150150150150150150150150150',
       'FIXTURE-015', 'FIXTURE-015|IMPORTACION'
FROM global_temp.bancos_configuracion c
JOIN global_temp.bancos_estado e ON e.codigo = 'ABIERTO'
CROSS JOIN LATERAL (
    SELECT id
    FROM public.cuenta_bancaria
    ORDER BY id
    LIMIT 1
) cb
WHERE c.observacion = 'FIXTURE-015|CONFIGURACION'
  AND NOT EXISTS (
      SELECT 1
      FROM global_temp.bancos_importacion_extracto i
      WHERE i.hash_origen = '0150150150150150150150150150150150150150150150150150150150150150'
        AND i.version_origen = 'FIXTURE-015'
  );

WITH lote AS (
    SELECT id
    FROM global_temp.bancos_importacion_extracto
    WHERE hash_origen = '0150150150150150150150150150150150150150150150150150150150150150'
      AND version_origen = 'FIXTURE-015'
), filas AS (
    SELECT *
    FROM (VALUES
        ('DEBUG-2026-09'::varchar, 1::bigint, 1::integer, DATE '2026-09-02', 'DBG-001', 'Crédito inicial', 1500.00::numeric, 0.00::numeric),
        ('DEBUG-2026-09'::varchar, 2::bigint, 2::integer, DATE '2026-09-03', 'DBG-002', 'Débito operativo', 0.00::numeric, 320.50::numeric),
        ('DEBUG-2026-09'::varchar, 3::bigint, 3::integer, DATE '2026-09-04', 'DBG-003', 'Crédito intermedio', 875.25::numeric, 0.00::numeric),
        ('DEBUG-2026-09'::varchar, 4::bigint, 4::integer, DATE '2026-09-05', 'DBG-004', 'Débito final', 0.00::numeric, 99.99::numeric),
        ('DEBUG-2026-09'::varchar, 5::bigint, 5::integer, DATE '2026-09-06', 'DBG-005', 'Crédito final', 42.00::numeric, 0.00::numeric)
    ) AS f(id_periodo, serial_seq, numero_fila_origen, fecha_operacion, referencia, descripcion, credito, debito)
)
INSERT INTO global_temp.bancos_movimiento_extracto
    (importacion_id, id_periodo, serial_seq, numero_fila_origen,
     fecha_operacion, referencia, descripcion, credito, debito)
SELECT l.id, f.id_periodo, f.serial_seq, f.numero_fila_origen,
       f.fecha_operacion, f.referencia, f.descripcion, f.credito, f.debito
FROM lote l
CROSS JOIN filas f
ON CONFLICT (id_periodo, serial_seq) DO UPDATE
SET importacion_id = EXCLUDED.importacion_id,
    numero_fila_origen = EXCLUDED.numero_fila_origen,
    fecha_operacion = EXCLUDED.fecha_operacion,
    referencia = EXCLUDED.referencia,
    descripcion = EXCLUDED.descripcion,
    credito = EXCLUDED.credito,
    debito = EXCLUDED.debito,
    fecha_modificacion = now();

COMMIT;
