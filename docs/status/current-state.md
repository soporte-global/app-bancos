# Estado actual

Descubrimiento estático completado para BANCOS, GESTION_USUARIOS y BANCOS_MENSUAL, más las referencias `nueva_app` y `hQuery`. El 2026-08-25 se completó además un relevamiento y una validación agregada de solo lectura del catálogo productivo de las tablas `bancos_*`, `bancos_mes_*` y entidades ERP relacionadas; la evidencia y el modelo objetivo actualizado se documentaron en `docs/database/`. Las tablas canónicas se encuentran en el esquema `global_prod`, con prefijo `bancos_` y nombres en español.

También se incorporó la base de estructura de `nueva_app` dentro de `app-bancos` (directorios `_shared`, `app`, `src`, `lib`, `vendor`, `tests`, configuración de bootstrap y entrypoints), sin modificar los legados ni sobreescribir documentación preexistente.

Alcance confirmado: BANCOS concilia cheques; BANCOS_MENSUAL gestiona extractos mensuales, asignación, asociación y cierre. La futura identidad/permisos se basará en `nueva_app` y las funciones JavaScript comunes en hQuery; SGUA no se adopta como integración.

La carga completa hacia esas tablas canónicas ya fue realizada mediante las migraciones `007` a `013`: preparación de trazabilidad, ajustes de modelo, configuración, períodos/importaciones, movimientos/historial, dependencias históricas y asientos compartidos. El sistema está en etapa post-migración: falta conciliar formalmente el corte y adoptar el destino de manera gradual; no se debe inferir que ya están habilitadas las operaciones funcionales ni las escrituras ERP.

Se definió además la estructura objetivo de implementación como un monolito modular con módulos de extractos mensuales y conciliación de cheques, casos de uso separados, repositorios de lectura/escritura y gateway ERP. El próximo incremento implementa primero repositorios de lectura y comparación en sombra. El plan de consultas incorpora CTEs acotadas para PostgreSQL 9.6, joins por PK, paginación por cursor, reservas atómicas e índices a medir; está documentado en `docs/database/query-plan.md`.

Se confirmaron las reglas iniciales de estados, asociaciones, reversa/fusión y retención: cualquier usuario autorizado prepara un movimiento, el cierre requiere administración o permiso especial, un cerrado es terminal, la preparación no modifica ERP y el histórico se conserva completo. Quedan por especificar el permiso concreto de Hub, validaciones adicionales de preparación y la secuencia contable final de fusión/generación de asiento.
