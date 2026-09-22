BEGIN;

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

    IF esquema_destino = 'global_temp' THEN
        IF EXISTS (
            SELECT 1
            FROM global_temp.bancos_borrador_asiento b
            WHERE b.asiento_zetti_id IS NOT NULL
              AND NOT EXISTS (SELECT 1 FROM public.asiento a WHERE a.id=b.asiento_zetti_id)
        ) THEN
            RAISE EXCEPTION 'No se puede restaurar la FK a public.asiento: existen borradores materializados solo en sandbox';
        END IF;
        ALTER TABLE global_temp.bancos_borrador_asiento
            DROP CONSTRAINT bancos_borrador_asiento_asiento_zetti_fk;
        ALTER TABLE global_temp.bancos_borrador_asiento
            ADD CONSTRAINT bancos_borrador_asiento_asiento_zetti_fk
            FOREIGN KEY (asiento_zetti_id) REFERENCES public.asiento(id);
    END IF;
END
$$;

DROP INDEX IF EXISTS global_temp.bancos_debug_asiento_id_uk;

COMMIT;
