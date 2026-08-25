select 
	l.id
from usuarios u 
left join legajos l on u.id = l.usuario_id and l.activo = 1 and l.deleted_at is null
where u.username = :alias;