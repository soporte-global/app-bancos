-- trae el nivel de permiso de acceso para esta aplicación, junto con sus datos
select *
from (
	select *
	from (
		select 
			ha.id,
			ha.nombre,
			ha.url,
			ha.imagen,
			hp.nivel,
			hn.nombre as nombre_nivel
		from global_prod.hub_aplicaciones ha 
		inner join global_prod.hub_permisos hp on hp.aplicacion = ha.id and hp.ignorado is null 
		inner join global_prod.hub_permisos_efectivos_usuario hpeu on hpeu.permiso = hp.id and hpeu.usuario = :user
		inner join global_prod.hub_niveles hn on hn.id = hp.nivel and hn.ignorado is null
		where ha.id = :app
		and ha.ignorado is null  
		and hp.tipo_permiso = 1
		and hp.nivel < 3
	) as nivel_acceso_concedido
	-- 
	union all 
	-- acceso forzado
	select 
		ha.id,
		ha.nombre,
		ha.url,
		ha.imagen,
		2,
		(select nombre from global_prod.hub_niveles where id = 2) as nombre_nivel
	from global_prod.hub_aplicaciones ha 
	inner join global_prod.hub_niveles hn on hn.id = 1
	where ha.id = :app
	and ha.ignorado is null 
	and :forzar_acceso = 1
	and exists (
		select 1
		from global_prod.hub_niveles
		where id = 2
		and ignorado is null
	)
) as acceso_utilizar
order by nivel asc 
limit 1;
