BEGIN;

DELETE FROM global_prod.bancos_mensaje_movimiento
WHERE tipo_mensaje IN ('SISTEMA', 'USUARIO')
  AND emitido_en IN (
      SELECT COALESCE(emision_time AT TIME ZONE 'America/Argentina/Buenos_Aires', now())
      FROM public.bancos_mensajeria
  );

DELETE FROM global_prod.bancos_linea_borrador_asiento l
USING global_prod.bancos_migracion_borrador_legacy mb
WHERE l.borrador_asiento_id = mb.borrador_asiento_id;

DELETE FROM global_prod.bancos_borrador_asiento
WHERE clave_idempotencia LIKE 'MIGRACION-012|BORRADOR|%';

DELETE FROM global_prod.bancos_reserva_recurso
WHERE observacion = 'MIGRACION-012|EXCLUSION';

DELETE FROM global_prod.bancos_asociacion_movimiento
WHERE observacion IN ('MIGRACION-012|VALOR', 'MIGRACION-012|ASIENTO');

DROP TABLE IF EXISTS global_prod.bancos_migracion_borrador_legacy;
DROP TABLE IF EXISTS global_prod.bancos_migracion_recurso_omitido;
DROP TABLE IF EXISTS global_prod.bancos_migracion_emisor_legacy;

ALTER TABLE global_prod.bancos_mensaje_movimiento
    ALTER COLUMN emisor_hub_id SET NOT NULL;

COMMIT;
