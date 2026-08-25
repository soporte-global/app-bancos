# Relevamiento productivo: tablas de bancos

Fecha de consulta: 2026-08-25. Base: PostgreSQL `ftweb`, esquema activo `public`. Método: lectura de catálogo (`information_schema` y `pg_catalog`) y conteos `COUNT(*)`; no se leyeron filas de negocio ni se ejecutó DDL o DML.

## Alcance y síntesis

Se encontraron 21 tablas cuyo nombre coincide con `bancos_*` o `bancos_mes_*`. El conjunto tiene dos dominios funcionales distintos:

- `bancos_guardado_*`: configuración de conciliación de cheques del proyecto BANCOS.
- `bancos_mes_*` y `bancos_mensajeria`: importación y tratamiento mensual de extractos.

También apareció `bancos_extracto_config`, una tabla pequeña con PK propia que no figura en el DDL del legado relevado. El SQL legado `model/querys/codigos.sql` la consulta para obtener el código de extracto por subtipo de valor y nodo/banco; sus otros usos encontrados están comentados. Debe confirmarse si esa consulta sigue formando parte del flujo productivo antes de reemplazarla.

El núcleo mensual acumula 979.308 movimientos, 977.476 asignaciones de usuario, 917.688 asignaciones de asiento y 935.700 exclusiones. Por lo tanto, cualquier migración debe diseñarse y probarse con volúmenes cercanos al millón de filas; no es una configuración menor.

## Inventario de producción

| Grupo | Tabla | Filas | Estructura observada | Integridad observada |
| --- | --- | ---: | --- | --- |
| configuración | `bancos_extracto_config` | 67 | `id bigint`, `banco bigint`, `subtipo_valor smallint`, `codigo varchar` | PK en `id`; no FK ni unicidad de regla |
| BANCOS | `bancos_guardado_datos` | 10 | `id`, `nombre`, `cuenta` (`varchar`) | sin PK, FK ni índice |
| BANCOS | `bancos_guardado_listabancos` | 490 | `banco`, `banco_id`, `id`, `cuenta` (`varchar`) | sin PK, FK ni índice |
| BANCOS | `bancos_guardado_listareglas` | 301 | `reglas_idsubtipo_valor`, `reglas_codigo_excel`, `reglas_debehaber`, `id`, `cuenta` (`varchar`) | sin PK, FK ni índice |
| BANCOS | `bancos_guardado_cuentasbanco` | 19 | `banco`, `banco_id`, `id_cuenta_contable`, `cuenta` (`varchar`) | sin PK, FK ni índice |
| BANCOS | `bancos_guardado_cuentasvalores` | 239 | `id_stv`, `id_cuenta_contable`, `cuenta` (`varchar`) | sin PK, FK ni índice |
| mensual / configuración | `bancos_mes_guardado_datos` | 87 | `id`, `nombre`, `cuenta` (`varchar`) | sin PK, FK ni índice |
| mensual / configuración | `bancos_mes_guardado_listabancos` | 1.611 | `banco`, `banco_id`, `id`, `cuenta` (`varchar`) | sin PK, FK ni índice |
| mensual / configuración | `bancos_mes_guardado_listareglas` | 4.673 | `reglas_idsubtipo_valor`, `reglas_codigo_excel`, `reglas_creditodebito`, `id`, `cuenta` (`varchar`), `validar boolean default false` | sin PK, FK ni índice |
| mensual / configuración | `bancos_mes_guardado_cuentasbanco` | 1.603 | `banco`, `banco_id`, `id_cuenta_contable`, `cuenta` (`varchar`) | sin PK, FK ni índice |
| mensual / configuración | `bancos_mes_guardado_cuentasvalores` | 4.673 | `id_stv`, `id_cuenta_contable`, `cuenta` (`varchar`) | sin PK, FK ni índice |
| mensual / período | `bancos_mes_periodos_cargados_cuenta` | 2.358 | `id_periodo varchar`, `num_mes integer`, `mes varchar`, `ano integer`, `total_movs integer`, `num_cuenta varchar`, `validado boolean default false` | `UNIQUE(id_periodo)` repetida 121 veces; sin PK ni FK |
| mensual / extracto | `bancos_mes_movimientos_cargados_periodo` | 979.308 | `id_movimiento varchar`, `id_periodo varchar`, `nodo`, `banco`, `num_cuenta`, `tipo_valor`, `referencia`, `observacion` (`varchar`); `fecha date`; `credito numeric`; `debito numeric`; `serial_seq bigint` con secuencia | sin PK, FK, índice ni unicidad |
| mensual / responsable | `bancos_mes_usuario_asignado_movimiento` | 977.476 | `id_movimiento varchar`, `id_usuario varchar`, `estado varchar` | `UNIQUE(id_movimiento)`; sin FK |
| mensual / valor | `bancos_mes_valor_asignado_movimiento` | 18.060 | `id_movimiento varchar`, `id_valor bigint`, `mult_valor boolean default false`, `monto_asignado double precision` | `UNIQUE(id_movimiento)` e índices sobre `id_valor`; sin FK |
| mensual / asiento existente | `bancos_mes_asiento_asignado_movimiento` | 917.688 | `id_movimiento varchar`, `id_asiento bigint`, `mult_asiento boolean default false`, `monto_asignado double precision`, `debita boolean`, `id_interno_asiento bigint` | `UNIQUE(id_movimiento)` y FK `id_asiento → asiento(id)`; cuatro índices |
| mensual / asiento nuevo | `bancos_mes_asientos_creados_movimiento` | 5.148 | `id_movimiento varchar`, `id_interno_asiento integer` con secuencia, `nombre varchar`, `numero bigint`, `fecha_creacion date`, `nodo_creacion`, `usuario_creacion`, `asiento_modelo` (`bigint`) | sin PK, FK ni índice |
| mensual / líneas de asiento nuevo | `bancos_mes_movimientos_creados_asiento` | 10.762 | `id_movimiento varchar`, `id_interno_asiento bigint`, `fecha date`, `nombre`, `cuenta`, `codigo_cuenta` (`varchar`), `numero bigint`, `haber`, `debe` (`numeric`), `cuenta_banco boolean` | sin PK, FK ni índice |
| mensual / reserva | `bancos_mes_exclusiones` | 935.700 | `id_movimiento varchar`, `id_asiento bigint`, `id_valor bigint`, `timestamp timestamp`, `id_interno_asiento bigint` | `UNIQUE(id_movimiento)`; FK a `asiento(id)` y `valor(id)`; cuatro índices |
| mensual / reglas de usuario | `bancos_mes_raau` | 8.919 | `id_subtipo varchar`, `id_usuario varchar`, `num_cuenta varchar`, `identificador varchar` | índice único `identificador`; sin PK ni FK |
| mensual / mensajes | `bancos_mensajeria` | 13 | `id_mensaje integer` con secuencia, `id_movimiento`, `id_usuario_emisor`, `mensaje` (`varchar`), `emisor_admin`, `leido_admin`, `leido_user`, `mensaje_de_sistema` (`integer`), tres timestamps | sin PK, FK ni índice |

Salvo donde se indica, las columnas son anulables. Los `varchar` no declaran longitud máxima.

## Relaciones que el modelo intenta expresar

```text
período cargado ──< movimiento de extracto ── 0..1 responsable / estado
                                          ├─ 0..1 valor ERP
                                          ├─ 0..1 asiento ERP
                                          ├─ 0..1 borrador de asiento + líneas
                                          ├─ 0..1 reserva/exclusión
                                          └─ 0..N mensajes
```

La mayoría de estas relaciones se codifica sólo mediante valores textuales repetidos de `id_movimiento`; no hay FK desde las tablas dependientes hacia `bancos_mes_movimientos_cargados_periodo`. Los únicos FKs encontrados son desde asignación/exclusión de asiento a `asiento(id)` y desde exclusiones a `valor(id)`.

## Hallazgos estructurales

1. **Identidad técnica incompleta.** Veinte de las 21 tablas no tienen PK. Algunas secuencias existen, pero sus columnas no están declaradas únicas ni se usan como PK.
2. **IDs y referencias incompatibles.** Identificadores de dominio y referencias a ERP se guardan con frecuencia como `varchar`, mientras que `valor.id` y `asiento.id` son `bigint`. Esto impide la integridad referencial y fuerza conversiones implícitas.
3. **Relaciones huérfanas posibles.** Asignaciones, mensajes, borradores y reservas no tienen FK al movimiento del extracto. Una eliminación o una importación repetida puede dejar registros sin padre.
4. **Modelo de importación débil.** El movimiento no tiene clave estable de origen ni una restricción que lo vincule de forma única con período y fila. `serial_seq` existe, pero no es clave. Esto concuerda con el riesgo ya identificado de que reordenar una importación altere asociaciones.
5. **Representación duplicada de configuración.** Las cinco tablas `bancos_guardado_*` y las cinco `bancos_mes_guardado_*` repiten la misma forma con nombres de columna muy similares. No hay restricciones que prevengan reglas, bancos o mapeos duplicados.
6. **Tipos monetarios inconsistentes.** Los importes de extracto y líneas nuevas usan `numeric`, pero los importes asignados usan `double precision`. Para contabilidad, los importes deben normalizarse a un `numeric(p,s)` acordado.
7. **Estados y banderas sin dominio.** `estado` es texto libre; `emisor_admin`, `leido_admin`, `leido_user` y `mensaje_de_sistema` son enteros anulables en lugar de booleanos o eventos fechados.
8. **Índices redundantes.** `bancos_mes_periodos_cargados_cuenta` contiene 121 restricciones e índices UNIQUE equivalentes sobre `id_periodo`. Es sobrecosto de escritura y mantenimiento sin beneficio funcional. `bancos_mes_valor_asignado_movimiento` tiene dos índices no únicos equivalentes sobre `id_valor`.
9. **Restricciones parciales.** `bancos_mes_asiento_asignado_movimiento`, `bancos_mes_usuario_asignado_movimiento`, `bancos_mes_valor_asignado_movimiento` y `bancos_mes_exclusiones` aplican unicidad por movimiento, pero no todos declaran el movimiento como FK ni PK. La semántica 1:1 sigue siendo frágil.
10. **Auditoría insuficiente.** No hay `created_at`, `updated_at`, operador real, origen de importación, versión ni trazabilidad consistente de cambios. El timestamp de exclusión no reemplaza un historial.

## Límites del relevamiento

Este documento describe el catálogo observado, no certifica la semántica contable ni la calidad de cada fila. Antes de definir una migración deben caracterizarse duplicados, nulos, huérfanos y reglas reales con consultas de calidad y ejemplos anonimizados. La propuesta de destino y el orden de esa validación están en [proposed-target-model.md](proposed-target-model.md).
