-- trae el detalle de los permisos internos de la aplicación
with permisos_concedidos as (
	select 
		hp.id as id_permiso,
		hp.aplicacion as id_aplicacion,
		hp.tipo_permiso,
		hp.nivel,
		hp.nombre_interno,
		hp.icon,
		hp.descripcion
	from global_prod.hub_aplicaciones ha 
	inner join global_prod.hub_permisos hp on hp.aplicacion = ha.id and hp.ignorado is null 
	inner join global_prod.hub_permisos_efectivos_usuario hpeu on hpeu.permiso = hp.id and hpeu.usuario = :user
	where ha.id = :app
	and ha.ignorado is null 
	and hp.tipo_permiso <> 1
)
select * from permisos_concedidos
union all 
select 
	hp.id as id_permiso,
	hp.aplicacion as id_aplicacion,
	hp.tipo_permiso,
	hp.nivel,
	hp.nombre_interno,
	hp.icon,
	hp.descripcion
from global_prod.hub_aplicaciones ha 
inner join global_prod.hub_permisos hp on hp.aplicacion = ha.id and hp.ignorado is null 
where ha.id = :app
and ha.ignorado is null 
and hp.tipo_permiso <> 1
and :forzar_permisos = 1
and hp.id not in (select id_permiso from permisos_concedidos);
