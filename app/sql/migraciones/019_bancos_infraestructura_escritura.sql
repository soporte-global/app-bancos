BEGIN;

-- Toda mutacion funcional debe poder reconocer reintentos y dejar evidencia.
-- Se crean las mismas estructuras en produccion y en el sandbox; el codigo
-- elige una sola mediante EsquemaBancos.
DO $$
DECLARE
    esquema text;
BEGIN
    FOREACH esquema IN ARRAY ARRAY['global_prod', 'global_temp']
    LOOP
        EXECUTE format(
            'CREATE TABLE IF NOT EXISTS %I.bancos_solicitud_idempotente (
                id bigserial PRIMARY KEY,
                operacion varchar(80) NOT NULL,
                clave varchar(120) NOT NULL,
                usuario_hub_id bigint NOT NULL REFERENCES global_prod.rrhh_login(id),
                huella_solicitud char(64) NOT NULL,
                estado varchar(20) NOT NULL DEFAULT ''EN_PROCESO'',
                codigo_http smallint,
                respuesta_json jsonb,
                creado_en timestamp with time zone NOT NULL DEFAULT current_timestamp,
                completado_en timestamp with time zone,
                CONSTRAINT bancos_solicitud_idempotente_operacion_clave_uk UNIQUE (operacion, clave),
                CONSTRAINT bancos_solicitud_idempotente_estado_ck CHECK (estado IN (''EN_PROCESO'', ''COMPLETADA'')),
                CONSTRAINT bancos_solicitud_idempotente_completada_ck CHECK (
                    (estado = ''EN_PROCESO'' AND codigo_http IS NULL AND respuesta_json IS NULL AND completado_en IS NULL)
                    OR
                    (estado = ''COMPLETADA'' AND codigo_http BETWEEN 200 AND 299 AND respuesta_json IS NOT NULL AND completado_en IS NOT NULL)
                )
            )',
            esquema
        );

        EXECUTE format(
            'CREATE TABLE IF NOT EXISTS %I.bancos_evento_auditoria (
                id bigserial PRIMARY KEY,
                solicitud_id bigint NOT NULL REFERENCES %I.bancos_solicitud_idempotente(id),
                usuario_hub_id bigint NOT NULL REFERENCES global_prod.rrhh_login(id),
                accion varchar(80) NOT NULL,
                recurso_tipo varchar(80) NOT NULL,
                recurso_id varchar(120),
                resultado varchar(30) NOT NULL,
                detalles jsonb NOT NULL DEFAULT ''{}''::jsonb,
                registrado_en timestamp with time zone NOT NULL DEFAULT current_timestamp
            )',
            esquema,
            esquema
        );

        EXECUTE format(
            'CREATE INDEX IF NOT EXISTS bancos_evento_auditoria_usuario_fecha_idx
             ON %I.bancos_evento_auditoria (usuario_hub_id, registrado_en DESC)',
            esquema
        );
        EXECUTE format(
            'CREATE INDEX IF NOT EXISTS bancos_evento_auditoria_recurso_idx
             ON %I.bancos_evento_auditoria (recurso_tipo, recurso_id, registrado_en DESC)',
            esquema
        );
    END LOOP;
END
$$;

COMMIT;
