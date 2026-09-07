# Tarea actual

La migración completa de los datos legacy a las tablas canónicas `global_prod.bancos_*` fue realizada. Incluye configuración, períodos/importaciones, movimientos, historial de asignación, asociaciones, reservas, borradores, mensajes y la semántica de asientos compartidos. La aplicación aún no usa estas tablas para atender tráfico funcional: el siguiente ciclo es de conciliación post-migración y corte controlado.

Diseño vigente:
- Dominio en `global_prod`, con nuevas tablas `bancos_*` y nombres en español.
- `nueva_app` queda definida como plantilla de referencia para identidad/permisos; SGUA se descarta.
- hQuery queda definida como librería compartida de UI/infraestructura cliente (versión externa publicada).

Avance funcional: `015_bancos_debug_fixtures.sql` cargó cinco movimientos de prueba en `global_temp` sin escribir datos productivos. `BandejaMensualRepository` y `ConsultarBandejaMensual` exponen la consulta keyset como pantalla interna `?pag=bandeja-mensual`, con límite de 1 a 100 y cursor firmado ligado a cuenta/período. La prueba de integración confirmó dos páginas consecutivas sobre ese fixture.

Próximo paso funcional: comparar esta bandeja en sombra con la consulta legacy sobre períodos representativos antes de adoptar consumidores.

La migración `014_bancos_preparar_debug_global_temp.sql` se ejecutó el 2026-09-04 en el ambiente de depuración: se verificó la presencia de 49 tablas `bancos_*`, las cinco dependencias ERP que faltaban y, el 2026-09-07, que no hay defaults de secuencias que apunten a `public` o `global_prod`. Falta configurar un usuario de base de datos sin permisos de escritura sobre esos esquemas. Activar `bancos_debug` sólo en `app/config.local.php`; desactivarlo es el cambio controlado que dirige los repositorios al esquema productivo después de validar el corte.

Antes de habilitar escritura funcional: configurar el permiso de cierre en Hub, definir la secuencia contable de fusión/generación, validar `EXPLAIN (ANALYZE, BUFFERS)` en volumen representativo y completar pruebas de integración, idempotencia y concurrencia. El detalle operativo está en `docs/migration-strategy.md`.
