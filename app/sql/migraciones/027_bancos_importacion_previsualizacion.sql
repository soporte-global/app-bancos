BEGIN;

INSERT INTO global_prod.hub_permisos (
    descripcion, fecha_creacion, fecha_actualizacion, ignorado,
    aplicacion, tipo_permiso, nivel, nombre_interno, icon, origen, origen_id
)
VALUES
    (
        'Importaciones de extractos',
        current_timestamp, current_timestamp, NULL,
        12, 2, 1, 'importaciones', 'file-import',
        'global_prod', 'app_bancos:destino:importaciones'
    ),
    (
        'Previsualizar importación de extracto',
        current_timestamp, current_timestamp, NULL,
        12, 2, 1, 'importacion-previsualizar', 'file-magnifying-glass',
        'global_prod', 'app_bancos:accion:importacion-previsualizar'
    )
ON CONFLICT (origen, origen_id) DO UPDATE
SET descripcion = EXCLUDED.descripcion,
    fecha_actualizacion = current_timestamp,
    ignorado = NULL,
    aplicacion = EXCLUDED.aplicacion,
    tipo_permiso = EXCLUDED.tipo_permiso,
    nivel = EXCLUDED.nivel,
    nombre_interno = EXCLUDED.nombre_interno,
    icon = EXCLUDED.icon;

DO $$
DECLARE
    cantidad integer;
BEGIN
    SELECT count(*) INTO cantidad
    FROM global_prod.rrhh_login
    WHERE lower(usuario) IN ('mcaballero', 'hvega')
      AND habilitado IS TRUE
      AND fecha_eliminacion IS NULL;
    IF cantidad <> 2 THEN
        RAISE EXCEPTION 'Se esperaban exactamente las cuentas activas mcaballero y hvega; encontradas: %', cantidad;
    END IF;
END
$$;

WITH usuarios AS (
    SELECT id, lower(usuario) AS alias
    FROM global_prod.rrhh_login
    WHERE lower(usuario) IN ('mcaballero', 'hvega')
      AND habilitado IS TRUE
      AND fecha_eliminacion IS NULL
), destino AS (
    SELECT id
    FROM global_prod.hub_permisos
    WHERE origen = 'global_prod'
      AND origen_id = 'app_bancos:destino:importaciones'
)
INSERT INTO global_prod.hub_permisos_usuario (
    usuario, permiso, descripcion, fecha_creacion, fecha_actualizacion,
    ignorado, origen, origen_id
)
SELECT usuarios.id, destino.id, 'Acceso inicial a importaciones APP BANCOS',
       current_timestamp, current_timestamp, NULL,
       'global_prod', 'app_bancos:usuario:' || usuarios.alias || ':importaciones'
FROM usuarios
CROSS JOIN destino
ON CONFLICT (origen, origen_id) DO UPDATE
SET usuario = EXCLUDED.usuario,
    permiso = EXCLUDED.permiso,
    descripcion = EXCLUDED.descripcion,
    fecha_actualizacion = current_timestamp,
    ignorado = NULL;

SELECT setval(
    pg_get_serial_sequence('global_prod.hub_permisos', 'id'),
    (SELECT max(id) FROM global_prod.hub_permisos),
    true
);
SELECT setval(
    pg_get_serial_sequence('global_prod.hub_permisos_usuario', 'id'),
    (SELECT max(id) FROM global_prod.hub_permisos_usuario),
    true
);

COMMIT;
