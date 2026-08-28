BEGIN;

-- Cuenta contable base de la configuración bancaria. Conserva el dato legado
-- de bancos_guardado_cuentasbanco y bancos_mes_guardado_cuentasbanco que no
-- depende de un subtipo de valor.
ALTER TABLE global_prod.bancos_configuracion
    ADD COLUMN IF NOT EXISTS cuenta_contable_zetti_id bigint;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conname = 'bancos_configuracion_cuenta_contable_fk'
          AND conrelid = 'global_prod.bancos_configuracion'::regclass
    ) THEN
        ALTER TABLE global_prod.bancos_configuracion
            ADD CONSTRAINT bancos_configuracion_cuenta_contable_fk
            FOREIGN KEY (cuenta_contable_zetti_id)
            REFERENCES public.cuenta(id);
    END IF;
END;
$$;

CREATE INDEX IF NOT EXISTS bancos_configuracion_cuenta_contable_idx
    ON global_prod.bancos_configuracion(cuenta_contable_zetti_id)
    WHERE cuenta_contable_zetti_id IS NOT NULL;

COMMENT ON COLUMN global_prod.bancos_configuracion.cuenta_contable_zetti_id IS
    'Cuenta contable ERP base asociada al banco/configuración; no reemplaza los mapeos por subtipo.';

COMMIT;
