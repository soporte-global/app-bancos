BEGIN;

DO $$
BEGIN
    IF EXISTS (
        SELECT 1
        FROM global_prod.bancos_regla_clasificacion
        WHERE sentido = 'A'
    ) THEN
        RAISE EXCEPTION 'No se puede revertir 008 mientras existan reglas con sentido A.';
    END IF;
END;
$$;

ALTER TABLE global_prod.bancos_regla_clasificacion
    DROP CONSTRAINT IF EXISTS bancos_regla_clasificacion_sentido_check;

ALTER TABLE global_prod.bancos_regla_clasificacion
    ADD CONSTRAINT bancos_regla_clasificacion_sentido_check
    CHECK (sentido IN ('C', 'D'));

COMMENT ON COLUMN global_prod.bancos_regla_clasificacion.sentido IS
    'C=crédito, D=débito.';

COMMIT;
