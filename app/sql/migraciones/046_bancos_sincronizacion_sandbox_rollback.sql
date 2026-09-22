BEGIN;

DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM global_temp.bancos_sandbox_sincronizacion) THEN
        RAISE EXCEPTION 'No se puede retirar 046: existen sincronizaciones registradas';
    END IF;
END;
$$;

DROP TABLE IF EXISTS global_temp.bancos_sandbox_sincronizacion;

COMMIT;
