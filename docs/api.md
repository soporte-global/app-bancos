# API

Los legados usan archivos PHP, formularios y AJAX, sin API formal. Las operaciones críticas son importación de período, reglas, asignación/estado, mensajería, asociación de valores/asientos, creación/fusión y cierre.

La API nueva debe autenticar y autorizar en servidor, validar entradas, preservar idempotencia cuando corresponda y no confiar en `idu` enviado por POST ni en rutas PHP heredadas. Debe integrarse al flujo de `nueva_app`: sesión opaca, `PoliticaAcceso` por aplicación y permiso interno por destino, antes de ejecutar carga o reglas de negocio.

## Infraestructura HTTP implementada

`api.php` es el único front controller JSON de la aplicación. Restaura la misma sesión opaca `GLOBAL_APPS_AUTH` que usa la interfaz, exige acceso vigente a APP BANCOS y responde errores con la forma estable `{"error":{"codigo":"...","mensaje":"..."}}`. No acepta una identidad enviada por el cliente.

El primer contrato disponible es `GET api.php?accion=csrf`. Devuelve un token aleatorio de 256 bits ligado a la sesión bajo `data.csrf_token`; no muta datos de negocio. Cada futura acción de escritura se registrará por un nombre cerrado y deberá, en este orden:

1. aceptar sólo JSON mediante `POST`;
2. validar `X-CSRF-Token` con `ProteccionCsrf`;
3. autorizar la acción con `AutorizadorAccion`: nivel Hub 1 administra toda la app y un acceso general necesita el permiso interno exacto;
4. exigir `Idempotency-Key` y ejecutar mediante `EjecutorComandoIdempotente`;
5. persistir la respuesta y un evento de auditoría dentro de la misma transacción que el cambio funcional.

Los estados de error reservados son `400` para acción o JSON inválidos, `401` para sesión ausente o vencida, `403` para acceso/permiso/CSRF, `404` para acción o movimiento inexistentes, `405` para método incorrecto, `409` para transición o clave idempotente incompatibles, `415` para contenido distinto de JSON, `422` para entrada inválida, `503` para escritura deshabilitada y `500` para fallos no publicables.

## Preparar movimiento mensual

`POST api.php?accion=movimiento.preparar` es la primera mutación funcional. Sólo está habilitada cuando `bancos_debug = true`; en cualquier otro perfil devuelve `503` antes de abrir una transacción. Exige el permiso interno `movimiento-preparar` o nivel administrador, además de `X-CSRF-Token`, `Idempotency-Key` y `Content-Type: application/json`.

Entrada:

```json
{
  "movimiento_id": 123,
  "cuenta_bancaria_id": "103500000000002811",
  "inicio_periodo": "2026-09-01"
}
```

El repositorio bloquea la fila y verifica que el ID pertenezca exactamente a esa cuenta/período. Sólo admite `ABIERTO -> PARA_CERRAR`; inserta un evento en `bancos_historial_asignacion` y actualiza la identidad/fecha de modificación del movimiento dentro de la transacción idempotente. No exige asociaciones ni borradores en esta primera regla confirmada, no modifica esos recursos y no escribe en el ERP. La respuesta contiene `movimiento_id`, estados anterior/nuevo, `historial_id` y fecha; `meta.repetida` indica si provino del resultado persistido.

## Revertir preparación

`POST api.php?accion=movimiento.revertir-preparacion` acepta el mismo contexto más `motivo`, obligatorio entre 3 y 200 caracteres. Requiere el permiso interno homónimo o nivel administrador y conserva las mismas protecciones de sandbox, CSRF, JSON e idempotencia.

Sólo admite `PARA_CERRAR -> ABIERTO`. Con la fila bloqueada, desactiva las asociaciones, reservas y borradores activos del movimiento; no elimina ninguna fila ni las líneas de los borradores. Luego registra el motivo y operador en `bancos_historial_asignacion`, actualiza el movimiento y deja auditoría en la misma transacción. La respuesta incluye el conteo por tipo bajo `data.descartados`. Mensajes y ERP quedan intactos.

## Asociar un valor ERP

`POST api.php?accion=movimiento.asociar-valor` recibe `movimiento_id`, `valor_zetti_id`, `cuenta_bancaria_id` e `inicio_periodo`. Exige el permiso `movimiento-asociar-valor` o nivel administrador, y conserva las protecciones de debug, CSRF, JSON e idempotencia.

La acción sólo admite movimientos `ABIERTO`. El servidor deriva `monto_asociado` del crédito o débito del movimiento: el cliente no puede elegirlo. El valor se lee desde `public.valor`, debe existir, no puede estar en los estados `7`, `20` o `36` y su monto absoluto debe cubrir el movimiento. Con el movimiento bloqueado, se rechaza otra reserva/asociación activa de valor y se insertan reserva y asociación mediante los índices únicos parciales. Un conflicto deja ambas operaciones revertidas.

La interfaz permite buscar opciones contextuales o ingresar el ID exacto. La búsqueda no puntúa ni autoselecciona: esas reglas continúan pendientes de validación. La respuesta `201` contiene movimiento, valor, importe, reserva y asociación; la auditoría conserva la misma evidencia. Los IDs ERP viajan como texto decimal para no perder precisión en JavaScript. No se actualiza ninguna fila ERP.

## Crear borrador contable

`POST api.php?accion=movimiento.crear-borrador` recibe el contexto del movimiento, `nodo_zetti_id`, `fecha_contable`, `modelo` y entre 2 y 200 `lineas`. Cada línea contiene `cuenta_zetti_id`, `debe`, `haber` y una observación opcional. Exige `movimiento-crear-borrador` o nivel administrador, además de las protecciones comunes.

Cada importe admite hasta cinco decimales y se normaliza sin aritmética binaria. Una línea debe tener un valor positivo exclusivamente en debe o haber, y ambos totales deben coincidir exactamente. Se aceptan cuentas repetidas porque existen borradores migrados válidos con esa forma. No se fuerza que el total coincida con el extracto: sólo 2.338 de los 5.169 borradores activos migrados cumplen esa igualdad, mientras que todos están balanceados.

La acción sólo admite movimientos `ABIERTO` sin otro borrador activo. Valida nodo y cuentas contra el ERP de sólo lectura, crea borrador y líneas, y luego reserva/asocia el borrador en la misma transacción idempotente. La respuesta `201` incluye los IDs del borrador, reserva y asociación y la cantidad de líneas. No genera operación, asiento ni movimiento ERP.

## Asociar un asiento ERP existente

`POST api.php?accion=movimiento.asociar-asiento` recibe el contexto, `asiento_zetti_id` y el booleano obligatorio `compartido`. Exige `movimiento-asociar-asiento` o nivel administrador y conserva las protecciones comunes de debug, CSRF, JSON e idempotencia.

El movimiento debe estar `ABIERTO` y no tener otro asiento activo. El servidor obtiene el asiento y sus líneas desde `public`, exige al menos dos líneas y balance exacto, y deriva `monto_asociado` del extracto. En modalidad exclusiva, el monto debe coincidir con el mayor importe absoluto de sus líneas y el asiento no puede tener otro uso activo. En modalidad compartida se permite más de un movimiento, pero se rechaza cualquier mezcla con usos exclusivos. Un bloqueo transaccional por ID de asiento serializa esa decisión aun cuando todavía no existan reservas. La respuesta incluye asiento, modalidad, monto, cantidad de líneas, reserva y asociación; no modifica el ERP.

## Búsqueda asistida de recursos ERP

`GET api.php?accion=erp.buscar-valores` recibe el contexto exacto del movimiento y `limite` entre 1 y 20. Requiere el mismo permiso que la asociación de valores. Devuelve valores de la cuenta bancaria con estado permitido, monto suficiente y sin reserva/asociación activa, ordenados por cercanía de importe, fecha e ID.

`GET api.php?accion=erp.buscar-asientos` agrega `compartido=0|1` y requiere el permiso de asociación de asientos. Examina una ventana de ±45 días, acota primero los 500 asientos temporalmente más cercanos, exige al menos dos líneas balanceadas y aplica las reglas de importe/disponibilidad de la modalidad elegida. Ambas búsquedas son de sólo lectura, sólo funcionan para movimientos `ABIERTO`, devuelven como máximo 20 resultados y nunca confirman una asociación ni seleccionan automáticamente una opción.

`GET api.php?accion=erp.buscar-nodos` recibe el mismo contexto y una `busqueda` opcional de hasta 80 caracteres. `GET api.php?accion=erp.buscar-cuentas` agrega `nodo_zetti_id` y exige una `busqueda` de 2 a 80 caracteres. Ambos reutilizan `movimiento-crear-borrador`: nodo prioriza el asociado a la cuenta bancaria; cuenta busca por ID, código o nombre en todo el catálogo y sólo usa el nodo seleccionado para ordenar y explicitar coincidencia. No filtra por nodo ni por `imputable`, porque los borradores históricos válidos demuestran que esas condiciones no son universales. La confirmación del borrador vuelve a validar la existencia de todos los recursos.

## Mensajería y lecturas

`POST api.php?accion=movimiento.agregar-mensaje` recibe el contexto exacto y `cuerpo` entre 1 y 2000 caracteres. Requiere `movimiento-agregar-mensaje` o nivel administrador, además de debug, JSON, CSRF e idempotencia. El servidor deriva `tipo_mensaje=USUARIO` y `emisor_hub_id` de la sesión; ningún dato de identidad se acepta desde el cliente. La conversación puede continuar en cualquier estado, incluidos los movimientos cerrados, de acuerdo con el histórico migrado. La inserción del mensaje, su recepción leída para el propio emisor y la auditoría son atómicas.

`POST api.php?accion=movimiento.marcar-mensajes-leidos` recibe sólo el contexto y exige `movimiento-marcar-mensajes-leidos` o nivel administrador con las mismas protecciones. Crea o completa la recepción del operador para todos los mensajes ajenos de ese movimiento y conserva la primera fecha de lectura. La ausencia de una recepción se interpreta como pendiente: no se generan destinatarios ni lecturas para otros usuarios. La respuesta informa `mensajes_leidos` y `leido_en`.

## Asignación de responsable

`POST api.php?accion=movimiento.asignar-responsable` recibe el contexto y `responsable_id`. Requiere `movimiento-asignar-responsable` o nivel administrador y las protecciones comunes de debug, JSON, CSRF e idempotencia. El destino debe ser una cuenta activa con acceso efectivo general a APP BANCOS; actualmente el catálogo contiene únicamente `mcaballero` y `hvega`, y se actualizará automáticamente al incorporar accesos en Hub.

La acción no cambia el estado: bloquea el movimiento, copia su estado vigente en un nuevo evento de historial y registra al responsable como `usuario_hub_id`. El operador real permanece en auditoría y en la observación técnica del evento. Reasignar al responsable vigente es un no-op auditado y no duplica el historial. La respuesta incluye responsable anterior/nuevo, usuario, evento y `cambio`.

## Previsualización de importación

`POST api.php?accion=importacion.previsualizar` usa `multipart/form-data` y recibe `archivo`, `cuenta_bancaria_id`, `configuracion_id` e `inicio_periodo`. Requiere `importacion-previsualizar` o nivel administrador, sesión, CSRF y sandbox; no requiere idempotencia porque no persiste datos. La cuenta debe existir en ERP y la configuración elegida debe estar activa y vinculada mediante `bancos_configuracion_cuenta` o una importación previa.

El archivo debe ser CSV o TSV UTF-8, pesar como máximo 5 MB y contener hasta 10.000 movimientos. La cabecera admite los nombres canónicos `fecha_operacion`, `descripcion`, `credito`, `debito`, `referencia` y `codigo_extracto`, junto con aliases documentados; las primeras cuatro son obligatorias. Cada fecha debe pertenecer al período elegido y cada fila debe tener un importe positivo exclusivamente en crédito o débito, con hasta dos decimales. La respuesta devuelve SHA-256 del archivo original, delimitador, conteos, totales normalizados a cinco decimales, hasta 100 errores y hasta 100 filas válidas de muestra. Cada fila de muestra informa `clasificacion`, `subtipos_candidatos`, `subtipo_valor_zetti_id` y si habilita validación automática; el resumen cuenta resultados unívocos, múltiples, sin regla y sin código. `meta.persistida=false` es parte del contrato.

La respuesta informa además `configuraciones_disponibles` y conserva la configuración seleccionada. No se elige una configuración automáticamente: las 70 cuentas vinculadas tienen más de una configuración activa en producción.

`POST api.php?accion=importacion.reporte-errores` recibe el mismo multipart y reutiliza `importacion-previsualizar`, sesión, CSRF y sandbox. El servidor vuelve a validar el contexto y el archivo, pero responde `text/csv` en vez de JSON. El reporte contiene BOM UTF-8, separador punto y coma y las columnas `fila` y `error`; incluye todos los errores por fila aunque la previsualización visual continúe limitada a 100. Si el archivo no presenta errores devuelve `422`. Es una operación de sólo lectura y no usa idempotencia.

`POST api.php?accion=importacion.confirmar` recibe el mismo multipart y exige `importacion-confirmar`, CSRF, sandbox e `Idempotency-Key`. El servidor vuelve a analizar y clasificar el archivo completo y sólo acepta una validación estructural sin errores. Dentro de una transacción vuelve a validar la configuración, toma un bloqueo por cuenta/período/hash/versión, rechaza un lote ya importado y crea `bancos_importacion_extracto` junto con todos sus movimientos. El lote nace `ABIERTO`, conserva nombre, SHA-256, versión `CSV-TSV-V1`, número de fila y operador. Sólo una clasificación unívoca persiste `subtipo_valor_zetti_id`; las múltiples, sin regla o sin código no bloquean la carga y quedan con subtipo nulo. Un reintento con la misma clave devuelve el HTTP 201 almacenado; el mismo archivo con otra clave devuelve conflicto 409.

Los contratos se separarán por caso de uso: consulta/paginación de extractos, importación, candidatos, reserva/asociación, mensajería, cambio de estado/cierre y conciliación de cheque. Las listas reciben `cuenta_bancaria_id`, `inicio_periodo`, filtros permitidos y cursor opaco; no aceptan fragmentos SQL ni orden arbitrario. Las operaciones que escriben reciben una clave de idempotencia y devuelven el identificador interno, el estado y la evidencia de auditoría.

La primera lectura materializada es la pantalla interna `?pag=bandeja-mensual`. Recibe por query string `cuenta_bancaria_id`, `inicio_periodo`, `limite` (1 a 100), los filtros opcionales `estado`, `responsable_id`, `asociacion=CON|SIN` y `mensajes=CON|SIN`; desde la segunda página recibe además `cursor`. Cuenta y período se eligen desde importaciones existentes, con una etiqueta ERP que identifica nodo, banco, tipo, nombre y código. El cursor versión 3 firma la posición, la cuenta, el período y los filtros; los cursores anteriores sólo se aceptan sin filtros. El cliente no puede enviar directamente la fecha ni el ID de ordenamiento.

Cada movimiento de la página contiene el resumen necesario para la grilla y colecciones completas de `asociaciones`, `mensajes`, `borradores.lineas` e `historial`. Esas colecciones se consultan por lote para los IDs visibles. Cuando existe una sesión, cada mensaje incluye su estado de lectura para el usuario actual y el movimiento informa `mensajes_no_leidos`. La carga se realiza sólo después de que el enrutador normal resuelva y autorice el destino.

El contrato de transición admite `ABIERTO -> PARA_CERRAR` para un usuario autorizado de la aplicación, `PARA_CERRAR -> ABIERTO` con `motivo` obligatorio y `PARA_CERRAR -> CERRADO` sólo con permiso de cierre. No existe endpoint de reapertura. Preparar asociaciones o borradores no escribe ERP; el endpoint de cierre es el único que puede materializar el efecto contable y debe ser transaccional. El plan de consultas que respalda esos contratos está en [query-plan.md](database/query-plan.md).
