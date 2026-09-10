BEGIN;

UPDATE global_prod.hub_permisos
SET ignorado = current_timestamp,
    fecha_actualizacion = current_timestamp
WHERE aplicacion = 12
  AND origen = 'global_prod'
  AND origen_id IN (
      'app_bancos:accion:movimiento-agregar-mensaje',
      'app_bancos:accion:movimiento-marcar-mensajes-leidos'
  );

COMMIT;
