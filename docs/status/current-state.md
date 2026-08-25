# Estado actual

Descubrimiento estático completado para BANCOS, GESTION_USUARIOS y BANCOS_MENSUAL, más las referencias `nueva_app` y `hQuery`. El 2026-08-25 se completó además un relevamiento y una validación agregada de solo lectura del catálogo productivo de las tablas `bancos_*`, `bancos_mes_*` y entidades ERP relacionadas; la evidencia y el modelo objetivo actualizado se documentaron en `docs/database/`. Las tablas nuevas se definirán en el esquema `global_prod`, con prefijo `bancos_` y nombres en español.

También se incorporó la base de estructura de `nueva_app` dentro de `app-bancos` (directorios `_shared`, `app`, `src`, `lib`, `vendor`, `tests`, configuración de bootstrap y entrypoints), sin modificar los legados ni sobreescribir documentación preexistente.

Alcance confirmado: BANCOS concilia cheques; BANCOS_MENSUAL gestiona extractos mensuales, asignación, asociación y cierre. La futura identidad/permisos se basará en `nueva_app` y las funciones JavaScript comunes en hQuery; SGUA no se adopta como integración.

También se avanzó con el primer paquete de DDL de dominio en `global_prod`: `app/sql/migraciones/004_bancos_tablas_auxiliares.sql`, que define las tablas auxiliares canónicas (configuración, importación de extracto, movimientos, historial, asociaciones, reservas, mensajes y conciliación de cheque), aplicado exitosamente en `ftweb` (`localhost:5500`) para dejar materializada la base del siguiente ciclo funcional.
