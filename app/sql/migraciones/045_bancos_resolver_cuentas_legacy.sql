BEGIN;

ALTER TABLE global_prod.bancos_migracion_cuenta_legacy
    ADD COLUMN tratamiento varchar(30) NOT NULL DEFAULT 'CUENTA_UNICA';

ALTER TABLE global_prod.bancos_migracion_cuenta_legacy
    ADD CONSTRAINT bancos_migracion_cuenta_legacy_tratamiento_chk
    CHECK (tratamiento IN (
        'CUENTA_UNICA',
        'OMITIR',
        'ALCANCE_GLOBAL',
        'MULTICUENTA',
        'PERIODOS_OMITIDOS'
    ));

UPDATE global_prod.bancos_migracion_cuenta_legacy
SET tratamiento = 'OMITIR'
WHERE omitir;

UPDATE global_prod.bancos_migracion_cuenta_legacy
SET metodo = 'MANUAL',
    tratamiento = 'ALCANCE_GLOBAL',
    observacion = 'Configuración de alcance global; no corresponde a una cuenta bancaria ERP.',
    actualizado_en = now()
WHERE cuenta_legacy = 'GENERAL';

UPDATE global_prod.bancos_migracion_cuenta_legacy
SET metodo = 'MANUAL',
    tratamiento = 'MULTICUENTA',
    observacion = 'Configuración vinculada explícitamente a las dos cuentas ERP de Mercado Pago.',
    actualizado_en = now()
WHERE cuenta_legacy = 'MERCADO PAGO';

UPDATE global_prod.bancos_migracion_cuenta_legacy
SET metodo = 'MANUAL',
    tratamiento = 'PERIODOS_OMITIDOS',
    observacion = 'Cuenta inexistente en ERP; todos sus períodos están omitidos con motivo auditable.',
    actualizado_en = now()
WHERE cuenta_legacy = '191168194623';

UPDATE global_prod.bancos_migracion_cuenta_legacy
SET cuenta_bancaria_zetti_id = 103500000000524090,
    metodo = 'MANUAL',
    tratamiento = 'CUENTA_UNICA',
    observacion = 'Coincidencia literal con BCO CREDICOOP DOLAR.',
    actualizado_en = now()
WHERE cuenta_legacy = '0191-168-011973/6';

CREATE TABLE global_prod.bancos_migracion_045_vinculo_cuenta (
    configuracion_id bigint NOT NULL
        REFERENCES global_prod.bancos_configuracion(id),
    cuenta_bancaria_zetti_id bigint NOT NULL
        REFERENCES public.cuenta_bancaria(id),
    PRIMARY KEY (configuracion_id, cuenta_bancaria_zetti_id)
);

INSERT INTO global_prod.bancos_migracion_045_vinculo_cuenta
    (configuracion_id, cuenta_bancaria_zetti_id)
SELECT DISTINCT mc.configuracion_id, 103500000000524090
FROM global_prod.bancos_migracion_configuracion_legacy mc
WHERE mc.cuenta_legacy = '0191-168-011973/6'
  AND mc.configuracion_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1
      FROM global_prod.bancos_configuracion_cuenta cc
      WHERE cc.configuracion_id = mc.configuracion_id
        AND cc.cuenta_bancaria_zetti_id = 103500000000524090
  );

INSERT INTO global_prod.bancos_configuracion_cuenta
    (configuracion_id, cuenta_bancaria_zetti_id)
SELECT configuracion_id, cuenta_bancaria_zetti_id
FROM global_prod.bancos_migracion_045_vinculo_cuenta;

DO $$
BEGIN
    IF (SELECT count(*) FROM global_prod.bancos_migracion_cuenta_legacy
        WHERE cuenta_legacy IN ('GENERAL', 'MERCADO PAGO', '191168194623', '0191-168-011973/6')
          AND metodo = 'MANUAL') <> 4 THEN
        RAISE EXCEPTION 'No se registraron los cuatro tratamientos legacy esperados';
    END IF;

    IF EXISTS (
        SELECT 1
        FROM global_prod.bancos_migracion_configuracion_legacy mc
        WHERE mc.cuenta_legacy = '0191-168-011973/6'
          AND mc.configuracion_id IS NOT NULL
          AND NOT EXISTS (
              SELECT 1
              FROM global_prod.bancos_configuracion_cuenta cc
              WHERE cc.configuracion_id = mc.configuracion_id
                AND cc.cuenta_bancaria_zetti_id = 103500000000524090
          )
    ) THEN
        RAISE EXCEPTION 'Quedaron configuraciones de Credicoop dólar sin vincular';
    END IF;

    IF EXISTS (
        SELECT 1
        FROM global_prod.bancos_migracion_periodo_legacy p
        WHERE p.cuenta_legacy = '191168194623'
          AND (NOT p.omitir OR NULLIF(btrim(p.motivo_omision), '') IS NULL)
    ) THEN
        RAISE EXCEPTION 'La cuenta 191168194623 conserva períodos sin omisión documentada';
    END IF;
END;
$$;

COMMENT ON COLUMN global_prod.bancos_migracion_cuenta_legacy.tratamiento IS
    'Resolución funcional de la cuenta legacy: cuenta única, omisión o tratamiento especial sin mapeo 1:1.';
COMMENT ON TABLE global_prod.bancos_migracion_045_vinculo_cuenta IS
    'Vínculos de configuración creados por 045 para permitir un rollback exacto.';

COMMIT;
