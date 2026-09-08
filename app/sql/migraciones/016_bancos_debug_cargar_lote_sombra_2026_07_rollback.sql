-- Revierte únicamente el lote de sombra 016. No toca fixtures ni producción.
BEGIN;

DELETE FROM global_temp.bancos_asociacion_movimiento a
USING global_temp.bancos_movimiento_extracto m,
      global_temp.bancos_importacion_extracto i
WHERE a.movimiento_id = m.id
  AND m.importacion_id = i.id
  AND i.observacion = 'SOMBRA-016|IMPORTACION|35333';

DELETE FROM global_temp.bancos_movimiento_extracto m
USING global_temp.bancos_importacion_extracto i
WHERE m.importacion_id = i.id
  AND i.observacion = 'SOMBRA-016|IMPORTACION|35333';

DELETE FROM global_temp.bancos_importacion_extracto
WHERE observacion = 'SOMBRA-016|IMPORTACION|35333';

DELETE FROM global_temp.bancos_configuracion
WHERE observacion = 'SOMBRA-016|CONFIGURACION|29556';

COMMIT;
