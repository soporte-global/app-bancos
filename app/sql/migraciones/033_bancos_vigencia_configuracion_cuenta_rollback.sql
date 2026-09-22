BEGIN;

DO $$
BEGIN
    IF EXISTS (
        SELECT 1 FROM global_prod.bancos_configuracion_cuenta
        WHERE activo IS FALSE OR origen = 'MANUAL'
    ) OR EXISTS (
        SELECT 1 FROM global_temp.bancos_configuracion_cuenta
        WHERE activo IS FALSE OR origen = 'MANUAL'
    ) THEN
        RAISE EXCEPTION 'No se puede retirar la vigencia: ya existen vinculos administrados manualmente';
    END IF;
END
$$;

DELETE FROM global_prod.bancos_configuracion_cuenta WHERE origen = 'IMPORTACION';
DELETE FROM global_temp.bancos_configuracion_cuenta WHERE origen = 'IMPORTACION';

DROP INDEX IF EXISTS global_prod.bancos_configuracion_cuenta_activa_idx;
DROP INDEX IF EXISTS global_temp.bancos_configuracion_cuenta_activa_idx;

ALTER TABLE global_prod.bancos_configuracion_cuenta
    DROP CONSTRAINT IF EXISTS bancos_configuracion_cuenta_origen_check,
    DROP COLUMN IF EXISTS origen,
    DROP COLUMN IF EXISTS activo;
ALTER TABLE global_temp.bancos_configuracion_cuenta
    DROP CONSTRAINT IF EXISTS bancos_configuracion_cuenta_origen_check,
    DROP COLUMN IF EXISTS origen,
    DROP COLUMN IF EXISTS activo;

COMMIT;
