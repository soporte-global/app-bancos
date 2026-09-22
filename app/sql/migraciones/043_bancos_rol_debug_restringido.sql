BEGIN;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_roles
        WHERE rolname = 'app_bancos_debug_runtime'
    ) THEN
        CREATE ROLE app_bancos_debug_runtime
            NOLOGIN
            NOSUPERUSER
            NOCREATEDB
            NOCREATEROLE
            NOREPLICATION;
    ELSIF EXISTS (
        SELECT 1
        FROM pg_roles
        WHERE rolname = 'app_bancos_debug_runtime'
          AND (
              rolcanlogin
              OR rolsuper
              OR rolcreatedb
              OR rolcreaterole
              OR rolreplication
          )
    ) THEN
        RAISE EXCEPTION
            'app_bancos_debug_runtime existe con atributos incompatibles';
    END IF;
END;
$$;

GRANT CONNECT ON DATABASE ftweb TO app_bancos_debug_runtime;

REVOKE ALL ON SCHEMA public, global_prod, global_temp
    FROM app_bancos_debug_runtime;
GRANT USAGE ON SCHEMA public, global_prod, global_temp
    TO app_bancos_debug_runtime;

REVOKE ALL ON ALL TABLES IN SCHEMA public, global_prod
    FROM app_bancos_debug_runtime;
GRANT SELECT ON ALL TABLES IN SCHEMA public, global_prod
    TO app_bancos_debug_runtime;

REVOKE ALL ON ALL SEQUENCES IN SCHEMA public, global_prod
    FROM app_bancos_debug_runtime;

REVOKE ALL ON ALL TABLES IN SCHEMA global_temp
    FROM app_bancos_debug_runtime;
GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA global_temp
    TO app_bancos_debug_runtime;

REVOKE ALL ON ALL SEQUENCES IN SCHEMA global_temp
    FROM app_bancos_debug_runtime;
GRANT SELECT, USAGE, UPDATE ON ALL SEQUENCES IN SCHEMA global_temp
    TO app_bancos_debug_runtime;

ALTER DEFAULT PRIVILEGES FOR ROLE postgres IN SCHEMA public, global_prod
    REVOKE ALL ON TABLES FROM app_bancos_debug_runtime;
ALTER DEFAULT PRIVILEGES FOR ROLE postgres IN SCHEMA public, global_prod
    GRANT SELECT ON TABLES TO app_bancos_debug_runtime;
ALTER DEFAULT PRIVILEGES FOR ROLE postgres IN SCHEMA public, global_prod
    REVOKE ALL ON SEQUENCES FROM app_bancos_debug_runtime;

ALTER DEFAULT PRIVILEGES FOR ROLE postgres IN SCHEMA global_temp
    REVOKE ALL ON TABLES FROM app_bancos_debug_runtime;
ALTER DEFAULT PRIVILEGES FOR ROLE postgres IN SCHEMA global_temp
    GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES
    TO app_bancos_debug_runtime;
ALTER DEFAULT PRIVILEGES FOR ROLE postgres IN SCHEMA global_temp
    REVOKE ALL ON SEQUENCES FROM app_bancos_debug_runtime;
ALTER DEFAULT PRIVILEGES FOR ROLE postgres IN SCHEMA global_temp
    GRANT SELECT, USAGE, UPDATE ON SEQUENCES
    TO app_bancos_debug_runtime;

COMMENT ON ROLE app_bancos_debug_runtime IS
    'Capacidad APP BANCOS debug: lectura productiva y DML exclusivo en global_temp. Asignar a un login administrado por DBA.';

COMMIT;

