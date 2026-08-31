# Progreso de migración

Fase actual: **post-migración y transición controlada**. Según el cierre informado, los datos legacy ya fueron cargados en las tablas canónicas `global_prod.bancos_*`. El alcance incluye configuración (`010`), períodos, importaciones, movimientos e historial (`011`), asociaciones, reservas, borradores y mensajes (`012`), además de la semántica de asientos compartidos (`013`).

La carga no equivale todavía a un corte de aplicación. Antes de cambiar consumidores se debe conservar la evidencia de conciliación y confirmar que los datos de destino, las trazas de origen y las excepciones documentadas coinciden con el legado.

## Siguientes pasos, en orden

1. Ejecutar y archivar la conciliación de corte: conteos por tabla/período/estado, sumas de crédito y débito, asociaciones y reservas, mensajes y borradores; verificar además PK/FK, restricciones, trazas `bancos_migracion_*` y excepciones/omisiones esperadas.
2. Tomar o verificar un respaldo recuperable de origen y destino, registrar versión, fecha, responsable y resultado de cada control. No ejecutar rollbacks de `010` a `013` una vez iniciado el corte sin una decisión explícita de reversión.
3. Definir la convivencia: el legado queda sólo lectura para el alcance migrado, la aplicación nueva es el único escritor de `global_prod.bancos_*`, y toda discrepancia se corrige mediante migración/versionado, no mediante edición manual.
4. Implementar lectura en sombra y luego consulta paginada sobre el destino; comparar resultados con el legado en períodos representativos antes de cambiar la pantalla principal.
5. Configurar en Hub el permiso de cierre y completar las reglas contables de fusión/generación. Luego validar el plan de consultas con `EXPLAIN (ANALYZE, BUFFERS)` y cubrir integración, idempotencia y concurrencia antes de habilitar cualquier escritura ERP.
