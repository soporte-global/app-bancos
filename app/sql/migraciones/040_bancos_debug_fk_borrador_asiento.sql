BEGIN;

CREATE UNIQUE INDEX IF NOT EXISTS bancos_debug_asiento_id_uk
    ON global_temp.asiento(id);

DO $$
DECLARE
    esquema_destino text;
BEGIN
    SELECT n.nspname
    INTO esquema_destino
    FROM pg_constraint c
    JOIN pg_class r ON r.oid = c.confrelid
    JOIN pg_namespace n ON n.oid = r.relnamespace
    WHERE c.conrelid = 'global_temp.bancos_borrador_asiento'::regclass
      AND c.conname = 'bancos_borrador_asiento_asiento_zetti_fk';

    IF esquema_destino IS DISTINCT FROM 'global_temp' THEN
        ALTER TABLE global_temp.bancos_borrador_asiento
            DROP CONSTRAINT IF EXISTS bancos_borrador_asiento_asiento_zetti_fk;
        ALTER TABLE global_temp.bancos_borrador_asiento
            ADD CONSTRAINT bancos_borrador_asiento_asiento_zetti_fk
            FOREIGN KEY (asiento_zetti_id) REFERENCES global_temp.asiento(id);
    END IF;
END
$$;

COMMIT;
