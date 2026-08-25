SELECT
    re.id AS id_legajo,
    re.legajo,
    re.nombre,
    re.apellido,
    su.mail,
    NULL::text AS direccion,
    re.cuil,
    re.dni,
    COALESCE(rl.usuario, su.alias) AS alias,
    CASE su.estado
        WHEN 1 THEN 'Activo'
        WHEN 0 THEN 'Inactivo'
        ELSE NULL
    END AS estado_zweb,
    re.cliente AS cliente_zweb,
    NULL::numeric AS ultimo_saldo,
    NULL::timestamp AS fecha_ultimo_saldo,
    re.empleado_farmacia,
    re.usuario_zweb,
    re.usuario,
    re.estado,
    re.criterio_cliente,
    COALESCE((
        SELECT json_agg(
            json_build_object(
                'id', empresa.id,
                'nombre', empresa.nombre,
                'cuit', empresa.cuit,
                'nodo', empresa.nodo,
                'liquida', relacion.liquida,
                'numero_legajo_origen', relacion.numero_legajo_origen,
                'fecha_ingreso', relacion.fecha_ingreso
            )
            ORDER BY relacion.liquida DESC, empresa.nombre
        )
        FROM global_prod.rrhh_empleados_empresa relacion
        INNER JOIN global_prod.rrhh_empresas empresa
            ON empresa.id = relacion.empresa
           AND empresa.fecha_eliminacion IS NULL
        WHERE relacion.empleado = re.id
          AND relacion.fecha_eliminacion IS NULL
    ), '[]'::json) AS empresas
FROM global_prod.rrhh_empleados re
LEFT JOIN global_prod.rrhh_login rl
    ON rl.id = re.usuario
   AND rl.habilitado
   AND rl.fecha_eliminacion IS NULL
LEFT JOIN public.seg_usuario su
    ON su.usuario = re.usuario_zweb
WHERE (
      re.legajo = :numLegajo
      OR (
          NOT EXISTS (
              SELECT 1
              FROM global_prod.rrhh_empleados por_legajo
              WHERE por_legajo.legajo = :numLegajo
          )
          AND re.id::text = :numLegajo
      )
  )
ORDER BY CASE WHEN re.legajo = :numLegajo THEN 0 ELSE 1 END, re.id
LIMIT 2;
