with usuario as (
    select
        su.usuario as id,
        su.alias,
        su.descripcion as nombre,
        su.mail
    from public.seg_usuario su
    where su.usuario = :usuario
      and su.estado = 1
    limit 2
)
select
    u.id,
    u.alias,
    u.nombre,
    u.mail,
    coalesce((
        select json_agg(
            json_build_object('id', n.id, 'nombre', n.nombre)
            order by n.nombre, n.id
        )
        from public.seg_usuario_nodo sun
        inner join public.nodo n on n.id = sun.nodo
        where sun.usuario = u.id
          and length(n.codigo_jerarquico) > 15
          and n.nombre not ilike all (array['ZZ%', 'COMPRAS %', '%-DEPOSITO'])
    ), '[]'::json) as nodos,
    coalesce((
        select json_agg(
            json_build_object('id', sg.grupo::varchar, 'nombre', sg.descripcion)
            order by sg.descripcion, sg.grupo
        )
        from public.seg_grupo_usuario sgu
        inner join public.seg_grupo sg on sg.grupo = sgu.grupo
        where sgu.usuario = u.id
    ), '[]'::json) as grupos
from usuario u;
