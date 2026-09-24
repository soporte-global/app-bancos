# Documentación técnica de APP BANCOS

Referencia de operación y soporte para la implementación actual. Complementa `docs/architecture.md`, `docs/api.md`, `docs/business-rules.md` y los procedimientos de `docs/migration/`. No contiene credenciales ni sustituye la aprobación de un corte productivo.

## 1. Alcance y estado

APP BANCOS consolida la bandeja de extractos mensuales y la conciliación de cheques. La interfaz actual expone bandeja, detalle, importación y configuraciones. Las escrituras funcionales de extractos, cierre y conciliación están implementadas para el **sandbox**. La aplicación legacy continúa siendo el escritor productivo hasta la ventana de corte y la definición de un único escritor.

La migración inicial y la conciliación post migración quedaron aprobadas el 2026-09-22. Ese resultado acredita el snapshot y los incrementales verificados en esa fecha; no garantiza que no haya aparecido deriva posterior. Antes de una nueva prueba o de un corte se deben repetir los comandos de previsualización y conciliación.

## 2. Estructura de la aplicación

`index.php` carga configuración, autenticación, autorización y las vistas internas. `api.php` atiende las consultas y comandos JSON o multipart. `app/html/` contiene las pantallas; `app/php/carga_inicial.php` prepara sus datos. Los casos de uso viven en `app/Application/`; los repositorios SQL en `app/Repository/`; la selección de esquemas en `app/Infrastructure/EsquemaBancos.php`. `src/Model/Core/` provee sesión y política de acceso compartidas.

La página **Acerca de** lee `docs/manual-de-uso.md` y `docs/manual-tecnico.md` desde una lista fija y los presenta como HTML escapado. Es una lectura local, sin conexión adicional a la base. Cualquier usuario que ya superó el acceso general a APP BANCOS puede abrirla. No habilita acciones ni concede permisos Hub.

## 3. Entorno, modo operativo y esquemas

`entorno` selecciona los parámetros de conexión (`tunel`, `testing` o `prod`). `bancos_modo_operativo` decide el destino de las escrituras (`sandbox` o `produccion`). Son conceptos independientes. `config.local.php` sobreescribe valores de `app/config.php` y nunca debe versionarse con secretos.

En sandbox, las tablas operativas `bancos_*` y los efectos ERP de prueba se resuelven en `global_temp`. Las referencias ERP se leen de `public` y la identidad/permisos Hub se leen de `global_prod`. El modo producción apunta las tablas operativas a `global_prod` y los efectos ERP a `public`; **no debe activarse como consecuencia de una prueba visual o funcional**.

Para publicar sandbox con `entorno=prod`, la configuración exige `bancos_modo_operativo=sandbox`, `bancos_sandbox_permitir_en_prod=true` y un login restringido miembro de `app_bancos_debug_runtime`. El arranque rechaza `postgres` y `root`; cada conexión web verifica que el login pueda escribir en `global_temp` y no pueda escribir en las tablas productivas comprobadas. El aviso permanente en la interfaz identifica el modo de prueba.

## 4. Autenticación y permisos

La sesión PHP identifica al operador. La política del Hub controla acceso general a la app y destinos internos. El administrador de APP BANCOS (`nivel=1`) cubre las acciones registradas; los demás usuarios necesitan permisos granulares como `movimiento-cerrar`, `cheque-conciliar` o `importacion-previsualizar`. Los únicos administradores habilitados inicialmente fueron `mcaballero` y `hvega`; incorporaciones posteriores deben gestionarse explícitamente en Hub.

Los endpoints de escritura nunca aceptan la identidad del operador desde el cliente. Validan sesión, permiso, token CSRF, contenido y clave de idempotencia antes de confirmar. Una pantalla visible no implica permiso para cada acción: la API vuelve a autorizar todas las solicitudes. La página Acerca de es una excepción informativa al permiso de destino, posterior al acceso general a la app.

## 5. Lectura de bandeja y estado

La bandeja recibe cuenta, período, filtros cerrados y límite. El repositorio consulta las tablas canónicas mediante `EsquemaBancos`; la paginación usa un cursor firmado ligado al contexto y filtros. El detalle agrega asociaciones, reservas, mensajes, borradores, historial y conciliaciones. La búsqueda de recursos ERP es de sólo lectura y acotada; el usuario confirma expresamente la asociación elegida.

Los estados operativos son `ABIERTO`, `PARA_CERRAR` y `CERRADO`. Preparar conserva reservas y borradores. Revertir preparación exige motivo y desactiva esas relaciones sin borrar evidencia. CERRADO es terminal. Mensajes y lecturas pueden continuar en cualquier estado; cada lectura corresponde a un operador concreto.

## 6. Importación y configuración

`importacion.previsualizar` acepta un archivo CSV/TSV UTF-8 de hasta 5 MB y 10.000 movimientos. Valida cabeceras, fechas, sentido e importes, y devuelve resumen, muestra y errores sin DML. `importacion.reporte-errores` genera un CSV completo de errores, también sin DML. `importacion.confirmar` vuelve a validar el archivo y crea lote y movimientos en una sola transacción auditada. El hash de bytes originales, la versión, la cuenta y el período impiden importar el mismo contenido dos veces aunque cambie la clave de solicitud.

`bancos_configuracion_cuenta` define qué cuentas están habilitadas para una configuración. Las reglas de clasificación usan código exacto normalizado y sentido; sólo una resolución unívoca asigna subtipo. Los mapeos contables y las reglas de responsable son versiones activas por configuración/subtipo. Alta, edición, retiro y cambios de validación automática exigen motivo o los datos correspondientes, permiso, idempotencia y auditoría. Las bajas son lógicas; no alteran movimientos históricos.

## 7. Cierre y conciliación

Los preflight `movimiento.prevalidar-cierre` y `cheque.prevalidar-conciliacion` son lecturas que devuelven bloqueos, advertencias y efectos previstos. El cliente sólo presenta el botón de ejecución cuando el resultado lo permite, y el servidor repite la validación bajo bloqueo dentro de la transacción. No se debe usar la presencia del botón como sustituto de ese control.

El cierre sandbox cubre casos sin efecto ERP, asiento existente válido, valor no cheque y borrador balanceado, incluidas combinaciones explícitamente admitidas. La combinación asiento existente más borrador permanece bloqueada por ambigüedad de fusión. La conciliación de cheque usa subtipos `13`, `14`, `10070` y `10071`, exige reserva y asociación consistentes y bloquea cualquier diferencia contable no exacta. Al conciliar, el movimiento continúa `PARA_CERRAR`; un cierre posterior registra `CERRADO` sin duplicar los efectos de la conciliación.

## 8. Transacciones, conflictos y auditoría

`EjecutorComandoIdempotente` reclama la combinación operación/clave, compara la huella normalizada de la solicitud y conserva la respuesta. Reintentar la misma petición devuelve el resultado anterior; reutilizar la clave con contenido u operador diferente produce conflicto. Asociación, reserva, cambio de estado, efectos ERP de prueba y auditoría se confirman o revierten juntos según el caso.

Los recursos exclusivos se serializan con bloqueos y restricciones de base, no sólo con una consulta previa de disponibilidad. Un conflicto significa que el operador debe recargar el contexto y revisar el recurso vigente. Los errores de validación no se corrigen cambiando la clave de idempotencia.

## 9. Sincronización mientras legacy sigue activo

El flujo de datos de prueba es `legacy → global_prod → global_temp`. `app/cli/migrar_legacy_incremental.php` incorpora datos nuevos del legado al esquema canónico. Después `app/cli/sincronizar_sandbox.php` compara y copia hacia el sandbox las 21 tablas y referencias necesarias. Ambos comandos previsualizan por defecto y exigen `--confirmar --clave=...` para escribir.

```text
php -d extension=pdo_pgsql app/cli/migrar_legacy_incremental.php
php -d extension=pdo_pgsql app/cli/sincronizar_sandbox.php
php -d extension=pdo_pgsql app/cli/conciliacion_post_migracion.php --json
```

Si la sincronización informa `REQUIERE_DECISION`, inspeccioná las filas divergentes. La opción de aceptar el origen sólo corresponde cuando se verificó que la versión del sandbox no representa una prueba que deba conservarse. El procedimiento completo, incluidas claves, respaldo inicial y secuencias de IDs, está en `docs/migration/sandbox-sync.md`; el catch-up está en `docs/migration/incremental-catchup.md`. La sincronización nunca devuelve datos de `global_temp` a producción.

## 10. Soporte y verificación

Ante una pantalla vacía, verificá primero acceso general, permiso de destino, modo operativo y errores del servidor. Ante un formulario deshabilitado, comprobá `bancos_modo_operativo` en la configuración efectiva; `bancos_debug` es sólo un alias transitorio. Ante un `503` en escritura, verificá el modo y el login restringido antes de investigar reglas de negocio. Ante un conflicto, inspeccioná el agregado y la auditoría antes de reintentar.

La regresión principal se ejecuta con las pruebas PHP y JavaScript del repositorio. Las pruebas integradas de datos deben correr sobre el sandbox preparado. Para un despliegue publicado se requiere además revisar esquema, privilegios reales del login, aviso de modo, CSRF, flujos de importación y cierre, conciliación post migración y respaldo restaurable. El corte definitivo requiere ventana, sincronización final, un único escritor y aprobación operativa; esta guía no autoriza ese cambio.
