BEGIN;
DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM global_prod.bancos_mapeo_cuenta_contable WHERE activo IS FALSE OR version<>1 OR reemplaza_mapeo_id IS NOT NULL OR origen='MANUAL')
       OR EXISTS (SELECT 1 FROM global_temp.bancos_mapeo_cuenta_contable WHERE activo IS FALSE OR version<>1 OR reemplaza_mapeo_id IS NOT NULL OR origen='MANUAL') THEN
        RAISE EXCEPTION 'No se puede retirar el versionado: ya existen mapeos administrados';
    END IF;
END
$$;
DELETE FROM global_temp.bancos_mapeo_cuenta_contable WHERE origen='SOMBRA';
DROP INDEX IF EXISTS global_prod.bancos_mapeo_cuenta_contable_reemplaza_uk;
DROP INDEX IF EXISTS global_prod.bancos_mapeo_cuenta_contable_activo_uk;
DROP INDEX IF EXISTS global_temp.bancos_mapeo_cuenta_contable_reemplaza_uk;
DROP INDEX IF EXISTS global_temp.bancos_mapeo_cuenta_contable_activo_uk;
ALTER TABLE global_prod.bancos_mapeo_cuenta_contable DROP CONSTRAINT IF EXISTS bancos_mapeo_cuenta_contable_reemplaza_fk, DROP CONSTRAINT IF EXISTS bancos_mapeo_cuenta_contable_origen_check, DROP CONSTRAINT IF EXISTS bancos_mapeo_cuenta_contable_version_check, DROP COLUMN IF EXISTS origen, DROP COLUMN IF EXISTS reemplaza_mapeo_id, DROP COLUMN IF EXISTS version, DROP COLUMN IF EXISTS activo;
ALTER TABLE global_temp.bancos_mapeo_cuenta_contable DROP CONSTRAINT IF EXISTS bancos_mapeo_cuenta_contable_reemplaza_fk, DROP CONSTRAINT IF EXISTS bancos_mapeo_cuenta_contable_origen_check, DROP CONSTRAINT IF EXISTS bancos_mapeo_cuenta_contable_version_check, DROP COLUMN IF EXISTS origen, DROP COLUMN IF EXISTS reemplaza_mapeo_id, DROP COLUMN IF EXISTS version, DROP COLUMN IF EXISTS activo;
ALTER TABLE global_prod.bancos_mapeo_cuenta_contable ADD CONSTRAINT bancos_mapeo_cuenta_contable_uk UNIQUE (configuracion_id, subtipo_valor_zetti_id, cuenta_zetti_id);
ALTER TABLE global_temp.bancos_mapeo_cuenta_contable ADD CONSTRAINT bancos_mapeo_cuenta_contable_configuracion_id_subtipo_valor_key UNIQUE (configuracion_id, subtipo_valor_zetti_id, cuenta_zetti_id);
COMMIT;
