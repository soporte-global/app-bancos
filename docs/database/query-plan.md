# Plan de consultas e índices

Estado: diseño de implementación. Fecha: 2026-08-26. Este documento no autoriza DDL ni cambios de datos en producción.

## Principios

- Todas las consultas se ejecutan con parámetros tipados; nunca se compone SQL desde filtros, ordenamientos o referencias recibidas por HTTP.
- El repositorio de lectura recibe un alcance reducido antes de unir tablas grandes: cuenta y período para extractos; cuenta, importe, fecha y subtipos para candidatos ERP.
- Se usan CTE para nombrar y reutilizar conjuntos intermedios pequeños (lote, movimiento, regla efectiva y candidatos). PostgreSQL 9.6 los materializa: no se encadenan CTE grandes ni se los usa como sustituto de índices. Cada CTE debe reducir filas o evitar recalcular una expresión; `EXPLAIN (ANALYZE, BUFFERS)` valida el plan sobre una copia representativa.
- Las consultas de pantalla son de sólo lectura y no ejecutan una operación contable. Las reservas, asociaciones, cierres y conciliaciones usan una transacción corta con bloqueo explícito e idempotencia.
- Se pagina por *keyset* (`fecha_operacion`, `id`) y no con `OFFSET` sobre períodos de hasta un millón de movimientos.

## Consultas canónicas

### Bandeja mensual paginada

Primero se reduce el lote por cuenta/período, luego los movimientos y finalmente se une sólo el último evento de asignación, la asociación vigente y el último mensaje. Los `LATERAL ... LIMIT 1` aprovechan índices por `movimiento_id` y evitan agrupar todo el historial de la base.

```sql
WITH lote AS (
    SELECT i.id
    FROM global_prod.bancos_importacion_extracto AS i
    WHERE i.cuenta_bancaria_zetti_id = :cuenta_bancaria_id
      AND i.inicio_periodo = :inicio_periodo
), pagina AS (
    SELECT m.id, m.fecha_operacion, m.referencia, m.descripcion,
           m.credito, m.debito, m.subtipo_valor_zetti_id
    FROM global_prod.bancos_movimiento_extracto AS m
    JOIN lote AS l ON l.id = m.importacion_id
    WHERE (:cursor_fecha IS NULL
           OR (m.fecha_operacion, m.id) > (:cursor_fecha, :cursor_id))
    ORDER BY m.fecha_operacion NULLS LAST, m.id
    LIMIT :limite
)
SELECT p.*, h.estado_id, h.usuario_hub_id,
       a.valor_zetti_id, a.asiento_zetti_id, a.borrador_asiento_id,
       ultimo_mensaje.emitido_en AS ultimo_mensaje_en
FROM pagina AS p
LEFT JOIN LATERAL (
    SELECT ha.estado_id, ha.usuario_hub_id
    FROM global_prod.bancos_historial_asignacion AS ha
    WHERE ha.movimiento_id = p.id
    ORDER BY ha.registrado_en DESC, ha.id DESC
    LIMIT 1
) AS h ON true
LEFT JOIN global_prod.bancos_asociacion_movimiento AS a
       ON a.movimiento_id = p.id AND a.activo
LEFT JOIN LATERAL (
    SELECT mm.emitido_en
    FROM global_prod.bancos_mensaje_movimiento AS mm
    WHERE mm.movimiento_id = p.id
    ORDER BY mm.emitido_en DESC, mm.id DESC
    LIMIT 1
) AS ultimo_mensaje ON true
ORDER BY p.fecha_operacion NULLS LAST, p.id;
```

El orden `NULLS LAST` debe coincidir exactamente con el índice de paginación. Si las fechas nulas son parte real del flujo, se fija su orden y cursor en el contrato API; no se usa `COALESCE` sobre la columna indexada.

### Candidatos de valor para asignación automática

La búsqueda parte de un único movimiento y resuelve la regla efectiva específica antes que la global. Luego filtra `public.valor` por columnas selectivas, excluye reservas activas y recién entonces calcula puntaje de referencia, importe y fecha. La coincidencia textual contiene `ILIKE '%...%'` no es indexable con B-tree; se ejecuta sólo sobre el conjunto previamente acotado. Un índice trigram sólo se evaluará si `EXPLAIN` demuestra que ese filtro domina el tiempo y la extensión está disponible.

```sql
WITH movimiento_objetivo AS (
    SELECT m.id, m.referencia, m.fecha_operacion,
           m.credito, m.debito, i.cuenta_bancaria_zetti_id, i.configuracion_id
    FROM global_prod.bancos_movimiento_extracto AS m
    JOIN global_prod.bancos_importacion_extracto AS i ON i.id = m.importacion_id
    WHERE m.id = :movimiento_id
), regla_efectiva AS (
    SELECT DISTINCT r.subtipo_valor_zetti_id
    FROM movimiento_objetivo AS mo
    JOIN global_prod.bancos_configuracion AS c ON c.id = mo.configuracion_id
    JOIN global_prod.bancos_regla_clasificacion AS r ON r.configuracion_id = c.id
    WHERE c.activo AND r.validar_automaticamente
    ORDER BY CASE c.alcance WHEN 'CUENTA' THEN 0 ELSE 1 END, r.id
), candidatos_base AS (
    SELECT v.id, v.monto_principal, v.referencia, v.fecha_emision,
           mo.id AS movimiento_id, mo.referencia AS referencia_extracto,
           mo.fecha_operacion,
           GREATEST(mo.credito, mo.debito) AS monto_extracto
    FROM movimiento_objetivo AS mo
    JOIN public.cuenta_bancaria AS cb ON cb.id = mo.cuenta_bancaria_zetti_id
    JOIN public.valor AS v ON v.entidad = cb.id
    JOIN regla_efectiva AS re ON re.subtipo_valor_zetti_id = v.subtipo_valor
    LEFT JOIN global_prod.bancos_reserva_recurso AS rr
           ON rr.valor_zetti_id = v.id AND rr.activo
    WHERE rr.id IS NULL
      AND v.estado <> :estado_conciliado_id
      AND v.monto_principal BETWEEN GREATEST(mo.credito, mo.debito) * :tolerancia_inferior
                              AND GREATEST(mo.credito, mo.debito) * :tolerancia_superior
)
SELECT cb.*,
       ((cb.monto_principal = cb.monto_extracto)::int
        + (cb.referencia ILIKE '%' || cb.referencia_extracto || '%')::int * 2
        + (cb.fecha_emision = cb.fecha_operacion)::int * 2) AS puntaje
FROM candidatos_base AS cb
ORDER BY puntaje DESC, cb.fecha_emision DESC NULLS LAST, cb.id
LIMIT :limite;
```

La fórmula y los estados son contratos de negocio pendientes de validación. La consulta sólo ilustra el límite de responsabilidades: el repositorio devuelve candidatos; el caso de uso decide si existe un único candidato suficiente para autoasignar.

### Reserva y asociación atómica

Para prevenir que dos operadores asignen el mismo valor, la escritura bloquea el movimiento y obtiene la reserva con `INSERT ... ON CONFLICT` sobre el índice único parcial. Un resultado vacío equivale a recurso ya reservado; no se reemplaza por una verificación previa susceptible a carrera.

```sql
WITH movimiento_bloqueado AS (
    SELECT id
    FROM global_prod.bancos_movimiento_extracto
    WHERE id = :movimiento_id
    FOR UPDATE
), reserva AS (
    INSERT INTO global_prod.bancos_reserva_recurso
        (movimiento_id, valor_zetti_id, reservado_por, reservado_hasta, activo)
    SELECT id, :valor_zetti_id, :usuario_hub_id, :reservado_hasta, true
    FROM movimiento_bloqueado
    ON CONFLICT (valor_zetti_id) WHERE activo AND valor_zetti_id IS NOT NULL
    DO NOTHING
    RETURNING id, movimiento_id
)
INSERT INTO global_prod.bancos_asociacion_movimiento
    (movimiento_id, valor_zetti_id, monto_asociado, usuario_creacion, activo)
SELECT movimiento_id, :valor_zetti_id, :monto_asociado, :usuario_hub_id, true
FROM reserva
RETURNING id;
```

La transacción debe revertirse si no se inserta asociación o si falla la transición de estado. Para asientos y borradores se repite el mismo patrón cambiando el recurso y su índice parcial. Un movimiento puede conservar filas activas para valor y asiento a la vez; los límites de una asociación de cada tipo por movimiento se validan en la transacción y se reforzarán con índices parciales específicos en una migración posterior. Preparar esas filas para `PARA_CERRAR` no escribe ERP. La conciliación de cheque agrega la clave de idempotencia antes de escribir en ERP y guarda todos los IDs resultantes en la misma transacción cuando ambos esquemas comparten conexión.

## Índices propuestos por acceso

| Acceso | Índice nuevo propuesto | Justificación |
| --- | --- | --- |
| resolver lote por cuenta/período | `bancos_importacion_extracto(cuenta_bancaria_zetti_id, inicio_periodo, id)` | cubre la primera CTE y el join hacia movimientos; la restricción de idempotencia no cubre búsquedas sin hash/versión. |
| paginar movimientos del lote | `bancos_movimiento_extracto(importacion_id, fecha_operacion NULLS LAST, id)` | filtra por lote y entrega la página en orden estable sin ordenar toda la importación. |
| último estado/responsable | `bancos_historial_asignacion(movimiento_id, registrado_en DESC, id DESC) INCLUDE (estado_id, usuario_hub_id)` | resuelve el `LATERAL ... LIMIT 1`; `INCLUDE` requiere PostgreSQL 11+, por lo que en 9.6 se omiten las columnas incluidas. |
| asociación vigente por movimiento | `bancos_asociacion_movimiento(movimiento_id) WHERE activo` | evita escaneo al armar la bandeja y complementa los únicos por recurso. |
| límite por tipo de asociación | índices únicos parciales sobre `(movimiento_id)` para `activo AND valor_zetti_id IS NOT NULL`, `activo AND asiento_zetti_id IS NOT NULL` y `activo AND borrador_asiento_id IS NOT NULL` | permite valor más asiento, pero limita a uno de cada tipo por movimiento según la regla confirmada. |
| último mensaje | `bancos_mensaje_movimiento(movimiento_id, emitido_en DESC, id DESC)` | resuelve conversación y última actividad por movimiento. |
| mensajes sin leer del receptor | `bancos_recepcion_mensaje(receptor_hub_id, mensaje_id) WHERE leido_en IS NULL` | permite contador sin recorrer lecturas ya confirmadas. |
| candidato ERP por entidad/subtipo/estado | validar con DBA un índice compuesto en `public.valor(entidad, subtipo_valor, estado, monto_principal)` | las tablas ERP no se alteran desde esta app; se mide selectividad y costo de escritura antes de proponer una migración ERP. |
| reserva de asiento/borrador por pantalla | `bancos_reserva_recurso(movimiento_id) WHERE activo` | complementa los únicos parciales por valor/asiento y evita consultas de disponibilidad por tabla completa. |

No se crean índices separados para prefijos ya cubiertos por un índice compuesto, ni índices de baja selectividad como `activo` o `estado_id` solos. Antes de agregar cada índice se registra: consulta, cardinalidad real, `EXPLAIN (ANALYZE, BUFFERS)` previo/posterior, tamaño, costo de escritura y plan de rollback. En PostgreSQL 9.6 no existe `CREATE INDEX CONCURRENTLY IF NOT EXISTS`; el despliegue se prepara fuera de una transacción y con nombre único, después de validar la sintaxis/versionado con DBA.

## Límites de los joins

- `INNER JOIN` se usa sólo para relaciones obligatorias y filtros que deben excluir la fila; `LEFT JOIN` preserva movimientos sin responsable, asociación o mensajes.
- Nunca se une directamente una importación completa con todas las filas de `valor`, `asiento` o `movimiento` ERP. La CTE inicial acota por PK/lote y los filtros indexables se aplican antes de la coincidencia textual o del cálculo de puntaje.
- La asociación, reserva y evento se unen por PK interna `movimiento_id`; `id_movimiento` heredado no participa en consultas nuevas. Para backfill se resuelve la correspondencia por `(id_periodo, serial_seq)`.
- Las consultas de detalle agrupan líneas de borrador después de seleccionar el borrador por `movimiento_id`; no agregan las líneas de todos los borradores del período.

## Validación operativa

1. Cargar un volumen representativo, incluyendo el orden de magnitud de 979.308 movimientos observado.
2. Ejecutar cada consulta con parámetros de períodos densos y poco densos; conservar `EXPLAIN (ANALYZE, BUFFERS)` y tiempo p95.
3. Probar dos solicitudes concurrentes sobre el mismo valor/asiento y verificar una única reserva/asociación activa.
4. Comparar conteos y resultados con el legado en casos caracterizados antes de habilitar autoasignación o escritura ERP.
5. Revisar trimestralmente los índices con `pg_stat_user_indexes` y eliminar sólo mediante migración los que no demuestren uso.
