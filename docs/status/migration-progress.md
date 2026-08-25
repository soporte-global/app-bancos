# Progreso de migración

Fase actual: habilitación estructural + primer DDL funcional. Se incorporó la base de `nueva_app` en `app-bancos` y se generó la migración auxiliar inicial en `app/sql/migraciones/004_bancos_tablas_auxiliares.sql` con tablas canónicas de configuración, importación, movimientos, asociaciones, borradores, mensajes, reservas y conciliación en `global_prod`. También se agregó `app/sql/migraciones/004_bancos_tablas_auxiliares_rollback.sql` para reversión.

Estado de ejecución desde este entorno: aplicada el 2026-08-25 en `ftweb` conectado como `postgres` sobre `localhost:5500` (WAMP con PostgreSQL activo), con SQL ejecutado por PHP CLI con `pdo_pgsql`.

Verificación siguiente sugerida: correr nuevamente checklist de presencia de tablas e índices en `global_prod` antes de comenzar el siguiente paquete funcional.
