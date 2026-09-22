# Estrategia post-migración y adopción

La carga de datos a `global_prod.bancos_*` está terminada. La migración de datos y el corte funcional son hitos distintos: no se cambian lectores ni se habilitan escrituras ERP hasta completar y aprobar los controles siguientes.

1. **Conciliar el corte.** Guardar un informe reproducible que compare origen y destino por configuración, período, estado, cuenta, movimientos, crédito/débito, asociaciones, reservas, borradores, mensajes y trazabilidad. Cada omisión o diferencia debe estar identificada como regla de migración o incidente.
2. **Asegurar recuperación.** Verificar un respaldo restaurable de ambos estados y registrar versión de migraciones, fecha, responsable y resultado. Los rollbacks versionados sirven para una reversión decidida; no se usan para corregir diferencias puntuales durante la convivencia.
3. **Definir propiedad de escritura.** Durante la convivencia, el legado queda en sólo lectura para el alcance migrado y la nueva aplicación será el único escritor de `global_prod.bancos_*`. Las correcciones se hacen con una migración auditable, nunca editando filas manualmente.
4. **Adoptar lecturas gradualmente.** Implementar repositorios de lectura, paginación por cursor y comparación en sombra contra el legado. Cambiar la consulta principal sólo después de validar paridad en períodos representativos y en los casos excepcionales documentados.
5. **Habilitar operación asistida.** Configurar el id y los permisos de Hub, incluida la autorización de cierre; completar contratos API, auditoría e idempotencia. La preparación de movimientos continúa sin efectos ERP.
6. **Habilitar escritura ERP al final.** Precisar fusión/reversa/generación de asiento, validar [query-plan.md](database/query-plan.md) con `EXPLAIN (ANALYZE, BUFFERS)` y ejecutar pruebas de integración y concurrencia de reservas. Sólo entonces se autoriza el flujo de cierre y efectos contables.

## Resultado de conciliación - 2026-09-22

El comando `app/cli/conciliacion_post_migracion.php` separa paridad del snapshot, deriva posterior y decisiones pendientes de corte. La traza original aprobó sus 16 controles y, después de los incrementales y `045`, el estado global es `APROBADO`. La evidencia puntual está archivada en [conciliacion-post-migracion-2026-09-22.md](migration/evidence/conciliacion-post-migracion-2026-09-22.md).

No se repitieron destructivamente las migraciones originales. El incremental idempotente avanzó desde la frontera persistida y `045` formalizó los cuatro tratamientos de cuenta. Antes de cambiar consumidores o habilitar DML productivo todavía debe establecerse una ventana con un único escritor y verificarse el respaldo restaurable.

El incremental quedó implementado como `app/cli/migrar_legacy_incremental.php`. Su modo predeterminado es sólo lectura; `--confirmar` exige una clave idempotente, toma un bloqueo global y ejecuta configuración, movimientos y dependencias por fases reanudables. `044` separa la traza `ORIGINAL` de los lotes `CATCHUP-*`. Los lotes `CATCHUP-370ADAA9731E3DA812764A85` y `CATCHUP-4C98F6200AA65FCBF8464A78` terminaron `OK`; este último incorporó 21 asociaciones/reservas surgidas durante la validación. La ventana de corte definitiva y la verificación del respaldo permanecen pendientes.
