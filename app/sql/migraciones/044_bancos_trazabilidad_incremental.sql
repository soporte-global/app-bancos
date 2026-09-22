BEGIN;

ALTER TABLE global_prod.bancos_migracion_cuenta_legacy
    ADD COLUMN IF NOT EXISTS lote_migracion varchar(40) NOT NULL DEFAULT 'ORIGINAL';
ALTER TABLE global_prod.bancos_migracion_configuracion_legacy
    ADD COLUMN IF NOT EXISTS lote_migracion varchar(40) NOT NULL DEFAULT 'ORIGINAL';
ALTER TABLE global_prod.bancos_migracion_periodo_legacy
    ADD COLUMN IF NOT EXISTS lote_migracion varchar(40) NOT NULL DEFAULT 'ORIGINAL';
ALTER TABLE global_prod.bancos_migracion_movimiento_legacy
    ADD COLUMN IF NOT EXISTS lote_migracion varchar(40) NOT NULL DEFAULT 'ORIGINAL';
ALTER TABLE global_prod.bancos_migracion_borrador_legacy
    ADD COLUMN IF NOT EXISTS lote_migracion varchar(40) NOT NULL DEFAULT 'ORIGINAL';
ALTER TABLE global_prod.bancos_migracion_recurso_omitido
    ADD COLUMN IF NOT EXISTS lote_migracion varchar(40) NOT NULL DEFAULT 'ORIGINAL';

CREATE TABLE IF NOT EXISTS global_prod.bancos_migracion_incremental_ejecucion (
    id bigserial PRIMARY KEY,
    clave varchar(80) NOT NULL UNIQUE,
    lote_migracion varchar(40) NOT NULL UNIQUE,
    estado varchar(30) NOT NULL
        CHECK (estado IN ('EN_CURSO', 'OK', 'ERROR', 'REQUIERE_DECISION')),
    inicio_en timestamptz NOT NULL DEFAULT now(),
    fin_en timestamptz,
    resumen jsonb NOT NULL DEFAULT '{}'::jsonb,
    error text
);

CREATE INDEX IF NOT EXISTS bancos_migracion_incremental_estado_idx
    ON global_prod.bancos_migracion_incremental_ejecucion(estado, inicio_en DESC);

COMMENT ON TABLE global_prod.bancos_migracion_incremental_ejecucion IS
    'Ejecuciones idempotentes del catch-up legacy. Una clave identifica un intento lógico y lote_migracion marca toda su trazabilidad.';

COMMIT;
