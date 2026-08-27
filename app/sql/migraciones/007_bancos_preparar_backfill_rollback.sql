BEGIN;

DROP TABLE IF EXISTS global_prod.bancos_migracion_movimiento_legacy;
DROP TABLE IF EXISTS global_prod.bancos_migracion_periodo_legacy;
DROP TABLE IF EXISTS global_prod.bancos_migracion_cuenta_legacy;

ALTER TABLE global_prod.bancos_movimiento_extracto
    ALTER COLUMN id_periodo TYPE integer USING NULLIF(id_periodo, '')::integer;

COMMIT;
