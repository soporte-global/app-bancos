BEGIN;

UPDATE global_prod.hub_permisos_usuario
SET ignorado = COALESCE(ignorado, current_timestamp),
    fecha_actualizacion = current_timestamp
WHERE origen = 'global_prod'
  AND origen_id IN (
      'app_bancos:usuario:mcaballero:administrador',
      'app_bancos:usuario:mcaballero:bandeja-mensual',
      'app_bancos:usuario:hvega:administrador',
      'app_bancos:usuario:hvega:bandeja-mensual'
  );

UPDATE global_prod.hub_permisos
SET ignorado = COALESCE(ignorado, current_timestamp),
    fecha_actualizacion = current_timestamp
WHERE origen = 'global_prod'
  AND origen_id IN (
      'app_bancos:acceso:administrador',
      'app_bancos:destino:bandeja-mensual'
  );

UPDATE global_prod.hub_aplicaciones
SET ignorado = COALESCE(ignorado, current_timestamp),
    fecha_modificacion = current_timestamp
WHERE id = 12
  AND origen = 'global_prod'
  AND origen_id = 'app_bancos';

COMMIT;
