select 
	u.id as id_user,
	u.username as alias,
	u.password as hash,
    CASE 
        WHEN u.rol_id = 1
        THEN TRUE
        ELSE FALSE
    END AS admin_rrhh, 
    -- hug.grupo as grupo_usuario,
    case 
        when hg.id is null 
        then 2
        else hg.id
    end as id_grupo_usuario,
    case 
        when hg.id is null 
        then (select nombre from hub_grupos where id = 2)
        else hg.nombre
    end as grupo_usuario,
    hg.zweb_predeterminado,
    u.nombre,
    u.apellido,
    u.email as mail
from usuarios u
left join hub_usuarios_grupo hug on hug.usuario = u.id 
left join hub_grupos hg on hg.id = hug.grupo 
where deleted_at is null
and u.username like :alias;