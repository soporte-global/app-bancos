# Backlog

- Validar con negocio antes de producción el tratamiento y cuenta para diferencias, conciliación forzada, numeración e identidad ERP. El POST transaccional ya funciona en `global_temp` para importes exactos, usa la fecha del movimiento y deja número/usuarios ERP nulos; cuenta base, cuenta por subtipo, nodo y moneda se resuelven sin ambigüedad para los cuatro pendientes activos.
- Incorporar gradualmente los demás usuarios de APP BANCOS con acceso general y permisos internos específicos; el acceso administrador inicial queda limitado a `mcaballero` y `hvega`.
- Elegir y fijar una versión publicada de hQuery, validando sus dependencias y contrato `vars`.
- Incluir `GENERAL`, `NNNNN`, Mercado Pago y la excepción CREDICOOP en la conciliación de configuración; validar que su traducción a alcance global/múltiples cuentas coincida con las reglas de carga ya aplicadas.
- Emitir y archivar la conciliación post-migración de `010` a `013`: conteos, importes, claves, relaciones, trazabilidad, omisiones y excepciones esperadas.
- Provisionar un usuario de depuración con escritura exclusiva en `global_temp`; la auditoría de defaults de secuencias de `014` fue superada el 2026-09-07.
- Validar el sistema visual RRHH sobre la instalación integrada con datos reales y archivar evidencia definitiva clara/oscura en `docs/ux/evidence/rrhh-style/`; el fixture y las pruebas automatizadas ya fueron validados.
- Aplicar gradualmente las rutas operativas restantes de importación, configuración y conciliación definidas en `docs/ux/navigation-and-ui.md`; el contexto, los filtros y el detalle de la bandeja ya están implementados.
- Incorporar gradualmente usuarios generales y, cuando negocio lo autorice, traducir las reglas legacy de responsables a cuentas con acceso efectivo. El mantenimiento de configuraciones ya cubre vínculos, clasificación, mapeos contables y responsables con las migraciones `031` a `038`.
- Definir si el formato productivo inicial debe incluir XLSX además del contrato CSV/TSV; no aceptar planillas binarias sin fixtures reales de cada banco.
- Implementar todos los repositorios BANCOS a través de `EsquemaBancos`; queda prohibido calificar tablas operativas o ERP directamente con `global_prod` o `public`.
- Definir y ejecutar el corte controlado: respaldo recuperable, ventana de convivencia, lecturas en sombra y regla de un único escritor para las tablas canónicas.
- Validar con negocio el contrato preliminar del preflight y definir un modelo explícito para fusión que identifique cada asiento fuente, su orden de reversa y el asiento resultante; asiento+borrador no es evidencia suficiente y no tiene casos activos migrados. Definir además número/nombre de asiento e identidad ERP del operador. La compensación transaccional de valor+borrador ya está probada en `global_temp`; `movimiento-cerrar` no debe asignarse a usuarios generales hasta autorizar producción.
- Validar con negocio si las cuentas no imputables deben advertirse o bloquearse en nuevas preparaciones; el histórico contiene 10 líneas activas de ese tipo, por lo que la búsqueda sólo informa y prioriza.
- Definir con negocio el algoritmo y umbral de candidatos antes de reemplazar el ingreso manual de `valor_zetti_id` por sugerencias automáticas.
- Validar con `EXPLAIN (ANALYZE, BUFFERS)` el plan de CTEs e índices propuestos sobre volumen representativo, antes de crear índices adicionales o habilitar autoasignación.
- Definir con DBA el eventual índice compuesto de `public.valor` y la disponibilidad de extensiones para búsqueda textual; no alterar tablas ERP desde este módulo sin medición y aprobación.
- Revisar con negocio/TI los resultados de conciliación y autorizar el cambio gradual de consumidores desde las tablas legacy.
