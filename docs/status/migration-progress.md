# Progreso de migración

Fase actual: **post-migración y transición controlada**. Según el cierre informado, los datos legacy ya fueron cargados en las tablas canónicas `global_prod.bancos_*`. El alcance incluye configuración (`010`), períodos, importaciones, movimientos e historial (`011`), asociaciones, reservas, borradores y mensajes (`012`), además de la semántica de asientos compartidos (`013`).

La carga no equivale todavía a un corte de aplicación. Antes de cambiar consumidores se debe conservar la evidencia de conciliación y confirmar que los datos de destino, las trazas de origen y las excepciones documentadas coinciden con el legado.

El sandbox de depuración quedó estructuralmente validado: `014_bancos_preparar_debug_global_temp.sql` fue aplicado el 2026-09-04 y el 2026-09-07 se confirmó que los defaults de `global_temp.bancos_*` no usan secuencias de `public` ni `global_prod`. `015_bancos_debug_fixtures.sql` cargó un lote mínimo de cinco movimientos. El 2026-09-08, `016_bancos_debug_cargar_lote_sombra_2026_07.sql` cargó desde `global_prod` un lote acotado de 51 movimientos y 47 asociaciones, con rollback y sin DML sobre producción. La lectura se expone como pantalla interna `?pag=bandeja-mensual`, con cursor firmado vinculado a cuenta y período. `CompararLoteSombra016Test.php` verificó su paridad con `global_prod` y el legado; la próxima iteración amplía la cobertura a casos funcionales representativos.

## Siguientes pasos, en orden

1. Provisionar el usuario de debug con escritura exclusiva en `global_temp`.
2. Comparar esa consulta en sombra con el legado, incluyendo períodos representativos y excepciones documentadas; no habilitar escrituras ERP ni automatismos.
3. Ejecutar y archivar la conciliación de corte: conteos por tabla/período/estado, sumas de crédito y débito, asociaciones y reservas, mensajes y borradores; verificar además PK/FK, restricciones, trazas `bancos_migracion_*` y excepciones/omisiones esperadas.
4. Tomar o verificar un respaldo recuperable de origen y destino, registrar versión, fecha, responsable y resultado de cada control. No ejecutar rollbacks de `010` a `013` una vez iniciado el corte sin una decisión explícita de reversión.
5. Definir la convivencia: el legado queda sólo lectura para el alcance migrado, la aplicación nueva es el único escritor de `global_prod.bancos_*`, y toda discrepancia se corrige mediante migración/versionado, no mediante edición manual.
6. Configurar en Hub el permiso de cierre y completar las reglas contables de fusión/generación. Luego validar el plan de consultas con `EXPLAIN (ANALYZE, BUFFERS)` y cubrir integración, idempotencia y concurrencia antes de habilitar cualquier escritura ERP.
