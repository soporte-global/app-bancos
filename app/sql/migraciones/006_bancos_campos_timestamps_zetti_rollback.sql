BEGIN;

DO $$
DECLARE
    rec RECORD;
BEGIN
    FOR rec IN
        SELECT table_name, column_name
        FROM information_schema.columns
        WHERE table_schema = 'global_prod'
            AND table_name LIKE 'bancos\_%'
            AND column_name = 'fecha_creacion'
    LOOP
        EXECUTE format(
            'ALTER TABLE global_prod.%I RENAME COLUMN %I TO created_at',
            rec.table_name,
            rec.column_name
        );
    END LOOP;

    FOR rec IN
        SELECT table_name, column_name
        FROM information_schema.columns
        WHERE table_schema = 'global_prod'
            AND table_name LIKE 'bancos\_%'
            AND column_name = 'usuario_creacion'
    LOOP
        EXECUTE format(
            'ALTER TABLE global_prod.%I RENAME COLUMN %I TO created_by',
            rec.table_name,
            rec.column_name
        );
    END LOOP;

    FOR rec IN
        SELECT table_name, column_name
        FROM information_schema.columns
        WHERE table_schema = 'global_prod'
            AND table_name LIKE 'bancos\_%'
            AND column_name = 'fecha_modificacion'
    LOOP
        EXECUTE format(
            'ALTER TABLE global_prod.%I RENAME COLUMN %I TO updated_at',
            rec.table_name,
            rec.column_name
        );
    END LOOP;

    FOR rec IN
        SELECT table_name, column_name
        FROM information_schema.columns
        WHERE table_schema = 'global_prod'
            AND table_name LIKE 'bancos\_%'
            AND column_name = 'usuario_modificacion'
    LOOP
        EXECUTE format(
            'ALTER TABLE global_prod.%I RENAME COLUMN %I TO updated_by',
            rec.table_name,
            rec.column_name
        );
    END LOOP;
END
$$;

COMMIT;
