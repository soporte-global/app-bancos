WITH permisos_efectivos_usuario AS (
    SELECT
        hp.id,
        hp.aplicacion,
        hp.tipo_permiso,
        hp.nivel,
        hp.descripcion,
        hp.nombre_interno,
        hp.icon
    FROM global_prod.hub_permisos_efectivos_usuario hpeu
    INNER JOIN global_prod.hub_permisos hp
        ON hp.id = hpeu.permiso
       AND hp.ignorado IS NULL
    WHERE hpeu.usuario = :usuario
),
accesos_por_usuario AS (
    SELECT aplicacion, min(nivel) AS nivel
    FROM permisos_efectivos_usuario
    WHERE tipo_permiso = 1
      AND nivel < 3
    GROUP BY aplicacion
),
accesos AS (
    SELECT aplicacion, nivel
    FROM accesos_por_usuario
    UNION ALL
    SELECT ha.id, 2
    FROM global_prod.hub_aplicaciones ha
    WHERE :forzar_habilitado = 1
      AND ha.id = :forzar_aplicacion
      AND ha.ignorado IS NULL
),
aplicaciones AS (
    SELECT aplicacion, min(nivel) AS nivel
    FROM accesos
    GROUP BY aplicacion
),
permisos_por_usuario AS (
    SELECT *
    FROM permisos_efectivos_usuario
    WHERE tipo_permiso <> 1
),
permisos AS (
    SELECT * FROM permisos_por_usuario
    UNION
    SELECT
        hp.id,
        hp.aplicacion,
        hp.tipo_permiso,
        hp.nivel,
        hp.descripcion,
        hp.nombre_interno,
        hp.icon
    FROM global_prod.hub_permisos hp
    WHERE :forzar_habilitado = 1
      AND hp.aplicacion = :forzar_aplicacion
      AND hp.tipo_permiso <> 1
      AND hp.ignorado IS NULL
)
SELECT
    ha.id,
    ha.nombre,
    ha.url,
    ha.imagen,
    aplicaciones.nivel,
    hn.nombre AS nombre_nivel,
    permisos.id AS id_permiso,
    permisos.tipo_permiso,
    permisos.nivel AS nivel_permiso,
    permisos.descripcion AS descripcion_permiso,
    permisos.nombre_interno,
    permisos.icon
FROM aplicaciones
INNER JOIN global_prod.hub_aplicaciones ha
    ON ha.id = aplicaciones.aplicacion
   AND ha.ignorado IS NULL
INNER JOIN global_prod.hub_niveles hn
    ON hn.id = aplicaciones.nivel
   AND hn.ignorado IS NULL
LEFT JOIN permisos ON permisos.aplicacion = ha.id
ORDER BY ha.nombre, permisos.id;
