select 
	ce2.entidad -- 103500000000017786
from contrato c2 
inner join contrato_comision cc2 on c2.id = cc2.contrato 
inner join nodo n2 on n2.id = c2.nodo_creacion and n2.id in (select id from nodo where codigo_jerarquico ilike '000.001.080%')
inner join condicion cn2 on cn2.id = cc2.condicion 
inner join condicion_entidad ce2 on ce2.condicion = cn2.id 
order by cc2.prioridad, ce2.entidad
limit 1