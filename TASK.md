# Tarea actual

La migración completa de los datos legacy a las tablas canónicas `global_prod.bancos_*` fue realizada. Incluye configuración, períodos/importaciones, movimientos, historial de asignación, asociaciones, reservas, borradores, mensajes y la semántica de asientos compartidos. La aplicación aún no usa estas tablas para atender tráfico funcional: el siguiente ciclo es de conciliación post-migración y corte controlado.

Diseño vigente:
- Dominio en `global_prod`, con nuevas tablas `bancos_*` y nombres en español.
- `nueva_app` queda definida como plantilla de referencia para identidad/permisos; SGUA se descarta.
- hQuery queda definida como librería compartida de UI/infraestructura cliente (versión externa publicada).

Avance funcional: `015_bancos_debug_fixtures.sql` cargó cinco movimientos de prueba en `global_temp` sin escribir datos productivos. `016_bancos_debug_cargar_lote_sombra_2026_07.sql` agregó un lote real y acotado: 51 movimientos y 47 asociaciones de una cuenta/período ya migrados desde `global_prod`, sin DML sobre `public` ni `global_prod`. `BandejaMensualRepository` y `ConsultarBandejaMensual` exponen la consulta keyset como pantalla interna `?pag=bandeja-mensual`, con límite de 1 a 100 y cursor firmado ligado a cuenta/período.

Validación de sombra: `tests/CompararLoteSombra016Test.php` confirmó paridad del lote `016` entre legacy, `global_prod` y `global_temp`: 51 movimientos y 47 asociaciones de asiento, sin diferencias.

Avance de cobertura: `017_bancos_debug_cargar_casos_sombra.sql` cargó dos movimientos de sombra con dos mensajes, una asociación a valor, un borrador y dos líneas, sin DML fuera de `global_temp`.

Validación de cobertura: `tests/CompararCasosSombra017Test.php` confirmó paridad de movimientos, mensajes, asociación a valor y líneas de borrador contra producción; además verifica una excepción documentada de migración.

La bandeja de sólo lectura ya muestra estados legibles, asociaciones, último mensaje y resumen del borrador activo (fecha, modelo, debe y haber), con contraste reforzado para la tabla y los controles.

Diseño UX/UI documentado: `docs/ux/navigation-and-ui.md` releva las estructuras de BANCOS y BANCOS_MENSUAL y propone navegación por rutas/tareas, contexto persistente de cuenta-período, detalle progresivo y acciones separadas por permiso/estado.

Próximo paso funcional: agregar navegación de regreso y un indicador de filtros/página, sin habilitar escrituras.

La migración `014_bancos_preparar_debug_global_temp.sql` se ejecutó el 2026-09-04 en el ambiente de depuración: se verificó la presencia de 49 tablas `bancos_*`, las cinco dependencias ERP que faltaban y, el 2026-09-07, que no hay defaults de secuencias que apunten a `public` o `global_prod`. Falta configurar un usuario de base de datos sin permisos de escritura sobre esos esquemas. Activar `bancos_debug` sólo en `app/config.local.php`; desactivarlo es el cambio controlado que dirige los repositorios al esquema productivo después de validar el corte.

Antes de habilitar escritura funcional: configurar el permiso de cierre en Hub, definir la secuencia contable de fusión/generación, validar `EXPLAIN (ANALYZE, BUFFERS)` en volumen representativo y completar pruebas de integración, idempotencia y concurrencia. El detalle operativo está en `docs/migration-strategy.md`.
