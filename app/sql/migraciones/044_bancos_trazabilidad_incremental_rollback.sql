BEGIN;

DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM global_prod.bancos_migracion_incremental_ejecucion)
       OR EXISTS (SELECT 1 FROM global_prod.bancos_migracion_cuenta_legacy WHERE lote_migracion <> 'ORIGINAL')
       OR EXISTS (SELECT 1 FROM global_prod.bancos_migracion_configuracion_legacy WHERE lote_migracion <> 'ORIGINAL')
       OR EXISTS (SELECT 1 FROM global_prod.bancos_migracion_periodo_legacy WHERE lote_migracion <> 'ORIGINAL')
       OR EXISTS (SELECT 1 FROM global_prod.bancos_migracion_movimiento_legacy WHERE lote_migracion <> 'ORIGINAL')
       OR EXISTS (SELECT 1 FROM global_prod.bancos_migracion_borrador_legacy WHERE lote_migracion <> 'ORIGINAL')
       OR EXISTS (SELECT 1 FROM global_prod.bancos_migracion_recurso_omitido WHERE lote_migracion <> 'ORIGINAL') THEN
        RAISE EXCEPTION 'No se puede retirar 044: existe trazabilidad incremental';
    END IF;
END;
$$;

DROP TABLE IF EXISTS global_prod.bancos_migracion_incremental_ejecucion;
ALTER TABLE global_prod.bancos_migracion_recurso_omitido DROP COLUMN IF EXISTS lote_migracion;
ALTER TABLE global_prod.bancos_migracion_borrador_legacy DROP COLUMN IF EXISTS lote_migracion;
ALTER TABLE global_prod.bancos_migracion_movimiento_legacy DROP COLUMN IF EXISTS lote_migracion;
ALTER TABLE global_prod.bancos_migracion_periodo_legacy DROP COLUMN IF EXISTS lote_migracion;
ALTER TABLE global_prod.bancos_migracion_configuracion_legacy DROP COLUMN IF EXISTS lote_migracion;
ALTER TABLE global_prod.bancos_migracion_cuenta_legacy DROP COLUMN IF EXISTS lote_migracion;

COMMIT;
