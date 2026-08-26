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
            AND column_name IN ('created_at', 'creado_en')
    LOOP
        EXECUTE format(
            'ALTER TABLE global_prod.%I RENAME COLUMN %I TO fecha_creacion',
            rec.table_name,
            rec.column_name
        );
    END LOOP;

    FOR rec IN
        SELECT table_name, column_name
        FROM information_schema.columns
        WHERE table_schema = 'global_prod'
            AND table_name LIKE 'bancos\_%'
            AND column_name IN ('created_by', 'creado_por')
    LOOP
        EXECUTE format(
            'ALTER TABLE global_prod.%I RENAME COLUMN %I TO usuario_creacion',
            rec.table_name,
            rec.column_name
        );
    END LOOP;

    FOR rec IN
        SELECT table_name, column_name
        FROM information_schema.columns
        WHERE table_schema = 'global_prod'
            AND table_name LIKE 'bancos\_%'
            AND column_name IN ('updated_at', 'update_at')
    LOOP
        EXECUTE format(
            'ALTER TABLE global_prod.%I RENAME COLUMN %I TO fecha_modificacion',
            rec.table_name,
            rec.column_name
        );
    END LOOP;

    FOR rec IN
        SELECT table_name, column_name
        FROM information_schema.columns
        WHERE table_schema = 'global_prod'
            AND table_name LIKE 'bancos\_%'
            AND column_name IN ('updated_by', 'update_by')
    LOOP
        EXECUTE format(
            'ALTER TABLE global_prod.%I RENAME COLUMN %I TO usuario_modificacion',
            rec.table_name,
            rec.column_name
        );
    END LOOP;
END
$$;

COMMIT;
