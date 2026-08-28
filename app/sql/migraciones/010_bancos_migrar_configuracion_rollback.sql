BEGIN;

DELETE FROM global_prod.bancos_regla_clasificacion r
USING global_prod.bancos_migracion_configuracion_legacy mc
WHERE r.configuracion_id = mc.configuracion_id
  AND r.observacion = 'MIGRACION-010|' || mc.origen;

DELETE FROM global_prod.bancos_mapeo_cuenta_contable m
USING global_prod.bancos_migracion_configuracion_legacy mc
WHERE m.configuracion_id = mc.configuracion_id
  AND m.observacion = 'MIGRACION-010|' || mc.origen;

DELETE FROM global_prod.bancos_regla_asignacion_usuario r
WHERE r.observacion LIKE 'MIGRACION-010|RAAU|%';

DELETE FROM global_prod.bancos_configuracion_cuenta cc
USING global_prod.bancos_migracion_configuracion_legacy mc
WHERE cc.configuracion_id = mc.configuracion_id;

DELETE FROM global_prod.bancos_configuracion c
USING global_prod.bancos_migracion_configuracion_legacy mc
WHERE c.id = mc.configuracion_id
  AND c.observacion = 'MIGRACION-010|' || mc.origen || '|' || mc.id_legacy || '|' || mc.cuenta_legacy || '|' || mc.banco_zetti_id;

DROP TABLE IF EXISTS global_prod.bancos_migracion_configuracion_legacy;

COMMIT;
