BEGIN;

-- BANCOS_MENSUAL no registra sentido por regla. 'A' conserva esa semántica:
-- la clasificación es aplicable tanto a créditos como a débitos.
ALTER TABLE global_prod.bancos_regla_clasificacion
    DROP CONSTRAINT IF EXISTS bancos_regla_clasificacion_sentido_check;

ALTER TABLE global_prod.bancos_regla_clasificacion
    ADD CONSTRAINT bancos_regla_clasificacion_sentido_check
    CHECK (sentido IN ('C', 'D', 'A'));

COMMENT ON COLUMN global_prod.bancos_regla_clasificacion.sentido IS
    'C=crédito, D=débito, A=ambos sentidos; A preserva reglas legacy de BANCOS_MENSUAL sin discriminación de sentido.';

COMMIT;
