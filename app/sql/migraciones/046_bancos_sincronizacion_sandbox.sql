BEGIN;

CREATE TABLE IF NOT EXISTS global_temp.bancos_sandbox_sincronizacion (
    id bigserial PRIMARY KEY,
    clave varchar(80) NOT NULL UNIQUE,
    estado varchar(30) NOT NULL
        CHECK (estado IN ('EN_CURSO', 'OK', 'ERROR')),
    inicio_en timestamptz NOT NULL DEFAULT now(),
    fin_en timestamptz,
    resumen jsonb NOT NULL DEFAULT '{}'::jsonb,
    error text
);

CREATE INDEX IF NOT EXISTS bancos_sandbox_sincronizacion_estado_idx
    ON global_temp.bancos_sandbox_sincronizacion(estado, inicio_en DESC);

COMMENT ON TABLE global_temp.bancos_sandbox_sincronizacion IS
    'Lotes idempotentes que copian datos canónicos faltantes desde global_prod/public hacia el sandbox.';

COMMIT;
