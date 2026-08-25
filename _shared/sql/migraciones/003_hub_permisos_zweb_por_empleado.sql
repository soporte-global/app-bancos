begin;

-- los permisos zweb siguen la relacion explicita del empleado y no una coincidencia de alias
create or replace view global_prod.hub_permisos_rutas_usuario as
select
    rl.id as usuario,
    hpg.permiso,
    'grupo_hub'::varchar(20) as ruta,
    hpg.id as asignacion,
    hug.id as membresia,
    hug.grupo
from global_prod.rrhh_login rl
inner join global_prod.hub_usuarios_grupo hug
    on hug.usuario = rl.id
   and hug.ignorado is null
inner join global_prod.hub_grupos hg
    on hg.id = hug.grupo
   and hg.ignorado is null
inner join global_prod.hub_permisos_grupo hpg
    on hpg.grupo = hug.grupo
   and hpg.ignorado is null
inner join global_prod.hub_permisos hp
    on hp.id = hpg.permiso
   and hp.ignorado is null
inner join global_prod.hub_aplicaciones ha
    on ha.id = hp.aplicacion
   and ha.ignorado is null
inner join global_prod.hub_tipo_permiso htp
    on htp.id = hp.tipo_permiso
   and htp.ignorado is null
inner join global_prod.hub_niveles hn
    on hn.id = hp.nivel
   and hn.ignorado is null
where rl.habilitado is true
  and rl.fecha_eliminacion is null

union all

select
    rl.id as usuario,
    hpu.permiso,
    'usuario'::varchar(20) as ruta,
    hpu.id as asignacion,
    null::bigint as membresia,
    null::bigint as grupo
from global_prod.rrhh_login rl
inner join global_prod.hub_permisos_usuario hpu
    on hpu.usuario = rl.id
   and hpu.ignorado is null
inner join global_prod.hub_permisos hp
    on hp.id = hpu.permiso
   and hp.ignorado is null
inner join global_prod.hub_aplicaciones ha
    on ha.id = hp.aplicacion
   and ha.ignorado is null
inner join global_prod.hub_tipo_permiso htp
    on htp.id = hp.tipo_permiso
   and htp.ignorado is null
inner join global_prod.hub_niveles hn
    on hn.id = hp.nivel
   and hn.ignorado is null
where rl.habilitado is true
  and rl.fecha_eliminacion is null

union all

select
    rl.id as usuario,
    hpgz.permiso,
    'grupo_zweb'::varchar(20) as ruta,
    hpgz.id as asignacion,
    sgu.grupo_usuario as membresia,
    hpgz.grupo_zweb as grupo
from global_prod.rrhh_login rl
inner join lateral (
    select min(re.usuario_zweb) as usuario_zweb
    from global_prod.rrhh_empleados re
    where re.usuario = rl.id
      and re.estado = 1
      and re.fecha_baja is null
      and re.usuario_zweb is not null
    having count(*) = 1
) identidad on true
inner join public.seg_grupo_usuario sgu
    on sgu.usuario = identidad.usuario_zweb
inner join global_prod.hub_permisos_grupo_zweb hpgz
    on hpgz.grupo_zweb = sgu.grupo
   and hpgz.ignorado is null
inner join global_prod.hub_permisos hp
    on hp.id = hpgz.permiso
   and hp.ignorado is null
inner join global_prod.hub_aplicaciones ha
    on ha.id = hp.aplicacion
   and ha.ignorado is null
inner join global_prod.hub_tipo_permiso htp
    on htp.id = hp.tipo_permiso
   and htp.ignorado is null
inner join global_prod.hub_niveles hn
    on hn.id = hp.nivel
   and hn.ignorado is null
where rl.habilitado is true
  and rl.fecha_eliminacion is null;

commit;
