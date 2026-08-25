-- si no se pasa id_proc, trae todos los procesos, si no trae el correspondiente al id
select 
	p.*,
	(select inicio_ejecucion from global_control_procesos gcp where gcp.id = p.id_ultimo_proceso ) as inicio_ultimo_proceso,
	(select fin_ejecucion from global_control_procesos gcp where gcp.id = p.id_ultimo_proceso ) as fin_ultimo_proceso,
	(select finalizado_correctamente from global_control_procesos gcp where gcp.id = p.id_ultimo_proceso ) as estado_ultimo_proceso
from (
	select 
		gp.id,
		gp.ruta_proceso,
		gp."extension",
		gp.texto_ok,
		gp.texto_error ,
		gp.ruta_log ,
		gp.usuario,
		gp.ssh,
		gp.ssh_server ,
		gp.nombre_proceso ,
		tp.nombre as tipo_proceso ,
		(select id from global_control_procesos gcp where gcp.proceso = gp.id order by gcp.inicio_ejecucion desc limit 1) as id_ultimo_proceso
	from global_procesos gp
	inner join tipo_proceso tp on gp.tipo_proceso = tp.id 
	where case 
		when :idProc = 0
		then tipo_proceso <> 1
		-- then 2 <> 1
		else gp.id = :idProc
	end
)p;