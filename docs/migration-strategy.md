# Estrategia post-migración y adopción

La carga de datos a `global_prod.bancos_*` está terminada. La migración de datos y el corte funcional son hitos distintos: no se cambian lectores ni se habilitan escrituras ERP hasta completar y aprobar los controles siguientes.

1. **Conciliar el corte.** Guardar un informe reproducible que compare origen y destino por configuración, período, estado, cuenta, movimientos, crédito/débito, asociaciones, reservas, borradores, mensajes y trazabilidad. Cada omisión o diferencia debe estar identificada como regla de migración o incidente.
2. **Asegurar recuperación.** Verificar un respaldo restaurable de ambos estados y registrar versión de migraciones, fecha, responsable y resultado. Los rollbacks versionados sirven para una reversión decidida; no se usan para corregir diferencias puntuales durante la convivencia.
3. **Definir propiedad de escritura.** Durante la convivencia, el legado queda en sólo lectura para el alcance migrado y la nueva aplicación será el único escritor de `global_prod.bancos_*`. Las correcciones se hacen con una migración auditable, nunca editando filas manualmente.
4. **Adoptar lecturas gradualmente.** Implementar repositorios de lectura, paginación por cursor y comparación en sombra contra el legado. Cambiar la consulta principal sólo después de validar paridad en períodos representativos y en los casos excepcionales documentados.
5. **Habilitar operación asistida.** Configurar el id y los permisos de Hub, incluida la autorización de cierre; completar contratos API, auditoría e idempotencia. La preparación de movimientos continúa sin efectos ERP.
6. **Habilitar escritura ERP al final.** Precisar fusión/reversa/generación de asiento, validar [query-plan.md](database/query-plan.md) con `EXPLAIN (ANALYZE, BUFFERS)` y ejecutar pruebas de integración y concurrencia de reservas. Sólo entonces se autoriza el flujo de cierre y efectos contables.
