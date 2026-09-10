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

Infraestructura de escritura preparada: `api.php` expone un único front controller JSON autenticado y entrega el token CSRF de la sesión. `AutorizadorAccion` concede alcance completo sólo al nivel administrador y exige permisos internos a futuros accesos generales. `EjecutorComandoIdempotente` coordina transacción, deduplicación por huella de solicitud, respuesta persistida y auditoría. La migración `019` creó las tablas de control en `global_prod` y `global_temp`; la prueba de escritura se ejecutó y limpió exclusivamente en el sandbox. Las escrituras ERP continúan deshabilitadas.

Primera mutación funcional: la acción `movimiento.preparar` permite a los administradores actuales pasar un movimiento `ABIERTO` a `PARA_CERRAR` desde el detalle de la bandeja. Sólo funciona con `bancos_debug = true`, exige CSRF e idempotencia, bloquea la fila, valida cuenta/período, registra historial y auditoría, y no toca asociaciones, borradores ni ERP. La migración `020` registró el permiso interno `movimiento-preparar` sin asignarlo a usuarios adicionales.

Reversión funcional: `movimiento.revertir-preparacion` permite devolver `PARA_CERRAR` a `ABIERTO` con motivo obligatorio. En una sola transacción desactiva asociaciones, reservas y borradores activos sin borrar su evidencia, conserva mensajes/líneas/historial y registra operador y auditoría. Sigue limitada a debug. La migración `021` registró su permiso interno sin asignaciones nuevas.

Asociación segura de valor: `movimiento.asociar-valor` reserva y asocia por ID exacto un valor ERP a un movimiento `ABIERTO`. El importe se deriva del movimiento, el ERP permanece de sólo lectura y los estados/montos/disponibilidad se validan en servidor. La migración `022` agregó índices únicos parciales por movimiento y tipo en ambos esquemas, y registró el permiso granular sin asignaciones. La búsqueda/puntuación automática de candidatos no está habilitada todavía.

Borrador contable multílínea: `movimiento.crear-borrador` valida nodo, cuentas, fecha, modelo, exclusividad por línea y balance exacto de 2 a 200 líneas. Crea cabecera, líneas, reserva, asociación y auditoría en una transacción idempotente de `global_temp`, sin escribir ERP. La migración `023` registró el permiso granular sin asignaciones nuevas. La interfaz ofrece búsqueda asistida de nodo y cuenta, con ID exacto como alternativa.

Asociación segura de asiento: `movimiento.asociar-asiento` valida por lectura que el asiento ERP exista, tenga al menos dos líneas y esté balanceado. La modalidad exclusiva exige coincidencia de importe y disponibilidad; la compartida admite varios movimientos, pero no puede mezclarse con usos exclusivos. La reserva, asociación y auditoría se escriben atómicamente sólo en `global_temp`. La migración `024` registró el permiso sin usuarios nuevos.

Búsqueda asistida: `erp.buscar-valores`, `erp.buscar-asientos`, `erp.buscar-nodos` y `erp.buscar-cuentas` muestran opciones contextuales y limitadas dentro del drawer, sin autoselección ni DML. Reutilizan el permiso de la operación correspondiente y la confirmación vuelve a validar todo. Los IDs ERP `bigint` se conservan como texto en el contrato web. Cuenta muestra nodo e imputabilidad, pero no los usa como filtros duros porque los datos históricos contienen excepciones.

Mensajería operativa: `movimiento.agregar-mensaje` publica como `USUARIO` usando siempre la identidad de sesión y `movimiento.marcar-mensajes-leidos` registra sólo la lectura del operador actual. Ambas acciones funcionan en cualquier estado, validan cuenta/período, son idempotentes y auditadas, y escriben exclusivamente en `global_temp`. La migración `025` agregó los permisos internos sin asignar usuarios nuevos; la bandeja muestra mensajes pendientes por usuario.

Asignación operativa: `movimiento.asignar-responsable` permite asignar y reasignar sin cambiar el estado, dejando historial e identidad del operador en auditoría. El destino debe tener acceso general efectivo a APP BANCOS; el selector contiene hoy sólo `mcaballero` y `hvega` y seguirá automáticamente las futuras altas en Hub. La migración `026` fue aplicada sin asignaciones granulares nuevas.

Etapa 6, importación operativa en sandbox: `?pag=importaciones` exige cuenta, configuración y período, previsualiza archivos CSV/TSV UTF-8 y permite confirmarlos. `importacion.confirmar` vuelve a analizar los bytes, serializa la decisión por hash, rechaza duplicados por cuenta/período/versión y crea lote más movimientos `ABIERTO` en una transacción idempotente y auditada. Las migraciones `027` y `028` registraron destino y permisos; sólo `mcaballero` y `hvega` acceden por su nivel administrador, sin asignaciones granulares nuevas.

Clasificación de importación: cada código se compara de forma exacta, normalizada y sensible al sentido contra las reglas de la configuración. Una única opción persiste `subtipo_valor_zetti_id`; cero o varias opciones quedan explícitamente sin subtipo y se informan en la previsualización. La búsqueda asistida de valores respeta los subtipos candidatos. La migración `029` cargó 165 reglas reales de los lotes sombra únicamente en `global_temp`.

Reporte de errores de importación: cuando la previsualización detecta filas inválidas, la misma pantalla permite descargar un CSV UTF-8 con BOM, separado por punto y coma y con todos los pares fila/error. `importacion.reporte-errores` vuelve a validar archivo, cuenta, configuración y período, reutiliza el permiso de previsualización y no persiste datos.

Diseño UX/UI documentado: `docs/ux/navigation-and-ui.md` releva las estructuras de BANCOS y BANCOS_MENSUAL y propone navegación por rutas/tareas, contexto persistente de cuenta-período, detalle progresivo y acciones separadas por permiso/estado.

Sistema visual de RRHH implementado: la bandeja usa paleta derivada, tema claro/oscuro centralizado por cookie, CSS separado entre estructura/apariencia/modos, paneles contiguos, densidad compacta, tabla responsive y drawer accesible. Se retiraron el tema local de `localStorage` y el movimiento del formulario al footer. El contrato y la validación están en `docs/ux/rrhh-style/`.

Próximo paso de interfaz: validar el sistema sobre la instalación integrada con datos reales y archivar capturas definitivas en `docs/ux/evidence/rrhh-style/`, sin habilitar escrituras.

La migración `014_bancos_preparar_debug_global_temp.sql` se ejecutó el 2026-09-04 en el ambiente de depuración: se verificó la presencia de 49 tablas `bancos_*`, las cinco dependencias ERP que faltaban y, el 2026-09-07, que no hay defaults de secuencias que apunten a `public` o `global_prod`. Falta configurar un usuario de base de datos sin permisos de escritura sobre esos esquemas. Activar `bancos_debug` sólo en `app/config.local.php`; desactivarlo es el cambio controlado que dirige los repositorios al esquema productivo después de validar el corte.

Antes de habilitar cierre o escritura ERP: configurar el permiso de cierre en Hub, definir la secuencia contable de fusión/generación, validar `EXPLAIN (ANALYZE, BUFFERS)` en volumen representativo y completar la prueba de concurrencia. La integración e idempotencia secuencial de la infraestructura común ya están cubiertas. El detalle operativo está en `docs/migration-strategy.md`.
