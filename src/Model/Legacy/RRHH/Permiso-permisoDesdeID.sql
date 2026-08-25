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
where hp.id = :permiso
and ha.ignorado is null;
