SELECT
    rl.id AS id_user,
    rl.usuario AS alias,
    rl.password_hash AS hash,
    EXISTS (
        SELECT 1
        FROM global_prod.hub_usuarios_grupo hug_admin
        WHERE hug_admin.usuario = rl.id
          AND hug_admin.grupo = 1
          AND hug_admin.ignorado IS NULL
    ) AS admin_rrhh,
    COALESCE(grupo.id, 2) AS id_grupo_usuario,
    COALESCE(grupo.nombre, 'Indefinido') AS grupo_usuario,
    grupo.zweb_predeterminado,
    COALESCE(empleado.nombre, rl.usuario) AS nombre,
    COALESCE(empleado.apellido, '') AS apellido,
    NULL::text AS mail
FROM global_prod.rrhh_login rl
LEFT JOIN LATERAL (
    SELECT hg.id, hg.nombre, hg.zweb_predeterminado
    FROM global_prod.hub_usuarios_grupo hug
    INNER JOIN global_prod.hub_grupos hg
        ON hg.id = hug.grupo
       AND hg.ignorado IS NULL
    WHERE hug.usuario = rl.id
      AND hug.ignorado IS NULL
    ORDER BY hg.id
    LIMIT 1
) grupo ON true
LEFT JOIN LATERAL (
    SELECT re.nombre, re.apellido
    FROM global_prod.rrhh_empleados re
    WHERE re.usuario = rl.id
      AND re.estado = 1
      AND re.fecha_baja IS NULL
    ORDER BY re.id
    LIMIT 1
) empleado ON true
WHERE lower(rl.usuario) = lower(:alias)
  AND rl.habilitado
  AND rl.fecha_eliminacion IS NULL
LIMIT 2;
