INSERT INTO global_prod.hub_aplicaciones (
    id,
    nombre,
    categoria,
    observacion,
    imagen,
    url,
    fecha_creacion,
    fecha_modificacion,
    ignorado,
    origen,
    origen_id
)
VALUES (
    10,
    'Consulta RRHH',
    1,
    'Consulta de liquidaciones para RRHH',
    NULL,
    '/rrhh_consulta',
    current_timestamp,
    current_timestamp,
    NULL,
    'global_prod',
    'rrhh_consulta'
)
ON CONFLICT (origen, origen_id) DO UPDATE
SET nombre = EXCLUDED.nombre,
    categoria = EXCLUDED.categoria,
    observacion = EXCLUDED.observacion,
    imagen = EXCLUDED.imagen,
    url = EXCLUDED.url,
    ignorado = NULL;

WITH permiso AS (
    INSERT INTO global_prod.hub_permisos (
        descripcion,
        fecha_creacion,
        fecha_actualizacion,
        ignorado,
        aplicacion,
        tipo_permiso,
        nivel,
        nombre_interno,
        icon,
        origen,
        origen_id
    )
    VALUES (
        'Acceso administrador',
        current_timestamp,
        current_timestamp,
        NULL,
        10,
        1,
        1,
        NULL,
        NULL,
        'global_prod',
        'rrhh_consulta:acceso:administrador'
    )
    ON CONFLICT (origen, origen_id) DO UPDATE
    SET descripcion = EXCLUDED.descripcion,
        ignorado = NULL,
        aplicacion = EXCLUDED.aplicacion,
        tipo_permiso = EXCLUDED.tipo_permiso,
        nivel = EXCLUDED.nivel
    RETURNING id
)
INSERT INTO global_prod.hub_permisos_grupo (
    grupo,
    permiso,
    descripcion,
    fecha_creacion,
    fecha_actualizacion,
    ignorado,
    origen,
    origen_id
)
SELECT
    1,
    permiso.id,
    'Acceso inicial del grupo Master',
    current_timestamp,
    current_timestamp,
    NULL,
    'global_prod',
    'rrhh_consulta:master:administrador'
FROM permiso
ON CONFLICT (origen, origen_id) DO UPDATE
SET grupo = EXCLUDED.grupo,
    permiso = EXCLUDED.permiso,
    descripcion = EXCLUDED.descripcion,
    ignorado = NULL;

INSERT INTO global_prod.hub_permisos (
    descripcion,
    fecha_creacion,
    fecha_actualizacion,
    ignorado,
    aplicacion,
    tipo_permiso,
    nivel,
    nombre_interno,
    icon,
    origen,
    origen_id
)
VALUES (
    'Acceso general',
    current_timestamp,
    current_timestamp,
    NULL,
    10,
    1,
    2,
    NULL,
    NULL,
    'global_prod',
    'rrhh_consulta:acceso:general'
)
ON CONFLICT (origen, origen_id) DO UPDATE
SET descripcion = EXCLUDED.descripcion,
    ignorado = NULL,
    aplicacion = EXCLUDED.aplicacion,
    tipo_permiso = EXCLUDED.tipo_permiso,
    nivel = EXCLUDED.nivel;

SELECT setval(
    pg_get_serial_sequence('global_prod.hub_aplicaciones', 'id'),
    (SELECT max(id) FROM global_prod.hub_aplicaciones),
    true
);
SELECT setval(
    pg_get_serial_sequence('global_prod.hub_permisos', 'id'),
    (SELECT max(id) FROM global_prod.hub_permisos),
    true
);
SELECT setval(
    pg_get_serial_sequence('global_prod.hub_permisos_grupo', 'id'),
    (SELECT max(id) FROM global_prod.hub_permisos_grupo),
    true
);

