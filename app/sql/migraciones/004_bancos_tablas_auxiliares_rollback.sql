BEGIN;

DROP TABLE IF EXISTS global_prod.bancos_recepcion_mensaje CASCADE;
DROP TABLE IF EXISTS global_prod.bancos_mensaje_movimiento CASCADE;
DROP TABLE IF EXISTS global_prod.bancos_reserva_recurso CASCADE;
DROP TABLE IF EXISTS global_prod.bancos_asociacion_movimiento CASCADE;
DROP TABLE IF EXISTS global_prod.bancos_linea_borrador_asiento CASCADE;
DROP TABLE IF EXISTS global_prod.bancos_borrador_asiento CASCADE;
DROP TABLE IF EXISTS global_prod.bancos_historial_asignacion CASCADE;
DROP TABLE IF EXISTS global_prod.bancos_movimiento_extracto CASCADE;
DROP TABLE IF EXISTS global_prod.bancos_importacion_extracto CASCADE;
DROP TABLE IF EXISTS global_prod.bancos_regla_asignacion_usuario CASCADE;
DROP TABLE IF EXISTS global_prod.bancos_mapeo_cuenta_contable CASCADE;
DROP TABLE IF EXISTS global_prod.bancos_regla_clasificacion CASCADE;
DROP TABLE IF EXISTS global_prod.bancos_configuracion_cuenta CASCADE;
DROP TABLE IF EXISTS global_prod.bancos_configuracion CASCADE;
DROP TABLE IF EXISTS global_prod.bancos_conciliacion_cheque CASCADE;
DROP TABLE IF EXISTS global_prod.bancos_estado CASCADE;

COMMIT;