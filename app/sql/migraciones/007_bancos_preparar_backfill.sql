BEGIN;

-- Esta migracion no copia datos de negocio a las tablas canonicas.
-- Prepara la trazabilidad y los mapas que deben revisarse antes del backfill.
-- No ejecutar junto con el backfill: primero completar las filas pendientes
-- de global_prod.bancos_migracion_cuenta_legacy.

DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM global_prod.bancos_movimiento_extracto) THEN
        RAISE EXCEPTION '007 requiere bancos_movimiento_extracto vacia';
    END IF;
END;
$$;

-- id_periodo del legado no es un entero: por ejemplo, contiene cuenta y mes.
ALTER TABLE global_prod.bancos_movimiento_extracto
    ALTER COLUMN id_periodo TYPE varchar(100) USING id_periodo::varchar;

CREATE TABLE global_prod.bancos_migracion_cuenta_legacy (
    cuenta_legacy varchar(100) PRIMARY KEY,
    cuenta_bancaria_zetti_id bigint REFERENCES public.cuenta_bancaria(id),
    omitir boolean NOT NULL DEFAULT false,
    metodo varchar(20) NOT NULL DEFAULT 'PENDIENTE'
        CHECK (metodo IN ('AUTOMATICO', 'MANUAL', 'PENDIENTE')),
    observacion text,
    actualizado_en timestamptz NOT NULL DEFAULT now(),
    CHECK (NOT omitir OR cuenta_bancaria_zetti_id IS NULL)
);

CREATE TABLE global_prod.bancos_migracion_periodo_legacy (
    id_periodo_legacy varchar(100) PRIMARY KEY,
    cuenta_legacy varchar(100) NOT NULL REFERENCES global_prod.bancos_migracion_cuenta_legacy(cuenta_legacy),
    num_mes integer,
    ano integer,
    total_movimientos_legacy integer,
    importacion_id bigint REFERENCES global_prod.bancos_importacion_extracto(id)
);

CREATE TABLE global_prod.bancos_migracion_movimiento_legacy (
    id_periodo_legacy varchar(100) NOT NULL,
    serial_seq_legacy bigint NOT NULL,
    id_movimiento_legacy varchar(100) NOT NULL,
    serial_seq_canonico bigint NOT NULL,
    es_canonico boolean NOT NULL,
    credito_legacy numeric(20,5) NOT NULL,
    debito_legacy numeric(20,5) NOT NULL,
    credito_normalizado numeric(20,5) NOT NULL,
    debito_normalizado numeric(20,5) NOT NULL,
    movimiento_id bigint REFERENCES global_prod.bancos_movimiento_extracto(id),
    PRIMARY KEY (id_periodo_legacy, serial_seq_legacy),
    FOREIGN KEY (id_periodo_legacy)
        REFERENCES global_prod.bancos_migracion_periodo_legacy(id_periodo_legacy),
    CHECK ((credito_normalizado = 0 AND debito_normalizado > 0)
        OR (debito_normalizado = 0 AND credito_normalizado > 0))
);

CREATE INDEX bancos_migracion_movimiento_legacy_canonico_idx
    ON global_prod.bancos_migracion_movimiento_legacy
       (id_periodo_legacy, serial_seq_canonico)
    WHERE es_canonico;

INSERT INTO global_prod.bancos_migracion_cuenta_legacy (cuenta_legacy)
SELECT DISTINCT cuenta
FROM (
    SELECT num_cuenta AS cuenta FROM public.bancos_mes_periodos_cargados_cuenta
    UNION
    SELECT cuenta FROM public.bancos_mes_guardado_datos
    UNION
    SELECT cuenta FROM public.bancos_guardado_datos
) origen
WHERE cuenta IS NOT NULL AND btrim(cuenta) <> '' AND cuenta <> 'N/A';

-- Se resuelven solamente coincidencias exactas y univocas contra entidad.codigo.
-- Las diferencias de formato quedan pendientes para una decision explicita.
WITH candidatos AS (
    SELECT m.cuenta_legacy, min(cb.id) AS cuenta_bancaria_zetti_id
    FROM global_prod.bancos_migracion_cuenta_legacy m
    JOIN public.entidad e ON e.codigo = m.cuenta_legacy
    JOIN public.cuenta_bancaria cb ON cb.id = e.id
    GROUP BY m.cuenta_legacy
    HAVING count(*) = 1
)
UPDATE global_prod.bancos_migracion_cuenta_legacy m
SET cuenta_bancaria_zetti_id = c.cuenta_bancaria_zetti_id,
    metodo = 'AUTOMATICO',
    actualizado_en = now()
FROM candidatos c
WHERE c.cuenta_legacy = m.cuenta_legacy;

INSERT INTO global_prod.bancos_migracion_periodo_legacy
    (id_periodo_legacy, cuenta_legacy, num_mes, ano, total_movimientos_legacy)
SELECT id_periodo, num_cuenta, num_mes, ano, total_movs
FROM public.bancos_mes_periodos_cargados_cuenta;

INSERT INTO global_prod.bancos_migracion_movimiento_legacy (
    id_periodo_legacy, serial_seq_legacy, id_movimiento_legacy,
    serial_seq_canonico, es_canonico,
    credito_legacy, debito_legacy, credito_normalizado, debito_normalizado
)
SELECT
    m.id_periodo,
    m.serial_seq,
    m.id_movimiento,
    min(m.serial_seq) OVER (PARTITION BY m.id_movimiento),
    m.serial_seq = min(m.serial_seq) OVER (PARTITION BY m.id_movimiento),
    m.credito::numeric(20,5),
    m.debito::numeric(20,5),
    CASE WHEN m.debito < 0 THEN abs(m.debito)::numeric(20,5)
         WHEN m.credito < 0 THEN 0 ELSE m.credito::numeric(20,5) END,
    CASE WHEN m.credito < 0 THEN abs(m.credito)::numeric(20,5)
         WHEN m.debito < 0 THEN 0 ELSE m.debito::numeric(20,5) END
FROM public.bancos_mes_movimientos_cargados_periodo m;

COMMIT;
