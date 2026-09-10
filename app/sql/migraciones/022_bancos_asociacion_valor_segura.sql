BEGIN;

DO $$
DECLARE
    esquema text;
BEGIN
    FOREACH esquema IN ARRAY ARRAY['global_prod', 'global_temp']
    LOOP
        EXECUTE format(
            'CREATE UNIQUE INDEX IF NOT EXISTS bancos_asociacion_movimiento_un_valor_activo_uk
             ON %I.bancos_asociacion_movimiento (movimiento_id)
             WHERE activo AND valor_zetti_id IS NOT NULL', esquema
        );
        EXECUTE format(
            'CREATE UNIQUE INDEX IF NOT EXISTS bancos_asociacion_movimiento_un_asiento_activo_uk
             ON %I.bancos_asociacion_movimiento (movimiento_id)
             WHERE activo AND asiento_zetti_id IS NOT NULL', esquema
        );
        EXECUTE format(
            'CREATE UNIQUE INDEX IF NOT EXISTS bancos_asociacion_movimiento_un_borrador_activo_uk
             ON %I.bancos_asociacion_movimiento (movimiento_id)
             WHERE activo AND borrador_asiento_id IS NOT NULL', esquema
        );
        EXECUTE format(
            'CREATE UNIQUE INDEX IF NOT EXISTS bancos_reserva_recurso_un_valor_activo_uk
             ON %I.bancos_reserva_recurso (movimiento_id)
             WHERE activo AND valor_zetti_id IS NOT NULL', esquema
        );
        EXECUTE format(
            'CREATE UNIQUE INDEX IF NOT EXISTS bancos_reserva_recurso_un_asiento_activo_uk
             ON %I.bancos_reserva_recurso (movimiento_id)
             WHERE activo AND asiento_zetti_id IS NOT NULL', esquema
        );
        EXECUTE format(
            'CREATE UNIQUE INDEX IF NOT EXISTS bancos_reserva_recurso_un_borrador_activo_uk
             ON %I.bancos_reserva_recurso (movimiento_id)
             WHERE activo AND borrador_asiento_id IS NOT NULL', esquema
        );
    END LOOP;
END
$$;

INSERT INTO global_prod.hub_permisos (
    descripcion, fecha_creacion, fecha_actualizacion, ignorado,
    aplicacion, tipo_permiso, nivel, nombre_interno, icon, origen, origen_id
)
VALUES (
    'Asociar valores ERP a movimientos mensuales',
    current_timestamp, current_timestamp, NULL,
    12, 2, 1, 'movimiento-asociar-valor', 'link',
    'global_prod', 'app_bancos:accion:movimiento-asociar-valor'
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
