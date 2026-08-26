# Backlog

- Validar estados, cierre y reversa de BANCOS_MENSUAL.
- Confirmar reglas contables y tratamiento de excepciones de cheques.
- Configurar la aplicación, roles y permisos internos en el Hub de `nueva_app`.
- Elegir y fijar una versión publicada de hQuery, validando sus dependencias y contrato `vars`.
- Caracterizar los 38 importes negativos, las asociaciones sin exclusión y los IDs de movimiento duplicados con ejemplos anonimizados, antes de definir el backfill. La validación agregada de claves, nulos y huérfanos ya fue completada.
- Acordar con Contabilidad las transiciones de estado, asociaciones múltiples de asiento, reversas/fusiones, retención y criterio de redondeo/moneda.
- Diseñar migraciones y contratos API antes de toda escritura ERP.
- Validar con `EXPLAIN (ANALYZE, BUFFERS)` el plan de CTEs e índices propuestos sobre volumen representativo, antes de crear índices adicionales o habilitar autoasignación.
- Definir con DBA el eventual índice compuesto de `public.valor` y la disponibilidad de extensiones para búsqueda textual; no alterar tablas ERP desde este módulo sin medición y aprobación.
- Configurar permisos de aplicación/sesión en Hub y definir `id_aplicacion` en la nueva base.
- Ejecutar revisión de la migración `004_bancos_tablas_auxiliares.sql` con negocio/TI para validar restricciones antes de la migración de producción.
