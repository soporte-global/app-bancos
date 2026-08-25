-- select 
-- 	l.id,
-- 	l.legajo,
-- 	l.nombre,
-- 	l.apellido,
-- 	l.mail,
-- 	l.direccion,
-- 	l.cuil,
-- 	l.dni
-- from legajos l 
-- where deleted_at is null
-- and l.id = :idLegajo;
select distinct 
	id_legajo,
	legajo,
	REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(apellido, 'ÃƒÂ±', 'ñ'), 'Ã©', 'é'), 'Ã­', 'í'), 'Ã³', 'ó'), 'Ãº', 'ú'), 'Ã¡', 'á'), 'Ã', 'Á'), 'Ã', 'É'), 'Ã', 'Í'), 'Ã', 'Ó') as apellido,
	REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(nombre, 'ÃƒÂ±', 'ñ'), 'Ã©', 'é'), 'Ã­', 'í'), 'Ã³', 'ó'), 'Ãº', 'ú'), 'Ã¡', 'á'), 'Ã', 'Á'), 'Ã', 'É'), 'Ã', 'Í'), 'Ã', 'Ó') as nombre,
	mail,
	direccion,
	dni,
	case 
		when char_length(cuil) <> 13
		then '-CUIL mal cargado-'
		else cuil
	end as cuil,
	id_empresa,
	empresa_ultima_liquidacion as id_empresa_ultima_liquidacion,
	case 
		when id_empresa is null
		then '-Empresa no seleccionada-'
		else (select nombre from empresas where id = b.id_empresa)
	end as nombre_empresa,
	alias,
	estado_zweb,
	cliente_zweb,
	ultimo_saldo,
	fecha_ultimo_saldo
from (
	select 
		a.*,
		case 
			when empresa_ultima_liquidacion in (select e.id from legajos_empresas le inner join empresas e on le.empresa_id = e.id where le.legajo_id = a.id_legajo and le.liquida = 'SI')
			then empresa_ultima_liquidacion
			else (select e.id from legajos_empresas le inner join empresas e on le.empresa_id = e.id where le.legajo_id = a.id_legajo and le.liquida = 'SI' limit 1)
		end as id_empresa
	from (
		select 
			l.id as id_legajo,
			l.legajo,
			u.username as alias,
			trim(concat(l.apellido)) as apellido,
			trim(concat(l.nombre)) as nombre,
			l.mail,
			l.direccion,
			'A COMPLETAR' as estado_zweb,
			l.dni,
			CASE 
				WHEN CHAR_LENGTH(l.cuil) = 11 
				THEN CONCAT(
					SUBSTRING(l.cuil, 1, 2), 
					'-', 
					SUBSTRING(l.cuil, 3, 8), 
					'-', 
					SUBSTRING(l.cuil, 11)
				)
				ELSE l.cuil
			END AS cuil,
			l.cliente_zweb,
			(select distinct empresa_id from novedades n where n.legajo_id = l.id and n.concepto_id in (23,19) and fecha > DATE_SUB(CURDATE(), INTERVAL 45 DAY) order by fecha desc limit 1) as empresa_ultima_liquidacion,
			'A COMPLETAR' as ultimo_saldo,
			'A COMPLETAR' as fecha_ultimo_saldo
		from legajos l 
		left join usuarios u on l.usuario_id = u.id
		left join novedades n on n.legajo_id = l.id and concepto_id in (23,19)
		where l.deleted_at is null
	)a
)b
where alias is not null
and id_legajo = :numLegajo
order by apellido,nombre;