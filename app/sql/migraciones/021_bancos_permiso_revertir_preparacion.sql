BEGIN;

INSERT INTO global_prod.hub_permisos (
    descripcion, fecha_creacion, fecha_actualizacion, ignorado,
    aplicacion, tipo_permiso, nivel, nombre_interno, icon, origen, origen_id
)
VALUES (
    'Revertir preparacion de movimientos mensuales',
    current_timestamp, current_timestamp, NULL,
    12, 2, 1, 'movimiento-revertir-preparacion', 'undo',
    'global_prod', 'app_bancos:accion:movimiento-revertir-preparacion'
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

SELECT setval(
    pg_get_serial_sequence('global_prod.hub_permisos', 'id'),
    (SELECT max(id) FROM global_prod.hub_permisos),
    true
);

COMMIT;
