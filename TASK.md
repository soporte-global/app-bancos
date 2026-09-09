# Tarea actual

La migración completa de los datos legacy a las tablas canónicas `global_prod.bancos_*` fue realizada. Incluye configuración, períodos/importaciones, movimientos, historial de asignación, asociaciones, reservas, borradores, mensajes y la semántica de asientos compartidos. La aplicación aún no usa estas tablas para atender tráfico funcional: el siguiente ciclo es de conciliación post-migración y corte controlado.

Diseño vigente:
- Dominio en `global_prod`, con nuevas tablas `bancos_*` y nombres en español.
- `nueva_app` queda definida como plantilla de referencia para identidad/permisos; SGUA se descarta.
- hQuery queda definida como librería compartida de UI/infraestructura cliente (versión externa publicada).

Avance funcional: `015_bancos_debug_fixtures.sql` cargó cinco movimientos de prueba en `global_temp` sin escribir datos productivos. `016_bancos_debug_cargar_lote_sombra_2026_07.sql` agregó un lote real y acotado: 51 movimientos y 47 asociaciones de una cuenta/período ya migrados desde `global_prod`, sin DML sobre `public` ni `global_prod`. `BandejaMensualRepository` y `ConsultarBandejaMensual` exponen la consulta keyset como pantalla interna `?pag=bandeja-mensual`, con límite de 1 a 100 y cursor firmado ligado a cuenta, período y filtros.

Validación de sombra: `tests/CompararLoteSombra016Test.php` confirmó paridad del lote `016` entre legacy, `global_prod` y `global_temp`: 51 movimientos y 47 asociaciones de asiento, sin diferencias.

Avance de cobertura: `017_bancos_debug_cargar_casos_sombra.sql` cargó dos movimientos de sombra con dos mensajes, una asociación a valor, un borrador y dos líneas, sin DML fuera de `global_temp`.

Validación de cobertura: `tests/CompararCasosSombra017Test.php` confirmó paridad de movimientos, mensajes, asociación a valor y líneas de borrador contra producción; además verifica una excepción documentada de migración.

La bandeja de sólo lectura ya ofrece selectores reales de cuenta/período, filtros por estado, responsable, asociación y mensajes, y detalle completo por página. El detalle incluye todas las asociaciones activas, conversación, borradores con líneas contables e historial de estado/responsable. Las consultas de detalle se agrupan por página y no generan una consulta por fila.

Acceso inicial configurado: la migración `018_bancos_hub_acceso_inicial.sql` registró `APP BANCOS` como aplicación Hub 12 y concedió acceso administrador y acceso a `bandeja-mensual` exclusivamente a las cuentas activas `mcaballero` y `hvega`, mediante asignaciones directas. La aplicación ya exige ese `id_aplicacion`.

Infraestructura de escritura preparada: `api.php` expone un único front controller JSON autenticado y entrega el token CSRF de la sesión. `AutorizadorAccion` concede alcance completo sólo al nivel administrador y exige permisos internos a futuros accesos generales. `EjecutorComandoIdempotente` coordina transacción, deduplicación por huella de solicitud, respuesta persistida y auditoría. La migración `019` creó las tablas de control en `global_prod` y `global_temp`; la prueba de escritura se ejecutó y limpió exclusivamente en el sandbox. No hay todavía acciones de negocio mutables ni escrituras ERP habilitadas.

Diseño UX/UI documentado: `docs/ux/navigation-and-ui.md` releva las estructuras de BANCOS y BANCOS_MENSUAL y propone navegación por rutas/tareas, contexto persistente de cuenta-período, detalle progresivo y acciones separadas por permiso/estado.

Sistema visual de RRHH implementado: la bandeja usa paleta derivada, tema claro/oscuro centralizado por cookie, CSS separado entre estructura/apariencia/modos, paneles contiguos, densidad compacta, tabla responsive y drawer accesible. Se retiraron el tema local de `localStorage` y el movimiento del formulario al footer. El contrato y la validación están en `docs/ux/rrhh-style/`.

Próximo paso de interfaz: validar el sistema sobre la instalación integrada con datos reales y archivar capturas definitivas en `docs/ux/evidence/rrhh-style/`, sin habilitar escrituras.

La migración `014_bancos_preparar_debug_global_temp.sql` se ejecutó el 2026-09-04 en el ambiente de depuración: se verificó la presencia de 49 tablas `bancos_*`, las cinco dependencias ERP que faltaban y, el 2026-09-07, que no hay defaults de secuencias que apunten a `public` o `global_prod`. Falta configurar un usuario de base de datos sin permisos de escritura sobre esos esquemas. Activar `bancos_debug` sólo en `app/config.local.php`; desactivarlo es el cambio controlado que dirige los repositorios al esquema productivo después de validar el corte.

Antes de habilitar cierre o escritura ERP: configurar el permiso de cierre en Hub, definir la secuencia contable de fusión/generación, validar `EXPLAIN (ANALYZE, BUFFERS)` en volumen representativo y completar la prueba de concurrencia. La integración e idempotencia secuencial de la infraestructura común ya están cubiertas. El detalle operativo está en `docs/migration-strategy.md`.
