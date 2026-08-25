select 
	pc.codigo
from producto p 
inner join producto_codigo pc on pc.producto = p.id and p.id = :idProd 
where pc.tipo_codigo = 1
and pc.codigo <> :codPrincipal;