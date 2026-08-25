select
    rl.id,
    rl.usuario as username,
    rl.usuario as alias
from global_prod.rrhh_login rl
where rl.id = :cuenta
  and rl.habilitado is true
  and rl.fecha_eliminacion is null
limit 2;
