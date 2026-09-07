BEGIN;

-- Sandbox operativo de BANCOS. No copia filas ni modifica public/global_prod:
-- sólo crea en global_temp las estructuras necesarias para cargar datos de
-- prueba. La autenticación Hub continúa deliberadamente en global_prod.
CREATE SCHEMA IF NOT EXISTS global_temp;

DO $$
DECLARE
    origen record;
    columna record;
    secuencia text;
    definicion record;
    creada boolean;
BEGIN
    -- Se clonan todas las tablas operativas BANCOS, incluso las de trazabilidad
    -- que ya existan al ejecutar esta migración. Las dependencias directas del
    -- ERP y las tablas legacy usadas en conciliaciones se clonan sin datos.
    FOR origen IN
        SELECT 'global_prod'::text AS esquema, c.relname AS tabla
        FROM pg_class c
        JOIN pg_namespace n ON n.oid = c.relnamespace
        WHERE n.nspname = 'global_prod'
          AND c.relkind = 'r'
          AND c.relname LIKE 'bancos\_%' ESCAPE E'\\'
        UNION
        SELECT 'public'::text, c.relname
        FROM pg_class c
        JOIN pg_namespace n ON n.oid = c.relnamespace
        WHERE n.nspname = 'public'
          AND c.relkind = 'r'
          AND (
              c.relname IN (
                  'asiento', 'cuenta', 'cuenta_bancaria', 'entidad',
                  'movimiento', 'nodo', 'operacion', 'operacion_valor',
                  'valor', 'valor_concepto', 'chequera'
              )
              OR c.relname LIKE 'bancos\_%' ESCAPE E'\\'
          )
    LOOP
        creada := false;
        IF to_regclass(format('global_temp.%I', origen.tabla)) IS NULL THEN
            EXECUTE format(
                'CREATE TABLE global_temp.%I (LIKE %I.%I INCLUDING ALL)',
                origen.tabla,
                origen.esquema,
                origen.tabla
            );
            creada := true;
        END IF;

        -- LIKE copia el DEFAULT de los bigserial. Se lo reemplaza siempre por
        -- una secuencia de global_temp para que un INSERT de debug no avance
        -- una secuencia productiva. Las tablas existentes no se alteran.
        IF creada THEN
        FOR columna IN
            SELECT a.attname,
                   pg_get_expr(d.adbin, d.adrelid) AS expresion
            FROM pg_attribute a
            JOIN pg_attrdef d
              ON d.adrelid = a.attrelid
             AND d.adnum = a.attnum
            WHERE a.attrelid = format('global_temp.%I', origen.tabla)::regclass
              AND a.attnum > 0
              AND NOT a.attisdropped
        LOOP
            IF columna.expresion LIKE 'nextval(%' THEN
                secuencia := left(
                    format('bancos_debug_%s_%s_seq', origen.tabla, columna.attname),
                    54
                ) || '_' || substr(md5(origen.tabla || ':' || columna.attname), 1, 8);
                EXECUTE format('CREATE SEQUENCE IF NOT EXISTS global_temp.%I', secuencia);
                EXECUTE format(
                    'ALTER TABLE global_temp.%I ALTER COLUMN %I SET DEFAULT nextval(%L::regclass)',
                    origen.tabla,
                    columna.attname,
                    'global_temp.' || secuencia
                );
                EXECUTE format(
                    'ALTER SEQUENCE global_temp.%I OWNED BY global_temp.%I.%I',
                    secuencia,
                    origen.tabla,
                    columna.attname
                );
            END IF;
        END LOOP;
        END IF;
    END LOOP;

    -- LIKE no traslada FKs. Se recrean sólo para las tablas operativas BANCOS;
    -- las referencias a public pasan a global_temp y las de Hub permanecen en
    -- global_prod para conservar la identidad real de la sesión.
    FOR definicion IN
        SELECT con.conname,
               rel.relname AS tabla,
               regexp_replace(
                   replace(pg_get_constraintdef(con.oid), 'REFERENCES public.', 'REFERENCES global_temp.'),
                   E'REFERENCES global_prod\\.(bancos_[a-z0-9_]+)',
                   E'REFERENCES global_temp.\\1',
                   'g'
               ) AS sql
        FROM pg_constraint con
        JOIN pg_class rel ON rel.oid = con.conrelid
        JOIN pg_namespace ns ON ns.oid = rel.relnamespace
        WHERE con.contype = 'f'
          AND ns.nspname = 'global_prod'
          AND rel.relname LIKE 'bancos\_%' ESCAPE E'\\'
    LOOP
        IF NOT EXISTS (
            SELECT 1
            FROM pg_constraint destino
            WHERE destino.conrelid = format('global_temp.%I', definicion.tabla)::regclass
              AND destino.conname = definicion.conname
        ) THEN
            EXECUTE format(
                'ALTER TABLE global_temp.%I ADD CONSTRAINT %I %s',
                definicion.tabla,
                definicion.conname,
                definicion.sql
            );
        END IF;
    END LOOP;
END;
$$;

COMMENT ON SCHEMA global_temp IS
    'Sandbox de BANCOS: estructuras vacías para debug. No contiene ni debe escribir datos productivos.';

COMMIT;

-- Para proteger el sandbox también a nivel de conexión, el usuario de debug
-- debe tener INSERT/UPDATE/DELETE solamente en global_temp y no en public ni
-- global_prod. No se emiten GRANT/REVOKE aquí porque esos privilegios son
-- administrados por DBA y pueden afectar otras aplicaciones.
