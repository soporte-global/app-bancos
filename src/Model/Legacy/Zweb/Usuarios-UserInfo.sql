WITH usuario AS (
    SELECT
        su.usuario AS id_zweb,
        su.alias,
        su.descripcion AS nombre_registrado
    FROM public.seg_usuario su
    WHERE su.estado = 1
      AND (
          lower(su.alias) = lower(:alias_filtro)
          OR (
              CAST(:fallback_habilitado AS integer) = 1
              AND (
                  lower(su.alias) = lower(:fallback_alias)
                  OR su.usuario::text = :fallback_id
              )
          )
      )
    ORDER BY
        CASE WHEN lower(su.alias) = lower(:alias_orden) THEN 0 ELSE 1 END,
        su.usuario
    LIMIT 1
)
SELECT
    u.id_zweb,
    u.alias,
    u.nombre_registrado,
    COALESCE((
        SELECT json_agg(
            json_build_object('nodo', n.id, 'nombre', n.nombre)
            ORDER BY n.nombre
        )
        FROM public.seg_usuario_nodo sun
        INNER JOIN public.nodo n ON n.id = sun.nodo
        WHERE sun.usuario = u.id_zweb
          AND length(n.codigo_jerarquico) > 15
          AND n.nombre NOT ILIKE ALL (ARRAY['ZZ%', 'COMPRAS %', '%-DEPOSITO'])
    ), '[]'::json) AS nodos_habilitados,
    COALESCE((
        SELECT json_agg(
            json_build_object('id', sg.grupo::varchar, 'nombre', sg.descripcion)
            ORDER BY sg.descripcion
        )
        FROM public.seg_grupo_usuario sgu
        INNER JOIN public.seg_grupo sg ON sg.grupo = sgu.grupo
        WHERE sgu.usuario = u.id_zweb
    ), '[]'::json) AS grupos_usuario
FROM usuario u;
