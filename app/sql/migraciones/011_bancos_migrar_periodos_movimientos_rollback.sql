BEGIN;

DELETE FROM global_prod.bancos_historial_asignacion
WHERE observacion = 'MIGRACION-011|estado actual legacy';

UPDATE global_prod.bancos_migracion_movimiento_legacy
SET movimiento_id = NULL
WHERE movimiento_id IS NOT NULL;

DELETE FROM global_prod.bancos_movimiento_extracto m
USING global_prod.bancos_migracion_periodo_legacy p
WHERE m.importacion_id = p.importacion_id;

UPDATE global_prod.bancos_migracion_periodo_legacy
SET importacion_id = NULL, configuracion_id = NULL;

DELETE FROM global_prod.bancos_importacion_extracto
WHERE observacion LIKE 'MIGRACION-011|%';

ALTER TABLE global_prod.bancos_migracion_periodo_legacy
    DROP COLUMN IF EXISTS configuracion_id;

COMMIT;
