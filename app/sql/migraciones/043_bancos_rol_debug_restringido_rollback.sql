BEGIN;

DO $$
BEGIN
    IF EXISTS (
        SELECT 1
        FROM pg_roles
        WHERE rolname = 'app_bancos_debug_runtime'
    ) THEN
        REVOKE CONNECT ON DATABASE ftweb FROM app_bancos_debug_runtime;
        REVOKE ALL ON SCHEMA public, global_prod, global_temp
            FROM app_bancos_debug_runtime;
        REVOKE ALL ON ALL TABLES IN SCHEMA public, global_prod, global_temp
            FROM app_bancos_debug_runtime;
        REVOKE ALL ON ALL SEQUENCES IN SCHEMA public, global_prod, global_temp
            FROM app_bancos_debug_runtime;

        ALTER DEFAULT PRIVILEGES FOR ROLE postgres IN SCHEMA public, global_prod, global_temp
            REVOKE ALL ON TABLES FROM app_bancos_debug_runtime;
        ALTER DEFAULT PRIVILEGES FOR ROLE postgres IN SCHEMA public, global_prod, global_temp
            REVOKE ALL ON SEQUENCES FROM app_bancos_debug_runtime;

        DROP ROLE app_bancos_debug_runtime;
    END IF;
END;
$$;

COMMIT;

