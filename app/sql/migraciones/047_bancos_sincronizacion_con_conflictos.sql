BEGIN;

ALTER TABLE global_temp.bancos_sandbox_sincronizacion
    DROP CONSTRAINT bancos_sandbox_sincronizacion_estado_check;
ALTER TABLE global_temp.bancos_sandbox_sincronizacion
    ADD CONSTRAINT bancos_sandbox_sincronizacion_estado_check
    CHECK (estado IN ('EN_CURSO', 'OK', 'REQUIERE_DECISION', 'ERROR'));

COMMIT;
