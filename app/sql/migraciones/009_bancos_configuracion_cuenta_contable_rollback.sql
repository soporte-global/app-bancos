BEGIN;

ALTER TABLE global_prod.bancos_configuracion
    DROP CONSTRAINT IF EXISTS bancos_configuracion_cuenta_contable_fk;

DROP INDEX IF EXISTS global_prod.bancos_configuracion_cuenta_contable_idx;

ALTER TABLE global_prod.bancos_configuracion
    DROP COLUMN IF EXISTS cuenta_contable_zetti_id;

COMMIT;
