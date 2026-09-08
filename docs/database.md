# Base de datos

Los legados usan PostgreSQL `ftweb` y escriben en el ERP. BANCOS_MENSUAL añade `bancos_mes_*` y `bancos_mensajeria`; BANCOS añade `bancos_guardado_*`. SGUA y sus tablas no son fuente para la nueva identidad.

El relevamiento del catálogo productivo real, realizado el 2026-08-25 en modo de solo lectura, está en [legacy-production-schema.md](database/legacy-production-schema.md). Incluye las 21 tablas cuyo nombre coincide con `bancos_*` o `bancos_mes_*`, sus columnas, restricciones, índices y volúmenes al momento de la consulta.

La validación adicional de datos productivos, también en modo de solo lectura, está en [production-validation.md](database/production-validation.md). Aporta evidencia para las decisiones del modelo objetivo y separa los puntos que todavía requieren definición funcional.

El mapa de las entidades ERP que intervienen en los procesos bancarios está en [erp-structure.md](database/erp-structure.md). Documenta las claves y tipos reales que deben respetar las futuras referencias desde `global_prod.bancos_*`.

La propuesta que definió el reemplazo está en [proposed-target-model.md](database/proposed-target-model.md). Las tablas canónicas ya están en `global_prod`, conservan el prefijo `bancos_` y usan nombres en español. La carga fue finalizada; resta la conciliación y el corte controlado descritos en [migration-strategy.md](migration-strategy.md).

El plan de acceso para el modelo nuevo está en [query-plan.md](database/query-plan.md): define CTEs acotadas, joins por PK, paginación por cursor, reservas concurrentes y los índices que deben validarse con `EXPLAIN (ANALYZE, BUFFERS)`. La versión productiva relevada es PostgreSQL 9.6: las CTE se materializan y se usan para reducir conjuntos, no para encadenar tablas grandes.

La identidad y permisos nuevos deben consumir el Hub de `ftweb.global_prod`, en particular el catálogo y las concesiones `hub_*` y su vista de permisos efectivos. No se deben crear tablas de credenciales equivalentes a `login_users`.

Para depurar sin escribir en producción se ejecutó el 2026-09-04 [014_bancos_preparar_debug_global_temp.sql](../app/sql/migraciones/014_bancos_preparar_debug_global_temp.sql). Creó en `global_temp` estructuras vacías equivalentes a las tablas operativas BANCOS y a los destinos ERP que pueden mutarse; se verificaron 49 tablas `bancos_*` y las cinco dependencias ERP que faltaban. El 2026-09-07 se confirmó en modo de solo lectura que ningún default de secuencia de `global_temp.bancos_*` apunta a `public` ni `global_prod`. Con `bancos_debug = true`, las lecturas ERP continúan en `public`, pero los `INSERT`/`UPDATE`/`DELETE` ERP y las tablas operativas BANCOS usan `global_temp`. El Hub no se replica: autenticación y permisos permanecen de sólo lectura en `global_prod`. Las migraciones `015` y `016` ya cargan fixtures y un lote real de sombra; la primera pantalla paginada está validada. Resta provisionar un usuario sin escritura sobre esquemas productivos y contrastar los resultados con el legado.

La migración [015_bancos_debug_fixtures.sql](../app/sql/migraciones/015_bancos_debug_fixtures.sql) cargó un lote idempotente de cinco movimientos en `global_temp` para el período `2026-09-01`. Sólo consulta `public.cuenta_bancaria` para satisfacer las FK ya existentes; no inserta, actualiza ni elimina filas de `public`. Ese lote respalda la prueba de integración de la primera bandeja paginada.

La migración [016_bancos_debug_cargar_lote_sombra_2026_07.sql](../app/sql/migraciones/016_bancos_debug_cargar_lote_sombra_2026_07.sql) se aplicó el 2026-09-08. Copia exclusivamente al sandbox la importación `35333` de `global_prod` (cuenta `103500000000515822`, período `2026-07-01`): 51 movimientos y 47 asociaciones a asientos ERP que se mantienen de sólo lectura en `public`. Reconstruye los IDs internos de `global_temp`, marca cada fila con `SOMBRA-016` y cuenta con [rollback](../app/sql/migraciones/016_bancos_debug_cargar_lote_sombra_2026_07_rollback.sql); no modifica `public` ni `global_prod`.

La prueba [CompararLoteSombra016Test.php](../tests/CompararLoteSombra016Test.php) verificó el 2026-09-08 la paridad de ese lote en tres tramos: movimientos y asociaciones entre `global_temp` y `global_prod`, y movimientos/asociaciones de asiento entre `global_prod` y las tablas legacy. La prueba es de sólo lectura y usa la traza de migración para resolver el período y la clave canónica de cada fila.

El DDL heredado se ejecuta desde requests y no debe ser el diseño canónico. La migración debe partir del esquema productivo real, con integridad referencial, índices, unicidad, auditoría y migraciones reversibles cuando sea posible.
