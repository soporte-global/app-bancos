----------------------------
-- agrupado por usuario y pc			
select 
	a.id_usuario,   		-- para este usuario
	a.usuario,
	a.alias,
	a.id_grupo_usuario,		-- perteneciente a este grupo
	a.grupo_usuario,
	a.piso_comision,		-- con estas reglas
	a.sin_0,
	a.min_cliente,
	a.pc,					-- dividido por porcentajes comisionables:
	sum(a.monto_venta) as total_vendido,
	sum(a.comision_producto) as total_comisionado,
    COUNT(DISTINCT a.id_valor) - COUNT(DISTINCT CASE WHEN a.id_tipo_valor IN (23, 173) THEN a.id_valor END) AS total_ventas,
    COUNT(DISTINCT CASE WHEN a.id_cliente <> 83172 THEN a.id_valor END) - COUNT(DISTINCT CASE WHEN a.id_cliente <> 83172 AND a.id_tipo_valor IN (23, 173) THEN a.id_valor END) AS ventas_cliente
from (
    "$$QUERY_COMISIONES$$"
)a
group by 1,2,3,4,5,6,7,8,9
order by 9
----------------------------
