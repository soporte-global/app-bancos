BEGIN;

UPDATE global_prod.hub_permisos_usuario
SET ignorado = COALESCE(ignorado, current_timestamp),
    fecha_actualizacion = current_timestamp
WHERE origen = 'global_prod'
  AND origen_id IN (
      'app_bancos:usuario:mcaballero:importaciones',
      'app_bancos:usuario:hvega:importaciones'
  );

UPDATE global_prod.hub_permisos
SET ignorado = COALESCE(ignorado, current_timestamp),
    fecha_actualizacion = current_timestamp
WHERE origen = 'global_prod'
  AND origen_id IN (
      'app_bancos:destino:importaciones',
      'app_bancos:accion:importacion-previsualizar'
  );

COMMIT;
