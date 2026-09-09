BEGIN;

-- APP BANCOS tiene identidad propia en Hub. El id es parte del contrato de
-- configuracion de la aplicacion y no puede reutilizar otra fila.
DO $$
BEGIN
    IF EXISTS (
        SELECT 1
        FROM global_prod.hub_aplicaciones
        WHERE id = 12
          AND (origen, origen_id) <> ('global_prod', 'app_bancos')
    ) OR EXISTS (
        SELECT 1
        FROM global_prod.hub_aplicaciones
        WHERE origen = 'global_prod'
          AND origen_id = 'app_bancos'
          AND id <> 12
    ) THEN
        RAISE EXCEPTION 'El id 12 o el origen app_bancos ya pertenecen a otra aplicacion';
    END IF;
END
$$;

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
    12,
    'APP BANCOS',
    1,
    'Gestion bancaria y conciliacion',
    NULL,
    '/app-bancos',
    current_timestamp,
    current_timestamp,
    NULL,
    'global_prod',
    'app_bancos'
)
ON CONFLICT (id) DO UPDATE
SET nombre = EXCLUDED.nombre,
    categoria = EXCLUDED.categoria,
    observacion = EXCLUDED.observacion,
    imagen = EXCLUDED.imagen,
    url = EXCLUDED.url,
    fecha_modificacion = current_timestamp,
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
VALUES
    (
        'Acceso administrador',
        current_timestamp,
        current_timestamp,
        NULL,
        12,
        1,
        1,
        NULL,
        NULL,
        'global_prod',
        'app_bancos:acceso:administrador'
    ),
    (
        'Bandeja mensual',
        current_timestamp,
        current_timestamp,
        NULL,
        12,
        2,
        1,
        'bandeja-mensual',
        'university',
        'global_prod',
        'app_bancos:destino:bandeja-mensual'
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

-- La asignacion falla completa si alguno de los dos aliases no identifica una
-- unica cuenta activa. No se acepta una coincidencia parcial o ambigua.
DO $$
DECLARE
    cantidad integer;
BEGIN
    SELECT count(*)
    INTO cantidad
    FROM global_prod.rrhh_login
    WHERE lower(usuario) IN ('mcaballero', 'hvega')
      AND habilitado IS TRUE
      AND fecha_eliminacion IS NULL;

    IF cantidad <> 2 THEN
        RAISE EXCEPTION 'Se esperaban las cuentas activas unicas mcaballero y hvega; encontradas: %', cantidad;
    END IF;
END
$$;

WITH usuarios AS (
    SELECT id, lower(usuario) AS alias
    FROM global_prod.rrhh_login
    WHERE lower(usuario) IN ('mcaballero', 'hvega')
      AND habilitado IS TRUE
      AND fecha_eliminacion IS NULL
), permisos AS (
    SELECT
        id,
        CASE origen_id
            WHEN 'app_bancos:acceso:administrador' THEN 'administrador'
            ELSE 'bandeja-mensual'
        END AS clave
    FROM global_prod.hub_permisos
    WHERE origen = 'global_prod'
      AND origen_id IN (
          'app_bancos:acceso:administrador',
          'app_bancos:destino:bandeja-mensual'
      )
)
INSERT INTO global_prod.hub_permisos_usuario (
    usuario,
    permiso,
    descripcion,
    fecha_creacion,
    fecha_actualizacion,
    ignorado,
    origen,
    origen_id
)
SELECT
    usuarios.id,
    permisos.id,
    'Acceso inicial APP BANCOS',
    current_timestamp,
    current_timestamp,
    NULL,
    'global_prod',
    'app_bancos:usuario:' || usuarios.alias || ':' || permisos.clave
FROM usuarios
CROSS JOIN permisos
ON CONFLICT (origen, origen_id) DO UPDATE
SET usuario = EXCLUDED.usuario,
    permiso = EXCLUDED.permiso,
    descripcion = EXCLUDED.descripcion,
    fecha_actualizacion = current_timestamp,
    ignorado = NULL;

-- El permiso administrador se concede solamente en forma directa. Se anula
-- cualquier ruta heredada o asignacion directa accidental a otra cuenta.
UPDATE global_prod.hub_permisos_grupo
SET ignorado = COALESCE(ignorado, current_timestamp),
    fecha_actualizacion = current_timestamp
WHERE permiso = (
    SELECT id
    FROM global_prod.hub_permisos
    WHERE origen = 'global_prod'
      AND origen_id = 'app_bancos:acceso:administrador'
);

UPDATE global_prod.hub_permisos_grupo_zweb
SET ignorado = COALESCE(ignorado, current_timestamp),
    fecha_actualizacion = current_timestamp
WHERE permiso = (
    SELECT id
    FROM global_prod.hub_permisos
    WHERE origen = 'global_prod'
      AND origen_id = 'app_bancos:acceso:administrador'
);

UPDATE global_prod.hub_permisos_usuario
SET ignorado = COALESCE(ignorado, current_timestamp),
    fecha_actualizacion = current_timestamp
WHERE permiso = (
    SELECT id
    FROM global_prod.hub_permisos
    WHERE origen = 'global_prod'
      AND origen_id = 'app_bancos:acceso:administrador'
)
AND usuario NOT IN (
    SELECT id
    FROM global_prod.rrhh_login
    WHERE lower(usuario) IN ('mcaballero', 'hvega')
      AND habilitado IS TRUE
      AND fecha_eliminacion IS NULL
);

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
    pg_get_serial_sequence('global_prod.hub_permisos_usuario', 'id'),
    (SELECT max(id) FROM global_prod.hub_permisos_usuario),
    true
);

COMMIT;
