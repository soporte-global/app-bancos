with permisos_efectivos as (
    select
        hp.id,
        hp.aplicacion,
        hp.tipo_permiso,
        hp.nivel,
        hp.descripcion,
        hp.nombre_interno,
        hp.icon
    from global_prod.hub_permisos_efectivos_usuario hpeu
    inner join global_prod.hub_permisos hp
        on hp.id = hpeu.permiso
       and hp.ignorado is null
    where hpeu.usuario = :cuenta
),
permisos_acceso as (
    select
        aplicacion,
        min(nivel) as nivel
    from permisos_efectivos
    where tipo_permiso = 1
      and nivel < 3
    group by aplicacion
)
select
    ha.id as aplicacion_id,
    ha.nombre,
    ha.url,
    ha.imagen,
    permisos_acceso.nivel,
    hn.nombre as nombre_nivel,
    permiso.id as permiso_id,
    permiso.tipo_permiso as permiso_tipo,
    permiso.nivel as permiso_nivel,
    permiso.descripcion as permiso_descripcion,
    permiso.nombre_interno as permiso_nombre_interno,
    permiso.icon as permiso_icono
from permisos_acceso
inner join global_prod.hub_aplicaciones ha
    on ha.id = permisos_acceso.aplicacion
   and ha.ignorado is null
inner join global_prod.hub_niveles hn
    on hn.id = permisos_acceso.nivel
   and hn.ignorado is null
left join permisos_efectivos permiso
    on permiso.aplicacion = ha.id
   and permiso.tipo_permiso <> 1
order by ha.nombre, permiso.id;
