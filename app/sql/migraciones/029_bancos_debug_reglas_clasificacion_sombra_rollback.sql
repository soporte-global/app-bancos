BEGIN;

DELETE FROM global_temp.bancos_regla_clasificacion
WHERE observacion LIKE 'SOMBRA-029|REGLA|%';

COMMIT;
