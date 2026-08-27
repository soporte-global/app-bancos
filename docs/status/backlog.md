# Backlog

- Confirmar reglas contables y tratamiento de excepciones de cheques.
- Configurar la aplicación, roles y permisos internos en el Hub de `nueva_app`.
- Elegir y fijar una versión publicada de hQuery, validando sus dependencias y contrato `vars`.
- Completar el mapa manual de las cuentas legacy cuyo formato no coincide exactamente con `entidad.codigo`, antes de ejecutar el backfill. La preparación está en la migración 007.
- Detallar las validaciones futuras para `PARA_CERRAR`, el permiso especial de cierre en Hub y la secuencia contable definitiva de fusión/generación de asiento.
- Diseñar migraciones y contratos API antes de toda escritura ERP.
- Validar con `EXPLAIN (ANALYZE, BUFFERS)` el plan de CTEs e índices propuestos sobre volumen representativo, antes de crear índices adicionales o habilitar autoasignación.
- Definir con DBA el eventual índice compuesto de `public.valor` y la disponibilidad de extensiones para búsqueda textual; no alterar tablas ERP desde este módulo sin medición y aprobación.
- Configurar permisos de aplicación/sesión en Hub y definir `id_aplicacion` en la nueva base.
- Ejecutar revisión de la migración `004_bancos_tablas_auxiliares.sql` con negocio/TI para validar restricciones antes de la migración de producción.
