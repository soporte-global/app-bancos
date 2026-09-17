BEGIN;

UPDATE global_prod.hub_permisos_usuario
SET ignorado = COALESCE(ignorado, current_timestamp),
    fecha_actualizacion = current_timestamp
WHERE origen = 'global_prod'
  AND origen_id IN (
      'app_bancos:usuario:mcaballero:configuraciones',
      'app_bancos:usuario:hvega:configuraciones'
  );

UPDATE global_prod.hub_permisos
SET ignorado = COALESCE(ignorado, current_timestamp),
    fecha_actualizacion = current_timestamp
WHERE origen = 'global_prod'
  AND origen_id IN (
      'app_bancos:destino:configuraciones',
      'app_bancos:accion:configuracion-actualizar-validacion-automatica'
  );

COMMIT;
