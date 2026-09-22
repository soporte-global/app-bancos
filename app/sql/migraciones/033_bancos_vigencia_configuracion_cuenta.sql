BEGIN;

ALTER TABLE global_prod.bancos_configuracion_cuenta
    ADD COLUMN IF NOT EXISTS activo boolean,
    ADD COLUMN IF NOT EXISTS origen varchar(20);
ALTER TABLE global_temp.bancos_configuracion_cuenta
    ADD COLUMN IF NOT EXISTS activo boolean,
    ADD COLUMN IF NOT EXISTS origen varchar(20);

UPDATE global_prod.bancos_configuracion_cuenta SET activo = true WHERE activo IS NULL;
UPDATE global_prod.bancos_configuracion_cuenta SET origen = 'MIGRACION' WHERE origen IS NULL;
UPDATE global_temp.bancos_configuracion_cuenta SET activo = true WHERE activo IS NULL;
UPDATE global_temp.bancos_configuracion_cuenta SET origen = 'MIGRACION' WHERE origen IS NULL;

ALTER TABLE global_prod.bancos_configuracion_cuenta
    ALTER COLUMN activo SET DEFAULT true,
    ALTER COLUMN activo SET NOT NULL,
    ALTER COLUMN origen SET DEFAULT 'MANUAL',
    ALTER COLUMN origen SET NOT NULL;
ALTER TABLE global_temp.bancos_configuracion_cuenta
    ALTER COLUMN activo SET DEFAULT true,
    ALTER COLUMN activo SET NOT NULL,
    ALTER COLUMN origen SET DEFAULT 'MANUAL',
    ALTER COLUMN origen SET NOT NULL;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conrelid = 'global_prod.bancos_configuracion_cuenta'::regclass
          AND conname = 'bancos_configuracion_cuenta_origen_check'
    ) THEN
        ALTER TABLE global_prod.bancos_configuracion_cuenta
            ADD CONSTRAINT bancos_configuracion_cuenta_origen_check
            CHECK (origen IN ('MIGRACION', 'IMPORTACION', 'MANUAL'));
    END IF;
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conrelid = 'global_temp.bancos_configuracion_cuenta'::regclass
          AND conname = 'bancos_configuracion_cuenta_origen_check'
    ) THEN
        ALTER TABLE global_temp.bancos_configuracion_cuenta
            ADD CONSTRAINT bancos_configuracion_cuenta_origen_check
            CHECK (origen IN ('MIGRACION', 'IMPORTACION', 'MANUAL'));
    END IF;
END
$$;

INSERT INTO global_prod.bancos_configuracion_cuenta
    (configuracion_id, cuenta_bancaria_zetti_id, activo, origen)
SELECT DISTINCT i.configuracion_id, i.cuenta_bancaria_zetti_id, true, 'IMPORTACION'
FROM global_prod.bancos_importacion_extracto i
WHERE NOT EXISTS (
    SELECT 1 FROM global_prod.bancos_configuracion_cuenta cc
    WHERE cc.configuracion_id = i.configuracion_id
      AND cc.cuenta_bancaria_zetti_id = i.cuenta_bancaria_zetti_id
);

INSERT INTO global_temp.bancos_configuracion_cuenta
    (configuracion_id, cuenta_bancaria_zetti_id, activo, origen)
SELECT DISTINCT i.configuracion_id, i.cuenta_bancaria_zetti_id, true, 'IMPORTACION'
FROM global_temp.bancos_importacion_extracto i
WHERE NOT EXISTS (
    SELECT 1 FROM global_temp.bancos_configuracion_cuenta cc
    WHERE cc.configuracion_id = i.configuracion_id
      AND cc.cuenta_bancaria_zetti_id = i.cuenta_bancaria_zetti_id
);

CREATE INDEX IF NOT EXISTS bancos_configuracion_cuenta_activa_idx
    ON global_prod.bancos_configuracion_cuenta(configuracion_id, cuenta_bancaria_zetti_id)
    WHERE activo IS TRUE;
CREATE INDEX IF NOT EXISTS bancos_configuracion_cuenta_activa_idx
    ON global_temp.bancos_configuracion_cuenta(configuracion_id, cuenta_bancaria_zetti_id)
    WHERE activo IS TRUE;

COMMENT ON COLUMN global_prod.bancos_configuracion_cuenta.activo IS
    'Determina si la configuracion puede usarse en futuras operaciones de la cuenta.';
COMMENT ON COLUMN global_prod.bancos_configuracion_cuenta.origen IS
    'Procedencia inicial del vinculo; los cambios posteriores se auditan por comando.';

COMMIT;
