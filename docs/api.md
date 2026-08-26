# API: estado de descubrimiento

Los legados usan archivos PHP, formularios y AJAX, sin API formal. Las operaciones críticas son importación de período, reglas, asignación/estado, mensajería, asociación de valores/asientos, creación/fusión y cierre.

La API nueva debe autenticar y autorizar en servidor, validar entradas, preservar idempotencia cuando corresponda y no confiar en `idu` enviado por POST ni en rutas PHP heredadas. Debe integrarse al flujo de `nueva_app`: sesión opaca, `PoliticaAcceso` por aplicación y permiso interno por destino, antes de ejecutar carga o reglas de negocio.

Los contratos se separarán por caso de uso: consulta/paginación de extractos, importación, candidatos, reserva/asociación, mensajería, cambio de estado/cierre y conciliación de cheque. Las listas reciben `cuenta_bancaria_id`, `inicio_periodo`, filtros permitidos y cursor opaco; no aceptan fragmentos SQL ni orden arbitrario. Las operaciones que escriben reciben una clave de idempotencia y devuelven el identificador interno, el estado y la evidencia de auditoría. El plan de consultas que respalda esos contratos está en [query-plan.md](database/query-plan.md).
