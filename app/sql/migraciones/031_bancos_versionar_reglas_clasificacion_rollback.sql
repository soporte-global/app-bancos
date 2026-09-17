BEGIN;

DO $$
BEGIN
    IF EXISTS (
        SELECT 1 FROM global_prod.bancos_regla_clasificacion
        WHERE activo IS FALSE OR version <> 1 OR reemplaza_regla_id IS NOT NULL
    ) OR EXISTS (
        SELECT 1 FROM global_temp.bancos_regla_clasificacion
        WHERE activo IS FALSE OR version <> 1 OR reemplaza_regla_id IS NOT NULL
    ) THEN
        RAISE EXCEPTION 'No se puede retirar el versionado: ya existen reglas retiradas o reemplazadas';
    END IF;
END
$$;

DROP INDEX IF EXISTS global_prod.bancos_regla_clasificacion_configuracion_activa_idx;
DROP INDEX IF EXISTS global_prod.bancos_regla_clasificacion_reemplaza_uk;
DROP INDEX IF EXISTS global_prod.bancos_regla_clasificacion_uk;
DROP INDEX IF EXISTS global_temp.bancos_regla_clasificacion_configuracion_activa_idx;
DROP INDEX IF EXISTS global_temp.bancos_regla_clasificacion_reemplaza_uk;
DROP INDEX IF EXISTS global_temp.bancos_regla_clasificacion_uk;

ALTER TABLE global_prod.bancos_regla_clasificacion
    DROP CONSTRAINT IF EXISTS bancos_regla_clasificacion_reemplaza_fk,
    DROP CONSTRAINT IF EXISTS bancos_regla_clasificacion_version_check,
    DROP COLUMN IF EXISTS reemplaza_regla_id,
    DROP COLUMN IF EXISTS version,
    DROP COLUMN IF EXISTS activo;
ALTER TABLE global_temp.bancos_regla_clasificacion
    DROP CONSTRAINT IF EXISTS bancos_regla_clasificacion_reemplaza_fk,
    DROP CONSTRAINT IF EXISTS bancos_regla_clasificacion_version_check,
    DROP COLUMN IF EXISTS reemplaza_regla_id,
    DROP COLUMN IF EXISTS version,
    DROP COLUMN IF EXISTS activo;

CREATE UNIQUE INDEX bancos_regla_clasificacion_uk
    ON global_prod.bancos_regla_clasificacion
       (configuracion_id, subtipo_valor_zetti_id, sentido, COALESCE(codigo_extracto, ''));
CREATE UNIQUE INDEX bancos_regla_clasificacion_uk
    ON global_temp.bancos_regla_clasificacion
       (configuracion_id, subtipo_valor_zetti_id, sentido, COALESCE(codigo_extracto, ''));

COMMIT;
