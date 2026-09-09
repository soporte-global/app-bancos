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

Los estados de error reservados son `400` para acción inválida, `401` para sesión ausente o vencida, `403` para acceso/permiso/CSRF, `404` para acción no registrada, `405` para método incorrecto, `409` para reutilización incompatible de una clave idempotente, `422` para entrada inválida y `500` para fallos no publicables. Todavía no existe una mutación de negocio expuesta: agregar una requiere su caso de uso, permiso Hub, validaciones y prueba de integración.

Los contratos se separarán por caso de uso: consulta/paginación de extractos, importación, candidatos, reserva/asociación, mensajería, cambio de estado/cierre y conciliación de cheque. Las listas reciben `cuenta_bancaria_id`, `inicio_periodo`, filtros permitidos y cursor opaco; no aceptan fragmentos SQL ni orden arbitrario. Las operaciones que escriben reciben una clave de idempotencia y devuelven el identificador interno, el estado y la evidencia de auditoría.

La primera lectura materializada es la pantalla interna `?pag=bandeja-mensual`. Recibe por query string `cuenta_bancaria_id`, `inicio_periodo`, `limite` (1 a 100), los filtros opcionales `estado`, `responsable_id`, `asociacion=CON|SIN` y `mensajes=CON|SIN`; desde la segunda página recibe además `cursor`. Cuenta y período se eligen desde importaciones existentes, con una etiqueta ERP que identifica nodo, banco, tipo, nombre y código. El cursor versión 3 firma la posición, la cuenta, el período y los filtros; los cursores anteriores sólo se aceptan sin filtros. El cliente no puede enviar directamente la fecha ni el ID de ordenamiento.

Cada movimiento de la página contiene el resumen necesario para la grilla y colecciones completas de `asociaciones`, `mensajes`, `borradores.lineas` e `historial`. Esas colecciones se consultan por lote para los IDs visibles. La pantalla no ejecuta mutaciones y su carga se realiza sólo después de que el enrutador normal resuelva y autorice el destino.

El contrato de transición admite `ABIERTO -> PARA_CERRAR` para un usuario autorizado de la aplicación, `PARA_CERRAR -> ABIERTO` con `motivo` obligatorio y `PARA_CERRAR -> CERRADO` sólo con permiso de cierre. No existe endpoint de reapertura. Preparar asociaciones o borradores no escribe ERP; el endpoint de cierre es el único que puede materializar el efecto contable y debe ser transaccional. El plan de consultas que respalda esos contratos está en [query-plan.md](database/query-plan.md).
