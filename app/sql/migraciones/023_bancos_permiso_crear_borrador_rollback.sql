BEGIN;

UPDATE global_prod.hub_permisos
SET ignorado = COALESCE(ignorado, current_timestamp),
    fecha_actualizacion = current_timestamp
WHERE origen = 'global_prod'
  AND origen_id = 'app_bancos:accion:movimiento-crear-borrador';

COMMIT;
