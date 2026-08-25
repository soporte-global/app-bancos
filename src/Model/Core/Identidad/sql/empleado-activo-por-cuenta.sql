select
    re.id,
    re.legajo,
    re.nombre,
    re.apellido,
    re.cuil,
    re.dni,
    re.empleado_farmacia,
    re.usuario_zweb,
    re.cliente,
    coalesce((
        select json_agg(
            json_build_object(
                'empresa_id', empresa.id,
                'empresa', empresa.nombre,
                'cuit', empresa.cuit,
                'nodo_id', empresa.nodo,
                'liquida', relacion.liquida,
                'numero_legajo_origen', relacion.numero_legajo_origen,
                'fecha_ingreso', relacion.fecha_ingreso
            )
            order by relacion.liquida desc, empresa.nombre, empresa.id
        )
        from global_prod.rrhh_empleados_empresa relacion
        inner join global_prod.rrhh_empresas empresa
            on empresa.id = relacion.empresa
           and empresa.fecha_eliminacion is null
        where relacion.empleado = re.id
          and relacion.fecha_eliminacion is null
    ), '[]'::json) as relaciones_laborales
from global_prod.rrhh_empleados re
where re.usuario = :cuenta
  and re.estado = 1
  and re.fecha_baja is null
order by re.id
limit 2;
