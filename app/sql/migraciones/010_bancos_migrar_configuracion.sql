BEGIN;

-- Requiere 007 (trazabilidad de cuentas), 008 (sentido A) y 009 (cuenta
-- contable base). No se ejecuta sobre una configuración ya cargada.
CREATE TABLE global_prod.bancos_migracion_configuracion_legacy (
    origen varchar(20) NOT NULL CHECK (origen IN ('BANCOS', 'MENSUAL')),
    id_legacy varchar(30) NOT NULL,
    cuenta_legacy varchar(100) NOT NULL,
    banco_zetti_id bigint NOT NULL,
    configuracion_id bigint REFERENCES global_prod.bancos_configuracion(id),
    PRIMARY KEY (origen, id_legacy, cuenta_legacy, banco_zetti_id)
);

DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_class WHERE oid = 'global_prod.bancos_migracion_cuenta_legacy'::regclass) THEN
        RAISE EXCEPTION '010 requiere ejecutar 007 antes';
    END IF;
    IF EXISTS (SELECT 1 FROM global_prod.bancos_migracion_configuracion_legacy) THEN
        RAISE EXCEPTION '010 ya tiene un mapa de configuración cargado';
    END IF;
END;
$$;

INSERT INTO global_prod.bancos_migracion_configuracion_legacy
    (origen, id_legacy, cuenta_legacy, banco_zetti_id)
SELECT DISTINCT 'BANCOS', d.id, d.cuenta, lb.banco_id::bigint
FROM public.bancos_guardado_datos d
JOIN public.bancos_guardado_listabancos lb
  ON lb.id = d.id AND lb.cuenta = d.cuenta
WHERE lb.banco_id ~ '^[0-9]+$'
UNION
SELECT DISTINCT 'MENSUAL', d.id, d.cuenta, lb.banco_id::bigint
FROM public.bancos_mes_guardado_datos d
JOIN public.bancos_mes_guardado_listabancos lb
  ON lb.id = d.id AND lb.cuenta = d.cuenta
WHERE lb.banco_id ~ '^[0-9]+$';

-- Una configuración por banco. GENERAL y NNNN son alcance global; las demás
-- se vinculan a una cuenta, salvo Mercado Pago que se trata luego como 1:N.
INSERT INTO global_prod.bancos_configuracion
    (alcance, banco_zetti_id, cuenta_contable_zetti_id, observacion)
SELECT
    CASE WHEN mc.cuenta_legacy IN ('GENERAL', 'NNNNN') THEN 'GLOBAL' ELSE 'CUENTA' END,
    mc.banco_zetti_id,
    cb.id_cuenta_contable::bigint,
    'MIGRACION-010|' || mc.origen || '|' || mc.id_legacy || '|' || mc.cuenta_legacy || '|' || mc.banco_zetti_id
FROM global_prod.bancos_migracion_configuracion_legacy mc
LEFT JOIN LATERAL (
    SELECT b.id_cuenta_contable
    FROM (
        SELECT 'BANCOS'::varchar AS origen, cuenta, banco_id, id_cuenta_contable FROM public.bancos_guardado_cuentasbanco
        UNION ALL
        SELECT 'MENSUAL', cuenta, banco_id, id_cuenta_contable FROM public.bancos_mes_guardado_cuentasbanco
    ) b
    JOIN public.cuenta cuenta_contable ON cuenta_contable.id = b.id_cuenta_contable::bigint
    WHERE b.origen = mc.origen
      AND b.cuenta = mc.cuenta_legacy
      AND b.banco_id = mc.banco_zetti_id::varchar
      AND b.id_cuenta_contable ~ '^[0-9]+$'
    LIMIT 1
) cb ON true;

UPDATE global_prod.bancos_migracion_configuracion_legacy mc
SET configuracion_id = c.id
FROM global_prod.bancos_configuracion c
WHERE c.observacion = 'MIGRACION-010|' || mc.origen || '|' || mc.id_legacy || '|' || mc.cuenta_legacy || '|' || mc.banco_zetti_id;

DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM global_prod.bancos_migracion_configuracion_legacy WHERE configuracion_id IS NULL) THEN
        RAISE EXCEPTION 'No se pudo identificar una configuración migrada';
    END IF;
END;
$$;

-- Cuentas específicas; Mercado Pago se asocia explícitamente a sus dos cuentas.
INSERT INTO global_prod.bancos_configuracion_cuenta (configuracion_id, cuenta_bancaria_zetti_id)
SELECT mc.configuracion_id, ml.cuenta_bancaria_zetti_id
FROM global_prod.bancos_migracion_configuracion_legacy mc
JOIN global_prod.bancos_migracion_cuenta_legacy ml ON ml.cuenta_legacy = mc.cuenta_legacy
WHERE ml.cuenta_bancaria_zetti_id IS NOT NULL AND NOT ml.omitir
ON CONFLICT (configuracion_id, cuenta_bancaria_zetti_id) DO NOTHING;

INSERT INTO global_prod.bancos_configuracion_cuenta (configuracion_id, cuenta_bancaria_zetti_id)
SELECT mc.configuracion_id, cb.id
FROM global_prod.bancos_migracion_configuracion_legacy mc
JOIN public.cuenta_bancaria cb ON cb.id IN (103500000000544336, 103500000000544338)
WHERE mc.cuenta_legacy = 'MERCADO PAGO'
ON CONFLICT (configuracion_id, cuenta_bancaria_zetti_id) DO NOTHING;

-- Reglas. El mensual conserva sentido A; BANCOS conserva crédito/débito.
INSERT INTO global_prod.bancos_regla_clasificacion
    (configuracion_id, subtipo_valor_zetti_id, sentido, codigo_extracto, validar_automaticamente, observacion)
SELECT DISTINCT mc.configuracion_id, r.subtipo::smallint, r.sentido, NULLIF(btrim(r.codigo), ''), r.validar,
    'MIGRACION-010|' || r.origen
FROM (
    SELECT 'BANCOS'::varchar AS origen, id, cuenta, reglas_idsubtipo_valor AS subtipo,
        CASE reglas_debehaber WHEN 'CRÉDITO' THEN 'C' WHEN 'DÉBITO' THEN 'D' END AS sentido,
        reglas_codigo_excel AS codigo, true AS validar
    FROM public.bancos_guardado_listareglas
    UNION ALL
    SELECT 'MENSUAL', id, cuenta, reglas_idsubtipo_valor, 'A', reglas_codigo_excel,
        COALESCE(validar, false)
    FROM public.bancos_mes_guardado_listareglas
) r
JOIN global_prod.bancos_migracion_configuracion_legacy mc
  ON mc.origen = r.origen AND mc.id_legacy = r.id AND mc.cuenta_legacy = r.cuenta
WHERE r.subtipo ~ '^[0-9]+$' AND r.sentido IS NOT NULL;

-- Mapeos por subtipo. Sólo inserta referencias válidas del ERP.
INSERT INTO global_prod.bancos_mapeo_cuenta_contable
    (configuracion_id, subtipo_valor_zetti_id, cuenta_zetti_id, observacion)
SELECT DISTINCT mc.configuracion_id, v.id_stv::smallint, v.id_cuenta_contable::bigint,
    'MIGRACION-010|' || v.origen
FROM (
    SELECT 'BANCOS'::varchar AS origen, cuenta, id_stv, id_cuenta_contable FROM public.bancos_guardado_cuentasvalores
    UNION ALL
    SELECT 'MENSUAL', cuenta, id_stv, id_cuenta_contable FROM public.bancos_mes_guardado_cuentasvalores
) v
JOIN global_prod.bancos_migracion_configuracion_legacy mc
  ON mc.origen = v.origen AND mc.cuenta_legacy = v.cuenta
JOIN public.cuenta c ON c.id = v.id_cuenta_contable::bigint
WHERE v.id_stv ~ '^[0-9]+$' AND v.id_cuenta_contable ~ '^[0-9]+$'
ON CONFLICT (configuracion_id, subtipo_valor_zetti_id, cuenta_zetti_id) DO NOTHING;

-- Reglas de asignación automática de usuario (RAAU), propias del flujo mensual.
INSERT INTO global_prod.bancos_regla_asignacion_usuario
    (configuracion_id, subtipo_valor_zetti_id, usuario, observacion)
SELECT DISTINCT mc.configuracion_id, r.id_subtipo::smallint, r.id_usuario::bigint,
    'MIGRACION-010|RAAU|' || r.identificador
FROM public.bancos_mes_raau r
JOIN global_prod.bancos_migracion_configuracion_legacy mc
  ON mc.origen = 'MENSUAL' AND mc.cuenta_legacy = r.num_cuenta
JOIN global_prod.rrhh_login u ON u.id = r.id_usuario::bigint
WHERE r.id_subtipo ~ '^[0-9]+$' AND r.id_usuario ~ '^[0-9]+$'
ON CONFLICT (configuracion_id, subtipo_valor_zetti_id, usuario) DO NOTHING;

COMMIT;
