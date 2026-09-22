BEGIN;

CREATE UNIQUE INDEX IF NOT EXISTS bancos_debug_operacion_id_uk ON global_temp.operacion(id);
CREATE UNIQUE INDEX IF NOT EXISTS bancos_debug_valor_id_uk ON global_temp.valor(id);
CREATE SEQUENCE IF NOT EXISTS global_temp.bancos_conciliacion_cheque_sandbox_id_seq;

DO $$
BEGIN
    IF to_regclass('global_temp.bancos_conciliacion_cheque_fk_public_legacy') IS NULL THEN
        IF EXISTS (SELECT 1 FROM global_temp.bancos_conciliacion_cheque) THEN
            RAISE EXCEPTION '042 requiere una tabla de conciliacion sandbox vacia';
        END IF;

        ALTER TABLE global_temp.bancos_conciliacion_cheque
            RENAME TO bancos_conciliacion_cheque_fk_public_legacy;

        CREATE TABLE global_temp.bancos_conciliacion_cheque (
            id bigint PRIMARY KEY DEFAULT nextval('global_temp.bancos_conciliacion_cheque_sandbox_id_seq'),
            movimiento_id bigint,
            estado_id smallint NOT NULL,
            operador_hub_id bigint NOT NULL,
            valor_origen_zetti_id bigint NOT NULL,
            operacion_zetti_id bigint NOT NULL,
            valor_resultante_zetti_id bigint NOT NULL,
            asiento_zetti_id bigint,
            clave_idempotencia varchar(120) NOT NULL,
            motivo text,
            forzada boolean NOT NULL DEFAULT false,
            evidencia_origen varchar(255),
            conciliado_en timestamptz NOT NULL DEFAULT now(),
            CONSTRAINT bancos_conciliacion_cheque_sandbox_movimiento_fk FOREIGN KEY (movimiento_id)
                REFERENCES global_temp.bancos_movimiento_extracto(id),
            CONSTRAINT bancos_conciliacion_cheque_sandbox_estado_fk FOREIGN KEY (estado_id)
                REFERENCES global_temp.bancos_estado(id),
            CONSTRAINT bancos_conciliacion_cheque_sandbox_operador_fk FOREIGN KEY (operador_hub_id)
                REFERENCES global_prod.rrhh_login(id),
            CONSTRAINT bancos_conciliacion_cheque_sandbox_valor_origen_fk FOREIGN KEY (valor_origen_zetti_id)
                REFERENCES global_temp.valor(id),
            CONSTRAINT bancos_conciliacion_cheque_sandbox_operacion_fk FOREIGN KEY (operacion_zetti_id)
                REFERENCES global_temp.operacion(id),
            CONSTRAINT bancos_conciliacion_cheque_sandbox_valor_resultante_fk FOREIGN KEY (valor_resultante_zetti_id)
                REFERENCES global_temp.valor(id),
            CONSTRAINT bancos_conciliacion_cheque_sandbox_asiento_fk FOREIGN KEY (asiento_zetti_id)
                REFERENCES global_temp.asiento(id),
            CONSTRAINT bancos_conciliacion_cheque_sandbox_contexto_chk
                CHECK (movimiento_id IS NOT NULL OR clave_idempotencia IS NOT NULL),
            CONSTRAINT bancos_conciliacion_cheque_sandbox_clave_uk UNIQUE (clave_idempotencia)
        );

        CREATE INDEX bancos_conciliacion_cheque_sandbox_estado_idx
            ON global_temp.bancos_conciliacion_cheque(estado_id);
        CREATE UNIQUE INDEX bancos_conciliacion_cheque_sandbox_movimiento_uk
            ON global_temp.bancos_conciliacion_cheque(movimiento_id);
        CREATE UNIQUE INDEX bancos_conciliacion_cheque_sandbox_valor_origen_uk
            ON global_temp.bancos_conciliacion_cheque(valor_origen_zetti_id);
    END IF;
END
$$;

COMMIT;
