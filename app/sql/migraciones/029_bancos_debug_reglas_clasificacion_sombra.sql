BEGIN;

INSERT INTO global_temp.bancos_regla_clasificacion
    (configuracion_id, subtipo_valor_zetti_id, sentido, codigo_extracto,
     validar_automaticamente, observacion)
SELECT destino.id, origen.subtipo_valor_zetti_id, origen.sentido,
       origen.codigo_extracto, origen.validar_automaticamente,
       'SOMBRA-029|REGLA|' || origen.id
FROM global_temp.bancos_configuracion destino
JOIN global_prod.bancos_regla_clasificacion origen
  ON origen.configuracion_id = split_part(destino.observacion, '|', 3)::bigint
WHERE destino.observacion LIKE 'SOMBRA-016|CONFIGURACION|%'
   OR destino.observacion LIKE 'SOMBRA-017|CONFIGURACION|%'
ON CONFLICT DO NOTHING;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM global_temp.bancos_regla_clasificacion
        WHERE observacion LIKE 'SOMBRA-029|REGLA|%'
    ) THEN
        RAISE EXCEPTION '029 validación: no se cargaron reglas de clasificación de sombra';
    END IF;
END;
$$;

COMMIT;
