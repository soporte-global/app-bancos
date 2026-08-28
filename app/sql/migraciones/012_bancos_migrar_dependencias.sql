BEGIN;

-- Un evento de sistema puede no tener un emisor humano identificable.
ALTER TABLE global_prod.bancos_mensaje_movimiento
    ALTER COLUMN emisor_hub_id DROP NOT NULL;

CREATE TABLE global_prod.bancos_migracion_borrador_legacy (
    id_interno_asiento bigint PRIMARY KEY,
    borrador_asiento_id bigint REFERENCES global_prod.bancos_borrador_asiento(id)
);

CREATE TABLE global_prod.bancos_migracion_recurso_omitido (
    tipo_recurso varchar(20) NOT NULL CHECK (tipo_recurso IN ('VALOR', 'ASIENTO')),
    recurso_zetti_id bigint NOT NULL,
    id_movimiento_legacy varchar(100) NOT NULL,
    motivo text NOT NULL,
    PRIMARY KEY (tipo_recurso, recurso_zetti_id, id_movimiento_legacy)
);

CREATE TABLE global_prod.bancos_migracion_emisor_legacy (
    id_usuario_legacy varchar(100) PRIMARY KEY,
    login_id bigint REFERENCES global_prod.rrhh_login(id),
    metodo varchar(50) NOT NULL,
    observacion text
);

-- Los IDs de bancos_mensajeria corresponden a public.login_users.idu. Se
-- resuelven por alias, no por rrhh_login.origen_id, que pertenece a otro origen.
INSERT INTO global_prod.bancos_migracion_emisor_legacy
    (id_usuario_legacy, login_id, metodo, observacion)
VALUES
    ('1003', 34, 'ALIAS_LOGIN_USERS', 'NCAROL'),
    ('1007', 307, 'ALIAS_LOGIN_USERS', 'CMARCHANT'),
    ('1001', NULL, 'SIN_EMISOR', 'Usuario legacy eliminado; se conserva como mensaje de sistema.');

-- Rectificación/duplicación operativa confirmada: el cheque 57137580 ya se
-- conserva en el débito del 16/03/2020; la segunda imputación (01/06/2020)
-- queda trazada, pero no crea una asociación activa adicional.
INSERT INTO global_prod.bancos_migracion_recurso_omitido
    (tipo_recurso, recurso_zetti_id, id_movimiento_legacy, motivo)
VALUES
    ('VALOR', 103500000012894309, 'M000685P1911680119736062020',
     'Duplicación o rectificación operativa de cheque 57137580; se conserva la imputación previa del 16/03/2020.');

-- Cuando un asiento se reutilizó sin mult_asiento, el importe de sus líneas ERP
-- es el criterio rector: se conserva sólo el movimiento bancario coincidente.
-- Si no existe coincidencia, se omiten todas sus asociaciones, siempre dejando
-- la decisión en trazabilidad para revisión posterior.
INSERT INTO global_prod.bancos_migracion_recurso_omitido
    (tipo_recurso, recurso_zetti_id, id_movimiento_legacy, motivo)
WITH semantica AS (
    SELECT id_asiento, bool_or(COALESCE(mult_asiento, false)) AS compartido
    FROM public.bancos_mes_asiento_asignado_movimiento
    WHERE id_asiento <> 0
    GROUP BY id_asiento
), importe_erp AS (
    SELECT asiento AS id_asiento, max(abs(monto)) AS importe
    FROM public.movimiento
    GROUP BY asiento
)
SELECT DISTINCT 'ASIENTO', a.id_asiento, a.id_movimiento,
       CASE WHEN importe_erp.importe IS NULL
            THEN 'Asiento sin líneas ERP: no se puede validar la asociación bancaria.'
            ELSE 'Importe bancario distinto del importe del asiento ERP; se conserva sólo la coincidencia exacta.'
       END
FROM public.bancos_mes_asiento_asignado_movimiento a
JOIN semantica ON semantica.id_asiento = a.id_asiento AND NOT semantica.compartido
LEFT JOIN importe_erp ON importe_erp.id_asiento = a.id_asiento
JOIN public.bancos_mes_movimientos_cargados_periodo m ON m.id_movimiento = a.id_movimiento
WHERE importe_erp.importe IS NULL
   OR abs(COALESCE(m.debito, 0) + COALESCE(m.credito, 0)) <> importe_erp.importe;

-- Preflight: valores no admiten fusión salvo resolución explícita. El asiento
-- 0 es un centinela legacy, no un asiento ERP, y no se migra.
DO $$
BEGIN
    IF EXISTS (
        WITH asignaciones AS (
            SELECT v.id_valor, t.movimiento_id, omitido.id_movimiento_legacy
            FROM public.bancos_mes_valor_asignado_movimiento v
            JOIN global_prod.bancos_migracion_movimiento_legacy t
              ON t.id_movimiento_legacy = v.id_movimiento AND t.es_canonico
            LEFT JOIN global_prod.bancos_migracion_recurso_omitido omitido
              ON omitido.tipo_recurso = 'VALOR'
             AND omitido.recurso_zetti_id = v.id_valor
             AND omitido.id_movimiento_legacy = v.id_movimiento
        )
        SELECT 1
        FROM asignaciones
        GROUP BY id_valor
        HAVING count(DISTINCT movimiento_id) > 1
           AND count(DISTINCT movimiento_id) <> count(DISTINCT id_movimiento_legacy) + 1
    ) THEN
        RAISE EXCEPTION 'Hay valores ERP asociados a más de un movimiento; resolverlos antes de la migración';
    END IF;

END;
$$;

-- Asociaciones históricas a valor y asiento existente.
INSERT INTO global_prod.bancos_asociacion_movimiento
    (movimiento_id, valor_zetti_id, monto_asociado, observacion)
SELECT t.movimiento_id, v.id_valor, NULLIF(abs(v.monto_asignado)::numeric(20,5), 0), 'MIGRACION-012|VALOR'
FROM public.bancos_mes_valor_asignado_movimiento v
JOIN global_prod.bancos_migracion_movimiento_legacy t
  ON t.id_movimiento_legacy = v.id_movimiento AND t.es_canonico
JOIN public.valor valor ON valor.id = v.id_valor
LEFT JOIN global_prod.bancos_migracion_recurso_omitido omitido
  ON omitido.tipo_recurso = 'VALOR'
 AND omitido.recurso_zetti_id = v.id_valor
 AND omitido.id_movimiento_legacy = v.id_movimiento
WHERE omitido.id_movimiento_legacy IS NULL
  AND t.movimiento_id IS NOT NULL;

INSERT INTO global_prod.bancos_asociacion_movimiento
    (movimiento_id, asiento_zetti_id, monto_asociado, compartido, observacion)
SELECT t.movimiento_id, a.id_asiento, NULLIF(abs(a.monto_asignado)::numeric(20,5), 0), semantica.compartido, 'MIGRACION-012|ASIENTO'
FROM public.bancos_mes_asiento_asignado_movimiento a
JOIN global_prod.bancos_migracion_movimiento_legacy t
  ON t.id_movimiento_legacy = a.id_movimiento AND t.es_canonico
JOIN public.asiento asiento ON asiento.id = a.id_asiento
JOIN (
    SELECT id_asiento, bool_or(COALESCE(mult_asiento, false)) AS compartido
    FROM public.bancos_mes_asiento_asignado_movimiento
    WHERE id_asiento <> 0
    GROUP BY id_asiento
) semantica ON semantica.id_asiento = a.id_asiento
LEFT JOIN global_prod.bancos_migracion_recurso_omitido omitido
  ON omitido.tipo_recurso = 'ASIENTO'
 AND omitido.recurso_zetti_id = a.id_asiento
 AND omitido.id_movimiento_legacy = a.id_movimiento
WHERE omitido.id_movimiento_legacy IS NULL
  AND t.movimiento_id IS NOT NULL;

-- Las exclusiones con valor y asiento se separan en dos reservas tipadas.
INSERT INTO global_prod.bancos_reserva_recurso
    (movimiento_id, valor_zetti_id, fecha_creacion, observacion)
SELECT t.movimiento_id, e.id_valor, COALESCE(e.timestamp AT TIME ZONE 'America/Argentina/Buenos_Aires', now()), 'MIGRACION-012|EXCLUSION'
FROM public.bancos_mes_exclusiones e
JOIN global_prod.bancos_migracion_movimiento_legacy t
  ON t.id_movimiento_legacy = e.id_movimiento AND t.es_canonico
JOIN public.valor v ON v.id = e.id_valor
WHERE e.id_valor IS NOT NULL
  AND t.movimiento_id IS NOT NULL;

INSERT INTO global_prod.bancos_reserva_recurso
    (movimiento_id, asiento_zetti_id, compartido, fecha_creacion, observacion)
SELECT t.movimiento_id, e.id_asiento, COALESCE(semantica.compartido, false), COALESCE(e.timestamp AT TIME ZONE 'America/Argentina/Buenos_Aires', now()), 'MIGRACION-012|EXCLUSION'
FROM public.bancos_mes_exclusiones e
JOIN global_prod.bancos_migracion_movimiento_legacy t
  ON t.id_movimiento_legacy = e.id_movimiento AND t.es_canonico
JOIN public.asiento a ON a.id = e.id_asiento
LEFT JOIN (
    SELECT id_asiento, bool_or(COALESCE(mult_asiento, false)) AS compartido
    FROM public.bancos_mes_asiento_asignado_movimiento
    WHERE id_asiento <> 0
    GROUP BY id_asiento
) semantica ON semantica.id_asiento = e.id_asiento
WHERE e.id_asiento IS NOT NULL
  AND e.id_asiento <> 0
  AND t.movimiento_id IS NOT NULL;

DO $$
BEGIN
    IF EXISTS (
        WITH lineas AS (
            SELECT l.id_interno_asiento,
                   COALESCE(
                       NULLIF(btrim(l.codigo_cuenta), ''),
                       (
                           SELECT min(l2.codigo_cuenta)
                           FROM public.bancos_mes_movimientos_creados_asiento l2
                           WHERE l2.id_interno_asiento = l.id_interno_asiento
                             AND NULLIF(btrim(l2.codigo_cuenta), '') IS NOT NULL
                             AND lower(btrim(l2.nombre)) = lower(btrim(l.nombre))
                             AND l2.debe = l.haber
                             AND l2.haber = l.debe
                           HAVING count(DISTINCT l2.codigo_cuenta) = 1
                       )
                   ) AS codigo_cuenta_resuelto
            FROM public.bancos_mes_movimientos_creados_asiento l
        )
        SELECT 1
        FROM lineas l
        WHERE (SELECT count(*)
               FROM public.cuenta c
               WHERE c.codigo = l.codigo_cuenta_resuelto) <> 1
    ) THEN
        RAISE EXCEPTION 'Hay líneas de borrador sin cuenta ERP unívoca por código, incluso aplicando la contrapartida gemela';
    END IF;
END;
$$;

INSERT INTO global_prod.bancos_borrador_asiento
    (movimiento_id, nodo_zetti_id, fecha_contable, estado_id, clave_idempotencia, modelo, observacion)
SELECT t.movimiento_id, h.nodo_creacion::integer, h.fecha_creacion, 1,
       'MIGRACION-012|BORRADOR|' || h.id_interno_asiento, h.asiento_modelo::text,
       h.nombre
FROM public.bancos_mes_asientos_creados_movimiento h
JOIN global_prod.bancos_migracion_movimiento_legacy t
  ON t.id_movimiento_legacy = h.id_movimiento AND t.es_canonico
JOIN public.nodo n ON n.id = h.nodo_creacion::integer
WHERE t.movimiento_id IS NOT NULL;

INSERT INTO global_prod.bancos_migracion_borrador_legacy (id_interno_asiento, borrador_asiento_id)
SELECT h.id_interno_asiento, b.id
FROM public.bancos_mes_asientos_creados_movimiento h
JOIN global_prod.bancos_borrador_asiento b
  ON b.clave_idempotencia = 'MIGRACION-012|BORRADOR|' || h.id_interno_asiento;

INSERT INTO global_prod.bancos_linea_borrador_asiento
    (borrador_asiento_id, cuenta_zetti_id, debe, haber, observacion)
SELECT mb.borrador_asiento_id, c.id, l.debe::numeric(20,5), l.haber::numeric(20,5), l.nombre
FROM public.bancos_mes_movimientos_creados_asiento l
JOIN public.bancos_mes_asientos_creados_movimiento h ON h.id_interno_asiento = l.id_interno_asiento
JOIN global_prod.bancos_migracion_borrador_legacy mb ON mb.id_interno_asiento = l.id_interno_asiento
JOIN LATERAL (
    SELECT COALESCE(
        NULLIF(btrim(l.codigo_cuenta), ''),
        (
            SELECT min(l2.codigo_cuenta)
            FROM public.bancos_mes_movimientos_creados_asiento l2
            WHERE l2.id_interno_asiento = l.id_interno_asiento
              AND NULLIF(btrim(l2.codigo_cuenta), '') IS NOT NULL
              AND lower(btrim(l2.nombre)) = lower(btrim(l.nombre))
              AND l2.debe = l.haber
              AND l2.haber = l.debe
            HAVING count(DISTINCT l2.codigo_cuenta) = 1
        )
    ) AS codigo_cuenta_resuelto
) codigo ON true
JOIN public.cuenta c ON c.codigo = codigo.codigo_cuenta_resuelto;

-- Mensajes y recepción: el emisor conserva su identidad; los flags legacy se
-- traducen en una recepción por el mismo usuario cuando existe fecha de lectura.
DO $$
BEGIN
    IF EXISTS (
        SELECT 1
        FROM public.bancos_mensajeria m
        LEFT JOIN global_prod.bancos_migracion_emisor_legacy emisor
          ON emisor.id_usuario_legacy = m.id_usuario_emisor
        WHERE emisor.login_id IS NULL
    ) THEN
        RAISE EXCEPTION 'Hay mensajes legacy sin emisor mapeado en global_prod.rrhh_login';
    END IF;
END;
$$;

INSERT INTO global_prod.bancos_mensaje_movimiento
    (movimiento_id, emisor_hub_id, tipo_mensaje, cuerpo, emitido_en)
SELECT t.movimiento_id, emisor.login_id,
       CASE WHEN emisor.login_id IS NULL OR m.mensaje_de_sistema <> 0 THEN 'SISTEMA' ELSE 'USUARIO' END,
       m.mensaje, COALESCE(m.emision_time AT TIME ZONE 'America/Argentina/Buenos_Aires', now())
FROM public.bancos_mensajeria m
JOIN global_prod.bancos_migracion_movimiento_legacy t
  ON t.id_movimiento_legacy = m.id_movimiento AND t.es_canonico
JOIN global_prod.bancos_migracion_emisor_legacy emisor
  ON emisor.id_usuario_legacy = m.id_usuario_emisor
WHERE t.movimiento_id IS NOT NULL;

COMMIT;
