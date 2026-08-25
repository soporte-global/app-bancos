select
    rl.id,
    rl.usuario as username,
    rl.usuario as alias,
    rl.password_hash
from global_prod.rrhh_login rl
where lower(rl.usuario) = lower(:username)
  and rl.habilitado is true
  and rl.fecha_eliminacion is null
order by rl.id
limit 2;
