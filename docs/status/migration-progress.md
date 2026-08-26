# Progreso de migración

Fase actual: habilitación estructural + primer DDL funcional. Se incorporó la base de `nueva_app` en `app-bancos` y se generó la migración auxiliar inicial en `app/sql/migraciones/004_bancos_tablas_auxiliares.sql` con tablas canónicas de configuración, importación, movimientos, asociaciones, borradores, mensajes, reservas y conciliación en `global_prod`. También se agregó `app/sql/migraciones/004_bancos_tablas_auxiliares_rollback.sql` para reversión.

Se ejecutó luego `app/sql/migraciones/005_bancos_campos_zetti.sql` para renombrar a `zetti` los campos que antes referenciaban `erp` dentro del dominio (`*_zetti_*`) y se validó que no quedaron columnas ni constraints residuales con prefijo `erp` en `global_prod.bancos_*`.
Luego se ejecutó `app/sql/migraciones/006_bancos_campos_timestamps_zetti.sql` para unificar nombres de auditoría en `global_prod.bancos_*`: `fecha_creacion`, `fecha_modificacion`, `usuario_creacion` y `usuario_modificacion`.

Estado de ejecución desde este entorno: aplicada `004` el 2026-08-25 en `ftweb` (`localhost:5500`) y `005` y `006` el 2026-08-26 en el mismo entorno, ambas con PHP CLI + `pdo_pgsql`.

Verificación siguiente sugerida: correr nuevamente checklist de presencia de tablas e índices en `global_prod` antes de comenzar el siguiente paquete funcional. Antes de crear índices adicionales o habilitar autoasignación, validar el plan de CTEs, joins y paginación definido en `docs/database/query-plan.md` con `EXPLAIN (ANALYZE, BUFFERS)` sobre volumen representativo.
