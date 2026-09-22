BEGIN;

DO $$
BEGIN
    IF EXISTS (
        SELECT 1 FROM global_temp.bancos_sandbox_sincronizacion
        WHERE estado = 'REQUIERE_DECISION'
    ) THEN
        RAISE EXCEPTION 'No se puede retirar 047: existen sincronizaciones con conflictos';
    END IF;
END;
$$;

ALTER TABLE global_temp.bancos_sandbox_sincronizacion
    DROP CONSTRAINT bancos_sandbox_sincronizacion_estado_check;
ALTER TABLE global_temp.bancos_sandbox_sincronizacion
    ADD CONSTRAINT bancos_sandbox_sincronizacion_estado_check
    CHECK (estado IN ('EN_CURSO', 'OK', 'ERROR'));

COMMIT;
