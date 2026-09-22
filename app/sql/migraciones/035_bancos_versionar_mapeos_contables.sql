BEGIN;

ALTER TABLE global_prod.bancos_mapeo_cuenta_contable
    ADD COLUMN IF NOT EXISTS activo boolean,
    ADD COLUMN IF NOT EXISTS version integer,
    ADD COLUMN IF NOT EXISTS reemplaza_mapeo_id bigint,
    ADD COLUMN IF NOT EXISTS origen varchar(20);
ALTER TABLE global_temp.bancos_mapeo_cuenta_contable
    ADD COLUMN IF NOT EXISTS activo boolean,
    ADD COLUMN IF NOT EXISTS version integer,
    ADD COLUMN IF NOT EXISTS reemplaza_mapeo_id bigint,
    ADD COLUMN IF NOT EXISTS origen varchar(20);

UPDATE global_prod.bancos_mapeo_cuenta_contable SET activo=true WHERE activo IS NULL;
UPDATE global_prod.bancos_mapeo_cuenta_contable SET version=1 WHERE version IS NULL;
UPDATE global_prod.bancos_mapeo_cuenta_contable SET origen='MIGRACION' WHERE origen IS NULL;
UPDATE global_temp.bancos_mapeo_cuenta_contable SET activo=true WHERE activo IS NULL;
UPDATE global_temp.bancos_mapeo_cuenta_contable SET version=1 WHERE version IS NULL;
UPDATE global_temp.bancos_mapeo_cuenta_contable SET origen='MIGRACION' WHERE origen IS NULL;

ALTER TABLE global_prod.bancos_mapeo_cuenta_contable
    ALTER COLUMN activo SET DEFAULT true, ALTER COLUMN activo SET NOT NULL,
    ALTER COLUMN version SET DEFAULT 1, ALTER COLUMN version SET NOT NULL,
    ALTER COLUMN origen SET DEFAULT 'MANUAL', ALTER COLUMN origen SET NOT NULL;
ALTER TABLE global_temp.bancos_mapeo_cuenta_contable
    ALTER COLUMN activo SET DEFAULT true, ALTER COLUMN activo SET NOT NULL,
    ALTER COLUMN version SET DEFAULT 1, ALTER COLUMN version SET NOT NULL,
    ALTER COLUMN origen SET DEFAULT 'MANUAL', ALTER COLUMN origen SET NOT NULL;

DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conrelid='global_prod.bancos_mapeo_cuenta_contable'::regclass AND conname='bancos_mapeo_cuenta_contable_version_check') THEN
        ALTER TABLE global_prod.bancos_mapeo_cuenta_contable ADD CONSTRAINT bancos_mapeo_cuenta_contable_version_check CHECK (version > 0);
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conrelid='global_prod.bancos_mapeo_cuenta_contable'::regclass AND conname='bancos_mapeo_cuenta_contable_origen_check') THEN
        ALTER TABLE global_prod.bancos_mapeo_cuenta_contable ADD CONSTRAINT bancos_mapeo_cuenta_contable_origen_check CHECK (origen IN ('MIGRACION','SOMBRA','MANUAL'));
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conrelid='global_prod.bancos_mapeo_cuenta_contable'::regclass AND conname='bancos_mapeo_cuenta_contable_reemplaza_fk') THEN
        ALTER TABLE global_prod.bancos_mapeo_cuenta_contable ADD CONSTRAINT bancos_mapeo_cuenta_contable_reemplaza_fk FOREIGN KEY (reemplaza_mapeo_id) REFERENCES global_prod.bancos_mapeo_cuenta_contable(id);
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conrelid='global_temp.bancos_mapeo_cuenta_contable'::regclass AND conname='bancos_mapeo_cuenta_contable_version_check') THEN
        ALTER TABLE global_temp.bancos_mapeo_cuenta_contable ADD CONSTRAINT bancos_mapeo_cuenta_contable_version_check CHECK (version > 0);
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conrelid='global_temp.bancos_mapeo_cuenta_contable'::regclass AND conname='bancos_mapeo_cuenta_contable_origen_check') THEN
        ALTER TABLE global_temp.bancos_mapeo_cuenta_contable ADD CONSTRAINT bancos_mapeo_cuenta_contable_origen_check CHECK (origen IN ('MIGRACION','SOMBRA','MANUAL'));
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conrelid='global_temp.bancos_mapeo_cuenta_contable'::regclass AND conname='bancos_mapeo_cuenta_contable_reemplaza_fk') THEN
        ALTER TABLE global_temp.bancos_mapeo_cuenta_contable ADD CONSTRAINT bancos_mapeo_cuenta_contable_reemplaza_fk FOREIGN KEY (reemplaza_mapeo_id) REFERENCES global_temp.bancos_mapeo_cuenta_contable(id);
    END IF;
END
$$;

INSERT INTO global_temp.bancos_mapeo_cuenta_contable
    (configuracion_id, subtipo_valor_zetti_id, cuenta_zetti_id, regla_orden,
     observacion, activo, version, origen)
SELECT destino.id, origen.subtipo_valor_zetti_id, origen.cuenta_zetti_id,
       origen.regla_orden, 'SOMBRA-035|MAPEO|' || origen.id, true, 1, 'SOMBRA'
FROM global_temp.bancos_configuracion destino
JOIN global_prod.bancos_mapeo_cuenta_contable origen
  ON origen.configuracion_id=split_part(destino.observacion, '|', 3)::bigint
WHERE destino.observacion LIKE 'SOMBRA-016|CONFIGURACION|%'
   OR destino.observacion LIKE 'SOMBRA-017|CONFIGURACION|%'
ON CONFLICT DO NOTHING;

ALTER TABLE global_prod.bancos_mapeo_cuenta_contable DROP CONSTRAINT IF EXISTS bancos_mapeo_cuenta_contable_uk;
ALTER TABLE global_temp.bancos_mapeo_cuenta_contable DROP CONSTRAINT IF EXISTS bancos_mapeo_cuenta_contable_configuracion_id_subtipo_valor_key;

CREATE UNIQUE INDEX IF NOT EXISTS bancos_mapeo_cuenta_contable_activo_uk
    ON global_prod.bancos_mapeo_cuenta_contable(configuracion_id, subtipo_valor_zetti_id)
    WHERE activo IS TRUE;
CREATE UNIQUE INDEX IF NOT EXISTS bancos_mapeo_cuenta_contable_reemplaza_uk
    ON global_prod.bancos_mapeo_cuenta_contable(reemplaza_mapeo_id)
    WHERE reemplaza_mapeo_id IS NOT NULL;
CREATE UNIQUE INDEX IF NOT EXISTS bancos_mapeo_cuenta_contable_activo_uk
    ON global_temp.bancos_mapeo_cuenta_contable(configuracion_id, subtipo_valor_zetti_id)
    WHERE activo IS TRUE;
CREATE UNIQUE INDEX IF NOT EXISTS bancos_mapeo_cuenta_contable_reemplaza_uk
    ON global_temp.bancos_mapeo_cuenta_contable(reemplaza_mapeo_id)
    WHERE reemplaza_mapeo_id IS NOT NULL;

COMMIT;
