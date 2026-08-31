# Base de datos

Los legados usan PostgreSQL `ftweb` y escriben en el ERP. BANCOS_MENSUAL añade `bancos_mes_*` y `bancos_mensajeria`; BANCOS añade `bancos_guardado_*`. SGUA y sus tablas no son fuente para la nueva identidad.

El relevamiento del catálogo productivo real, realizado el 2026-08-25 en modo de solo lectura, está en [legacy-production-schema.md](database/legacy-production-schema.md). Incluye las 21 tablas cuyo nombre coincide con `bancos_*` o `bancos_mes_*`, sus columnas, restricciones, índices y volúmenes al momento de la consulta.

La validación adicional de datos productivos, también en modo de solo lectura, está en [production-validation.md](database/production-validation.md). Aporta evidencia para las decisiones del modelo objetivo y separa los puntos que todavía requieren definición funcional.

El mapa de las entidades ERP que intervienen en los procesos bancarios está en [erp-structure.md](database/erp-structure.md). Documenta las claves y tipos reales que deben respetar las futuras referencias desde `global_prod.bancos_*`.

La propuesta que definió el reemplazo está en [proposed-target-model.md](database/proposed-target-model.md). Las tablas canónicas ya están en `global_prod`, conservan el prefijo `bancos_` y usan nombres en español. La carga fue finalizada; resta la conciliación y el corte controlado descritos en [migration-strategy.md](migration-strategy.md).

El plan de acceso para el modelo nuevo está en [query-plan.md](database/query-plan.md): define CTEs acotadas, joins por PK, paginación por cursor, reservas concurrentes y los índices que deben validarse con `EXPLAIN (ANALYZE, BUFFERS)`. La versión productiva relevada es PostgreSQL 9.6: las CTE se materializan y se usan para reducir conjuntos, no para encadenar tablas grandes.

La identidad y permisos nuevos deben consumir el Hub de `ftweb.global_prod`, en particular el catálogo y las concesiones `hub_*` y su vista de permisos efectivos. No se deben crear tablas de credenciales equivalentes a `login_users`.

El DDL heredado se ejecuta desde requests y no debe ser el diseño canónico. La migración debe partir del esquema productivo real, con integridad referencial, índices, unicidad, auditoría y migraciones reversibles cuando sea posible.
