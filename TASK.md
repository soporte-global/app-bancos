# Tarea actual

La migración completa de los datos legacy a las tablas canónicas `global_prod.bancos_*` fue realizada. Incluye configuración, períodos/importaciones, movimientos, historial de asignación, asociaciones, reservas, borradores, mensajes y la semántica de asientos compartidos. La aplicación aún no usa estas tablas para atender tráfico funcional: el siguiente ciclo es de conciliación post-migración y corte controlado.

Diseño vigente:
- Dominio en `global_prod`, con nuevas tablas `bancos_*` y nombres en español.
- `nueva_app` queda definida como plantilla de referencia para identidad/permisos; SGUA se descarta.
- hQuery queda definida como librería compartida de UI/infraestructura cliente (versión externa publicada).

Próximo paso: emitir la conciliación de corte (conteos, importes, claves, trazabilidad y excepciones), conservar un respaldo recuperable y acordar la ventana de convivencia. Con esa evidencia, implementar primero los repositorios de lectura y la consulta paginada sobre las tablas nuevas, manteniendo las escrituras ERP y los automatismos deshabilitados.

Antes de desarrollar esas pantallas, ejecutar `014_bancos_preparar_debug_global_temp.sql` en el ambiente de depuración y configurar un usuario de base de datos sin permisos de escritura sobre `public` ni `global_prod`. Activar `bancos_debug` sólo en `app/config.local.php`; desactivarlo es el cambio controlado que dirige los repositorios al esquema productivo después de validar el corte.

Antes de habilitar escritura funcional: configurar el permiso de cierre en Hub, definir la secuencia contable de fusión/generación, validar `EXPLAIN (ANALYZE, BUFFERS)` en volumen representativo y completar pruebas de integración, idempotencia y concurrencia. El detalle operativo está en `docs/migration-strategy.md`.
