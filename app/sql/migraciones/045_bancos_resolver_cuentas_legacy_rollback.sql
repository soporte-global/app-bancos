BEGIN;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.columns
        WHERE table_schema = 'global_prod'
          AND table_name = 'bancos_migracion_cuenta_legacy'
          AND column_name = 'tratamiento'
    ) THEN
        RAISE EXCEPTION 'No se puede retirar 045: la columna tratamiento no existe';
    END IF;
END;
$$;

DELETE FROM global_prod.bancos_configuracion_cuenta cc
USING global_prod.bancos_migracion_045_vinculo_cuenta v
WHERE cc.configuracion_id = v.configuracion_id
  AND cc.cuenta_bancaria_zetti_id = v.cuenta_bancaria_zetti_id;

DROP TABLE global_prod.bancos_migracion_045_vinculo_cuenta;

UPDATE global_prod.bancos_migracion_cuenta_legacy
SET cuenta_bancaria_zetti_id = NULL,
    omitir = false,
    metodo = 'PENDIENTE',
    observacion = NULL,
    actualizado_en = now()
WHERE cuenta_legacy IN (
    'GENERAL',
    'MERCADO PAGO',
    '191168194623',
    '0191-168-011973/6'
);

ALTER TABLE global_prod.bancos_migracion_cuenta_legacy
    DROP CONSTRAINT bancos_migracion_cuenta_legacy_tratamiento_chk;
ALTER TABLE global_prod.bancos_migracion_cuenta_legacy
    DROP COLUMN tratamiento;

COMMIT;
