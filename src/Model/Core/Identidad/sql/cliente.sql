select
    c.id,
    c.documento,
    entidad.codigo as codigo_entidad,
    c.nombres_propios as nombre,
    c.apellido,
    c.entidad_agrupadora as entidad_agrupadora_id,
    agrupadora.nombre as entidad_agrupadora,
    (
        select nullif(btrim(npe.cuenta_corriente), '')::integer
        from public.nodo_por_entidad npe
        where npe.entidad = c.id
          and npe.nodo = 2006202 -- la fila de GRUPO GLOBAL define la regla general
          and npe.habilitado is true
    ) as cuenta_corriente_general,
    coalesce((
        select json_agg(
            json_build_object(
                'nodo_id', npe.nodo,
                'codigo', npe.cuenta_corriente::integer
            )
            order by npe.nodo
        )
        from public.nodo_por_entidad npe
        where npe.entidad = c.id
          and npe.nodo <> 2006202
          and npe.habilitado is true
          and nullif(btrim(npe.cuenta_corriente), '') is not null
    ), '[]'::json) as excepciones_cuenta_corriente
from public.cliente c
inner join public.entidad entidad on entidad.id = c.id
inner join public.entidad agrupadora on agrupadora.id = c.entidad_agrupadora
where c.id = :cliente
limit 2;
