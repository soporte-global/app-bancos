# Backlog

- Confirmar reglas contables y tratamiento de excepciones de cheques.
- Incorporar gradualmente los demás usuarios de APP BANCOS con acceso general y permisos internos específicos; el acceso administrador inicial queda limitado a `mcaballero` y `hvega`.
- Elegir y fijar una versión publicada de hQuery, validando sus dependencias y contrato `vars`.
- Incluir `GENERAL`, `NNNNN`, Mercado Pago y la excepción CREDICOOP en la conciliación de configuración; validar que su traducción a alcance global/múltiples cuentas coincida con las reglas de carga ya aplicadas.
- Emitir y archivar la conciliación post-migración de `010` a `013`: conteos, importes, claves, relaciones, trazabilidad, omisiones y excepciones esperadas.
- Provisionar un usuario de depuración con escritura exclusiva en `global_temp`; la auditoría de defaults de secuencias de `014` fue superada el 2026-09-07.
- Validar el sistema visual RRHH sobre la instalación integrada con datos reales y archivar evidencia definitiva clara/oscura en `docs/ux/evidence/rrhh-style/`; el fixture y las pruebas automatizadas ya fueron validados.
- Aplicar gradualmente las rutas operativas restantes de importación, configuración y conciliación definidas en `docs/ux/navigation-and-ui.md`; el contexto, los filtros y el detalle de la bandeja ya están implementados.
- Implementar todos los repositorios BANCOS a través de `EsquemaBancos`; queda prohibido calificar tablas operativas o ERP directamente con `global_prod` o `public`.
- Definir y ejecutar el corte controlado: respaldo recuperable, ventana de convivencia, lecturas en sombra y regla de un único escritor para las tablas canónicas.
- Detallar las validaciones futuras para `PARA_CERRAR`, el permiso especial de cierre en Hub y la secuencia contable definitiva de fusión/generación de asiento.
- Diseñar migraciones y contratos API antes de toda escritura ERP.
- Validar con `EXPLAIN (ANALYZE, BUFFERS)` el plan de CTEs e índices propuestos sobre volumen representativo, antes de crear índices adicionales o habilitar autoasignación.
- Definir con DBA el eventual índice compuesto de `public.valor` y la disponibilidad de extensiones para búsqueda textual; no alterar tablas ERP desde este módulo sin medición y aprobación.
- Revisar con negocio/TI los resultados de conciliación y autorizar el cambio gradual de consumidores desde las tablas legacy.
