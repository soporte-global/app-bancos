BEGIN;

DO $$
BEGIN
    IF to_regclass('global_temp.bancos_conciliacion_cheque_fk_public_legacy') IS NOT NULL THEN
        IF EXISTS (SELECT 1 FROM global_temp.bancos_conciliacion_cheque) THEN
            RAISE EXCEPTION 'No se puede revertir 042: existen conciliaciones creadas en sandbox';
        END IF;
        DROP TABLE global_temp.bancos_conciliacion_cheque;
        ALTER TABLE global_temp.bancos_conciliacion_cheque_fk_public_legacy
            RENAME TO bancos_conciliacion_cheque;
    END IF;
END
$$;

DROP INDEX IF EXISTS global_temp.bancos_debug_operacion_id_uk;
DROP INDEX IF EXISTS global_temp.bancos_debug_valor_id_uk;
DROP SEQUENCE IF EXISTS global_temp.bancos_conciliacion_cheque_sandbox_id_seq;

COMMIT;
