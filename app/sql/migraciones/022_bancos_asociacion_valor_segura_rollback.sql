BEGIN;

DROP INDEX IF EXISTS global_temp.bancos_reserva_recurso_un_borrador_activo_uk;
DROP INDEX IF EXISTS global_temp.bancos_reserva_recurso_un_asiento_activo_uk;
DROP INDEX IF EXISTS global_temp.bancos_reserva_recurso_un_valor_activo_uk;
DROP INDEX IF EXISTS global_temp.bancos_asociacion_movimiento_un_borrador_activo_uk;
DROP INDEX IF EXISTS global_temp.bancos_asociacion_movimiento_un_asiento_activo_uk;
DROP INDEX IF EXISTS global_temp.bancos_asociacion_movimiento_un_valor_activo_uk;
DROP INDEX IF EXISTS global_prod.bancos_reserva_recurso_un_borrador_activo_uk;
DROP INDEX IF EXISTS global_prod.bancos_reserva_recurso_un_asiento_activo_uk;
DROP INDEX IF EXISTS global_prod.bancos_reserva_recurso_un_valor_activo_uk;
DROP INDEX IF EXISTS global_prod.bancos_asociacion_movimiento_un_borrador_activo_uk;
DROP INDEX IF EXISTS global_prod.bancos_asociacion_movimiento_un_asiento_activo_uk;
DROP INDEX IF EXISTS global_prod.bancos_asociacion_movimiento_un_valor_activo_uk;

UPDATE global_prod.hub_permisos
SET ignorado = COALESCE(ignorado, current_timestamp),
    fecha_actualizacion = current_timestamp
WHERE origen = 'global_prod'
  AND origen_id = 'app_bancos:accion:movimiento-asociar-valor';

COMMIT;
