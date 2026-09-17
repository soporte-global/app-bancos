BEGIN;

ALTER TABLE global_prod.bancos_regla_clasificacion
    ADD COLUMN IF NOT EXISTS activo boolean,
    ADD COLUMN IF NOT EXISTS version integer,
    ADD COLUMN IF NOT EXISTS reemplaza_regla_id bigint;
ALTER TABLE global_temp.bancos_regla_clasificacion
    ADD COLUMN IF NOT EXISTS activo boolean,
    ADD COLUMN IF NOT EXISTS version integer,
    ADD COLUMN IF NOT EXISTS reemplaza_regla_id bigint;

UPDATE global_prod.bancos_regla_clasificacion SET activo = true WHERE activo IS NULL;
UPDATE global_prod.bancos_regla_clasificacion SET version = 1 WHERE version IS NULL;
UPDATE global_temp.bancos_regla_clasificacion SET activo = true WHERE activo IS NULL;
UPDATE global_temp.bancos_regla_clasificacion SET version = 1 WHERE version IS NULL;

ALTER TABLE global_prod.bancos_regla_clasificacion
    ALTER COLUMN activo SET DEFAULT true,
    ALTER COLUMN activo SET NOT NULL,
    ALTER COLUMN version SET DEFAULT 1,
    ALTER COLUMN version SET NOT NULL;
ALTER TABLE global_temp.bancos_regla_clasificacion
    ALTER COLUMN activo SET DEFAULT true,
    ALTER COLUMN activo SET NOT NULL,
    ALTER COLUMN version SET DEFAULT 1,
    ALTER COLUMN version SET NOT NULL;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conrelid = 'global_prod.bancos_regla_clasificacion'::regclass
          AND conname = 'bancos_regla_clasificacion_version_check'
    ) THEN
        ALTER TABLE global_prod.bancos_regla_clasificacion
            ADD CONSTRAINT bancos_regla_clasificacion_version_check CHECK (version > 0);
    END IF;
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conrelid = 'global_prod.bancos_regla_clasificacion'::regclass
          AND conname = 'bancos_regla_clasificacion_reemplaza_fk'
    ) THEN
        ALTER TABLE global_prod.bancos_regla_clasificacion
            ADD CONSTRAINT bancos_regla_clasificacion_reemplaza_fk
            FOREIGN KEY (reemplaza_regla_id)
            REFERENCES global_prod.bancos_regla_clasificacion(id);
    END IF;
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conrelid = 'global_temp.bancos_regla_clasificacion'::regclass
          AND conname = 'bancos_regla_clasificacion_version_check'
    ) THEN
        ALTER TABLE global_temp.bancos_regla_clasificacion
            ADD CONSTRAINT bancos_regla_clasificacion_version_check CHECK (version > 0);
    END IF;
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conrelid = 'global_temp.bancos_regla_clasificacion'::regclass
          AND conname = 'bancos_regla_clasificacion_reemplaza_fk'
    ) THEN
        ALTER TABLE global_temp.bancos_regla_clasificacion
            ADD CONSTRAINT bancos_regla_clasificacion_reemplaza_fk
            FOREIGN KEY (reemplaza_regla_id)
            REFERENCES global_temp.bancos_regla_clasificacion(id);
    END IF;
END
$$;

DROP INDEX IF EXISTS global_prod.bancos_regla_clasificacion_uk;
DROP INDEX IF EXISTS global_temp.bancos_regla_clasificacion_uk;
-- La copia inicial de global_temp puede conservar el nombre generado al clonar
-- el índice de producción. Debe retirarse también para permitir varias versiones
-- históricas de una misma clave lógica.
DROP INDEX IF EXISTS global_temp.bancos_regla_clasificacion_configuracion_id_subtipo_valor_e_idx;

CREATE UNIQUE INDEX IF NOT EXISTS bancos_regla_clasificacion_uk
    ON global_prod.bancos_regla_clasificacion
       (configuracion_id, subtipo_valor_zetti_id, sentido, COALESCE(codigo_extracto, ''))
    WHERE activo IS TRUE;
CREATE UNIQUE INDEX IF NOT EXISTS bancos_regla_clasificacion_reemplaza_uk
    ON global_prod.bancos_regla_clasificacion(reemplaza_regla_id)
    WHERE reemplaza_regla_id IS NOT NULL;
CREATE INDEX IF NOT EXISTS bancos_regla_clasificacion_configuracion_activa_idx
    ON global_prod.bancos_regla_clasificacion(configuracion_id, id)
    WHERE activo IS TRUE;

CREATE UNIQUE INDEX IF NOT EXISTS bancos_regla_clasificacion_uk
    ON global_temp.bancos_regla_clasificacion
       (configuracion_id, subtipo_valor_zetti_id, sentido, COALESCE(codigo_extracto, ''))
    WHERE activo IS TRUE;
CREATE UNIQUE INDEX IF NOT EXISTS bancos_regla_clasificacion_reemplaza_uk
    ON global_temp.bancos_regla_clasificacion(reemplaza_regla_id)
    WHERE reemplaza_regla_id IS NOT NULL;
CREATE INDEX IF NOT EXISTS bancos_regla_clasificacion_configuracion_activa_idx
    ON global_temp.bancos_regla_clasificacion(configuracion_id, id)
    WHERE activo IS TRUE;

COMMENT ON COLUMN global_prod.bancos_regla_clasificacion.activo IS
    'Sólo las reglas activas participan de la clasificación.';
COMMENT ON COLUMN global_prod.bancos_regla_clasificacion.version IS
    'Versión creciente dentro de una cadena de reemplazos.';
COMMENT ON COLUMN global_prod.bancos_regla_clasificacion.reemplaza_regla_id IS
    'Regla histórica reemplazada por esta versión.';

COMMIT;
