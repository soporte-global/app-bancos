BEGIN;

DO $$
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = 'global_prod'
            AND table_name = 'bancos_configuracion'
            AND column_name = 'banco_erp_id'
    ) THEN
        ALTER TABLE global_prod.bancos_configuracion RENAME COLUMN banco_erp_id TO banco_zetti_id;
    END IF;

    IF EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = 'global_prod'
            AND table_name = 'bancos_configuracion'
            AND column_name = 'nodo_erp_id'
    ) THEN
        ALTER TABLE global_prod.bancos_configuracion RENAME COLUMN nodo_erp_id TO nodo_zetti_id;
    END IF;

    IF EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = 'global_prod'
            AND table_name = 'bancos_configuracion_cuenta'
            AND column_name = 'cuenta_bancaria_erp_id'
    ) THEN
        ALTER TABLE global_prod.bancos_configuracion_cuenta RENAME COLUMN cuenta_bancaria_erp_id TO cuenta_bancaria_zetti_id;
    END IF;

    IF EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = 'global_prod'
            AND table_name = 'bancos_regla_clasificacion'
            AND column_name = 'subtipo_valor_erp_id'
    ) THEN
        ALTER TABLE global_prod.bancos_regla_clasificacion RENAME COLUMN subtipo_valor_erp_id TO subtipo_valor_zetti_id;
    END IF;

    IF EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = 'global_prod'
            AND table_name = 'bancos_mapeo_cuenta_contable'
            AND column_name = 'subtipo_valor_erp_id'
    ) THEN
        ALTER TABLE global_prod.bancos_mapeo_cuenta_contable RENAME COLUMN subtipo_valor_erp_id TO subtipo_valor_zetti_id;
    END IF;

    IF EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = 'global_prod'
            AND table_name = 'bancos_mapeo_cuenta_contable'
            AND column_name = 'cuenta_erp_id'
    ) THEN
        ALTER TABLE global_prod.bancos_mapeo_cuenta_contable RENAME COLUMN cuenta_erp_id TO cuenta_zetti_id;
    END IF;

    IF EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = 'global_prod'
            AND table_name = 'bancos_regla_asignacion_usuario'
            AND column_name = 'subtipo_valor_erp_id'
    ) THEN
        ALTER TABLE global_prod.bancos_regla_asignacion_usuario RENAME COLUMN subtipo_valor_erp_id TO subtipo_valor_zetti_id;
    END IF;

    IF EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = 'global_prod'
            AND table_name = 'bancos_movimiento_extracto'
            AND column_name = 'subtipo_valor_erp_id'
    ) THEN
        ALTER TABLE global_prod.bancos_movimiento_extracto RENAME COLUMN subtipo_valor_erp_id TO subtipo_valor_zetti_id;
    END IF;

    IF EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = 'global_prod'
            AND table_name = 'bancos_importacion_extracto'
            AND column_name = 'cuenta_bancaria_erp_id'
    ) THEN
        ALTER TABLE global_prod.bancos_importacion_extracto RENAME COLUMN cuenta_bancaria_erp_id TO cuenta_bancaria_zetti_id;
    END IF;

    IF EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = 'global_prod'
            AND table_name = 'bancos_borrador_asiento'
            AND column_name = 'nodo_erp_id'
    ) THEN
        ALTER TABLE global_prod.bancos_borrador_asiento RENAME COLUMN nodo_erp_id TO nodo_zetti_id;
    END IF;

    IF EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = 'global_prod'
            AND table_name = 'bancos_borrador_asiento'
            AND column_name = 'operacion_erp_id'
    ) THEN
        ALTER TABLE global_prod.bancos_borrador_asiento RENAME COLUMN operacion_erp_id TO operacion_zetti_id;
    END IF;

    IF EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = 'global_prod'
            AND table_name = 'bancos_borrador_asiento'
            AND column_name = 'asiento_erp_id'
    ) THEN
        ALTER TABLE global_prod.bancos_borrador_asiento RENAME COLUMN asiento_erp_id TO asiento_zetti_id;
    END IF;

    IF EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = 'global_prod'
            AND table_name = 'bancos_linea_borrador_asiento'
            AND column_name = 'cuenta_erp_id'
    ) THEN
        ALTER TABLE global_prod.bancos_linea_borrador_asiento RENAME COLUMN cuenta_erp_id TO cuenta_zetti_id;
    END IF;

    IF EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = 'global_prod'
            AND table_name = 'bancos_asociacion_movimiento'
            AND column_name = 'valor_erp_id'
    ) THEN
        ALTER TABLE global_prod.bancos_asociacion_movimiento RENAME COLUMN valor_erp_id TO valor_zetti_id;
    END IF;

    IF EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = 'global_prod'
            AND table_name = 'bancos_asociacion_movimiento'
            AND column_name = 'asiento_erp_id'
    ) THEN
        ALTER TABLE global_prod.bancos_asociacion_movimiento RENAME COLUMN asiento_erp_id TO asiento_zetti_id;
    END IF;

    IF EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = 'global_prod'
            AND table_name = 'bancos_reserva_recurso'
            AND column_name = 'valor_erp_id'
    ) THEN
        ALTER TABLE global_prod.bancos_reserva_recurso RENAME COLUMN valor_erp_id TO valor_zetti_id;
    END IF;

    IF EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = 'global_prod'
            AND table_name = 'bancos_reserva_recurso'
            AND column_name = 'asiento_erp_id'
    ) THEN
        ALTER TABLE global_prod.bancos_reserva_recurso RENAME COLUMN asiento_erp_id TO asiento_zetti_id;
    END IF;

    IF EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = 'global_prod'
            AND table_name = 'bancos_conciliacion_cheque'
            AND column_name = 'valor_origen_erp_id'
    ) THEN
        ALTER TABLE global_prod.bancos_conciliacion_cheque RENAME COLUMN valor_origen_erp_id TO valor_origen_zetti_id;
    END IF;

    IF EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = 'global_prod'
            AND table_name = 'bancos_conciliacion_cheque'
            AND column_name = 'operacion_erp_id'
    ) THEN
        ALTER TABLE global_prod.bancos_conciliacion_cheque RENAME COLUMN operacion_erp_id TO operacion_zetti_id;
    END IF;

    IF EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = 'global_prod'
            AND table_name = 'bancos_conciliacion_cheque'
            AND column_name = 'valor_resultante_erp_id'
    ) THEN
        ALTER TABLE global_prod.bancos_conciliacion_cheque RENAME COLUMN valor_resultante_erp_id TO valor_resultante_zetti_id;
    END IF;

    IF EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = 'global_prod'
            AND table_name = 'bancos_conciliacion_cheque'
            AND column_name = 'asiento_erp_id'
    ) THEN
        ALTER TABLE global_prod.bancos_conciliacion_cheque RENAME COLUMN asiento_erp_id TO asiento_zetti_id;
    END IF;

    IF EXISTS (
        SELECT 1 FROM pg_constraint c
        JOIN pg_class t ON t.oid = c.conrelid
        JOIN pg_namespace n ON n.oid = t.relnamespace
        WHERE n.nspname = 'global_prod'
            AND t.relname = 'bancos_borrador_asiento'
            AND c.conname = 'bancos_borrador_asiento_operacion_erp_fk'
    ) THEN
        ALTER TABLE global_prod.bancos_borrador_asiento
            RENAME CONSTRAINT bancos_borrador_asiento_operacion_erp_fk TO bancos_borrador_asiento_operacion_zetti_fk;
    END IF;

    IF EXISTS (
        SELECT 1 FROM pg_constraint c
        JOIN pg_class t ON t.oid = c.conrelid
        JOIN pg_namespace n ON n.oid = t.relnamespace
        WHERE n.nspname = 'global_prod'
            AND t.relname = 'bancos_borrador_asiento'
            AND c.conname = 'bancos_borrador_asiento_asiento_erp_fk'
    ) THEN
        ALTER TABLE global_prod.bancos_borrador_asiento
            RENAME CONSTRAINT bancos_borrador_asiento_asiento_erp_fk TO bancos_borrador_asiento_asiento_zetti_fk;
    END IF;
END
$$;

COMMIT;
