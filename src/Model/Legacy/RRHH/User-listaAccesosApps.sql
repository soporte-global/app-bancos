select distinct
	ha.id
from global_prod.hub_aplicaciones ha 
inner join global_prod.hub_permisos hp on hp.aplicacion = ha.id and hp.ignorado is null 
inner join global_prod.hub_permisos_efectivos_usuario hpeu on hpeu.permiso = hp.id and hpeu.usuario = :user
where ha.ignorado is null 
and hp.tipo_permiso = 1
and hp.nivel < 3
union
select 
	ha.id
from global_prod.hub_aplicaciones ha 
where ha.ignorado is null 
and ha.id = :forzar_acceso;
