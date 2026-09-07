# Tarea actual

La migración completa de los datos legacy a las tablas canónicas `global_prod.bancos_*` fue realizada. Incluye configuración, períodos/importaciones, movimientos, historial de asignación, asociaciones, reservas, borradores, mensajes y la semántica de asientos compartidos. La aplicación aún no usa estas tablas para atender tráfico funcional: el siguiente ciclo es de conciliación post-migración y corte controlado.

Diseño vigente:
- Dominio en `global_prod`, con nuevas tablas `bancos_*` y nombres en español.
- `nueva_app` queda definida como plantilla de referencia para identidad/permisos; SGUA se descarta.
- hQuery queda definida como librería compartida de UI/infraestructura cliente (versión externa publicada).

Próximo paso funcional: cargar un conjunto mínimo y versionado de fixtures en `global_temp` (cuenta, configuración, importación, movimientos y estados) y construir el primer repositorio de lectura paginada mediante `EsquemaBancos`. Las lecturas ERP permanecerán en `public`; las tablas propias se leerán desde el sandbox. La pantalla debe probarse con comparación en sombra contra las consultas legacy antes de adoptar consumidores.

La migración `014_bancos_preparar_debug_global_temp.sql` se ejecutó el 2026-09-04 en el ambiente de depuración: se verificó la presencia de 49 tablas `bancos_*`, las cinco dependencias ERP que faltaban y, el 2026-09-07, que no hay defaults de secuencias que apunten a `public` o `global_prod`. Falta configurar un usuario de base de datos sin permisos de escritura sobre esos esquemas. Activar `bancos_debug` sólo en `app/config.local.php`; desactivarlo es el cambio controlado que dirige los repositorios al esquema productivo después de validar el corte.

Antes de habilitar escritura funcional: configurar el permiso de cierre en Hub, definir la secuencia contable de fusión/generación, validar `EXPLAIN (ANALYZE, BUFFERS)` en volumen representativo y completar pruebas de integración, idempotencia y concurrencia. El detalle operativo está en `docs/migration-strategy.md`.
