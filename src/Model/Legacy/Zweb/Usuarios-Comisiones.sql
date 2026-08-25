	-------------------------
	-- detalle de ventas full
	select 
		*,
		round((de.monto_venta * de.pc) / 100,2) as comision_producto
	from (
		-------------------------
		-- detalle de ventas base
		select 
			v.id as id_valor,
			date(v.fecha_emision) as fecha_emision,
			v.fecha_emision::time as hora_emision,
			v.codificacion as numero,
			tv.id as id_tipo_valor,
			tv.descripcion as tipo_valor, 
			c.id as id_cliente,
			c.nombres_propios || ' ' || c.apellido as cliente,
			ed.entidad_agrupadora as id_entidad_agrupadora,
			ea.nombre as entidad_agrupadora,  
			su.usuario as id_usuario,
			su.descripcion as usuario,
			su.alias,
			ed.grupo_usuario as id_grupo_usuario,
			sg.descripcion as grupo_usuario,
			com.piso as piso_comision,
			com.sin_0,
			com.min_cliente,
			iv.producto as id_producto,
			p.descripcion as producto,
			ed.obra_social as id_obra_social,
			os.nombre as obra_social,
			case
				when v.tipo_valor in (23,173)
				then round(-iv.monto,2)
				else round(iv.monto,2)
			end as monto_venta,			-- monto con descuentos aplicados
			case
				when ed.entidad_agrupadora = ( :no_comision	) or ed.obra_social = (	:no_comision ) -- 103500000000017786
				then 0
				else (
					select 
						round(cg2.porcentaje_descuento,2) as comision
					from contrato c2 
					inner join contrato_comision cc2 on c2.id = cc2.contrato 
					inner join seg_grupo sg2 on sg2.grupo = cc2.seg_grupo and sg2.grupo = ed.grupo_usuario
					inner join contrato_grupo cg2 on cg2.contrato = c2.id
					inner join grupo g2 on g2.id = cg2.grupo 
					inner join grupo_producto gp2 on gp2.grupo = g2.id 
					inner join producto p2 on p2.id = gp2.producto and p2.id = iv.producto
					inner join nodo n2 on n2.id = c2.nodo_creacion and n2.id in (select id from nodo where codigo_jerarquico ilike '000.001.080%')
					order by cc2.prioridad, cg2.prioridad, cg2.rechazo 
					limit 1
				) 
			end as pc
		from (
			------------------
			-- esqueleto datos
			select 
				v.id as valor,
				v2.id as comprobante,
				c.id as cliente,
				e.id as entidad_agrupadora,
				su.usuario,
				iv.id as item_valor,
				(
					select 
						cc.seg_grupo
					from (
						select 
							cc.seg_grupo, cc.prioridad  
						from contrato_comision cc
						inner join seg_grupo_usuario sgu on cc.seg_grupo = sgu.grupo 
						where sgu.usuario = su.usuario 
						group by 1,2 order by 2 asc	-- cuando alguien tiene mas de un grupo comisionable, usa el que tenga la prioridad con num más bajo 
					) cc
					limit 1
				) as grupo_usuario,
				(
					select 
						pos.obra_social 
					from valor v2
					inner join entidad e2 on e2.id = v.entidad
					inner join plan_obra_social pos on pos.id = e2.id 
					where v2.comprobante = v.id
					and v2.tipo_valor = 2
					group by 1
				) as obra_social
			from valor v 
			inner join item_valor iv on iv.valor = v.id 
			inner join seg_usuario su on su.usuario = v.usuario_creacion 
			inner join cliente c on c.id = v.entidad
			inner join entidad e on c.entidad_agrupadora = e.id 
			left join valor v2 on v2.id = v.comprobante 
			where v.tipo_valor in (7, 16, 196, 197, 23, 173)
			and v.estado in (1,4,11) 
			and v.nodo_creacion in (
				select id 
				from nodo 
				where codigo_jerarquico ilike '000.001.080.%' 
				and length(codigo_jerarquico) = 19 
				and nombre not ilike all(array['%-deposito', 'compras %'])
			)
			and su.alias = :alias					-- selecciona alias
			and case								-- selecciona fecha desde
				when :desde is not null 
				then v.fecha_creacion >= :desde
				else 1=1
			end
			and case								-- selecciona fecha hasta
				when :hasta is not null
				then date(v.fecha_creacion) <= :hasta
				else 1=1
			end
			------------------
		) ed 
		inner join valor v on ed.valor = v.id
		inner join tipo_valor tv on v.tipo_valor = tv.id 
		inner join cliente c on ed.cliente = c.id 
		inner join seg_usuario su on ed.usuario = su.usuario 
		inner join item_valor iv on iv.id = ed.item_valor
		inner join seg_grupo sg on ed.grupo_usuario = sg.grupo 
		inner join producto p on iv.producto = p.id 
		inner join entidad ea on ed.entidad_agrupadora = ea.id
		inner join piso_comision com on sg.grupo = com.id
		left join entidad os on ed.obra_social = os.id			-- left porque no necesariamente tiene obra social
		left join valor comp on ed.comprobante = comp.id 		-- left porque no necesariamente hay un comprobante relacionado
		-----------------------
	) de
	-- where pc = 1
	-----------------------