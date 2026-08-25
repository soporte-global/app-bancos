BEGIN;

-- este desarrollo pasa a ser el laboratorio y la plantilla del core compartido
UPDATE global_prod.hub_aplicaciones
SET nombre = 'Nueva App',
    observacion = 'Laboratorio para migrar el core compartido',
    url = '/nueva_app',
    fecha_modificacion = current_timestamp,
    origen_id = 'nueva_app'
WHERE id = 10
  AND origen = 'global_prod'
  AND origen_id IN ('rrhh_consulta', 'migracion_src');

-- acepta la identidad original y el nombre intermedio usado durante la migracion
UPDATE global_prod.hub_permisos
SET origen_id = regexp_replace(
        origen_id,
        '^(rrhh_consulta|migracion_src):',
        'nueva_app:'
    ),
    fecha_actualizacion = current_timestamp
WHERE aplicacion = 10
  AND origen = 'global_prod'
  AND (
      origen_id LIKE 'rrhh_consulta:%'
      OR origen_id LIKE 'migracion_src:%'
  );

UPDATE global_prod.hub_permisos_grupo
SET origen_id = regexp_replace(
        origen_id,
        '^(rrhh_consulta|migracion_src):',
        'nueva_app:'
    ),
    fecha_actualizacion = current_timestamp
WHERE origen = 'global_prod'
  AND (
      origen_id LIKE 'rrhh_consulta:%'
      OR origen_id LIKE 'migracion_src:%'
  );

COMMIT;
