# Arquitectura objetivo

Estado: estructura acordada para implementación; las reglas contables pendientes siguen sujetas a validación de negocio.

El producto se implementará como un monolito modular de bancos, con dos módulos de negocio independientes que comparten núcleo técnico y modelo operativo: **Conciliación de cheques** (BANCOS) y **Gestión de extractos mensuales** (BANCOS_MENSUAL). No se duplican autenticación, configuración, importación, búsqueda ERP ni infraestructura de persistencia. Sólo se comparte lo que tiene la misma regla; las transiciones y efectos contables permanecen dentro de cada módulo.

```text
HTTP/UI -> Controlador -> Caso de uso -> Política de dominio -> Repositorios -> PostgreSQL / ERP
                                  |                  |
                                  |                  -> Transacción + idempotencia + auditoría
                                  -> autorización de acción (Hub)

Módulo extractos: importar, clasificar, asignar, asociar, mensajería, cerrar
Módulo cheques: buscar candidato, proponer, confirmar/forzar conciliación, reportar
Compartido: sesión, permisos, configuración, movimientos de extracto, reservas, consultas ERP
```

## Límites y responsabilidades

| Capa | Responsabilidad | No debe hacer |
| --- | --- | --- |
| HTTP/controlador | validar forma de entrada, obtener sesión, responder DTO/HTTP | SQL, reglas de puntaje o decisiones contables |
| caso de uso | orquestar autorización, transacción, idempotencia y políticas | generar HTML ni interpolar SQL |
| dominio | transiciones permitidas, cálculo de candidato, condiciones de cierre y forzado | depender de PDO o de `$_POST` |
| repositorio | consultas parametrizadas, CTEs, joins e hidratación de datos | decidir permisos o transiciones |
| gateway ERP | leer/escribir valor, operación, asiento y movimiento con contrato ERP | conocer estado de interfaz o sesión |
| auditoría/eventos | registrar cambio de estado, reserva, asociación y efecto ERP | ser reemplazada por logs HTTP |

`nueva_app` es la referencia adoptada para login y permisos, no SGUA. Su Core separa Identidad, Acceso e Infraestructura; autentica una vez, mantiene una sesión PHP opaca compartible entre aplicaciones y vuelve a autorizar por aplicación y destino interno mediante `PoliticaAcceso`. Los permisos efectivos se obtienen desde `ftweb.global_prod.hub_*`. Las nuevas tablas propias se ubican en `global_prod`, con prefijo `bancos_` y nombres en español.

Las asociaciones a asientos ERP existentes no pasan todavía por un gateway de escritura: consultan `public.asiento` y `public.movimiento` mediante `EsquemaBancos::tablaLecturaErp`, y persisten únicamente reserva, asociación y auditoría operativa. Un advisory lock transaccional sobre el ID del asiento protege la elección exclusiva/compartida cuando aún no existe una fila propia bloqueable.

`BusquedaRecursosErpRepository` concentra las consultas asistidas de sólo lectura. Parte siempre de un movimiento validado dentro de su cuenta/período, usa filtros indexables antes de ordenar y limita resultados; el caso de uso no calcula puntajes ni toma decisiones automáticas. La validación del comando de asociación sigue siendo autoritativa porque la disponibilidad puede cambiar después de mostrar las opciones.

La interfaz consume contratos API y no conoce IDs heredados, SQL ni códigos ERP. `hQuery` queda limitado a funciones UI; su estado técnico vive en `vars.hquery` y el contexto funcional bajo `sesion`, `app`, `data` y `estado`. El detalle de CTEs, joins, paginación, reservas concurrentes e índices está en [query-plan.md](database/query-plan.md).

La infraestructura común de escritura se materializa en `api.php`, `ProteccionCsrf`, `AutorizadorAccion` y `EjecutorComandoIdempotente`. El administrador Hub (`nivel = 1`) puede ejecutar cualquier acción registrada de APP BANCOS; los accesos generales deben poseer el permiso interno específico. El ejecutor abre y controla una única transacción, reclama `(operacion, clave)`, compara una huella SHA-256 del JSON normalizado, ejecuta el comando una sola vez y guarda respuesta y auditoría antes del commit. Un reintento compatible devuelve la respuesta persistida; reutilizar la clave con otro usuario o contenido produce conflicto. Las tablas `bancos_solicitud_idempotente` y `bancos_evento_auditoria` existen en `global_prod` y `global_temp`, y se resuelven exclusivamente con `EsquemaBancos`.

La navegación y los límites de UX resultantes del relevamiento de BANCOS y BANCOS_MENSUAL están en [navigation-and-ui.md](ux/navigation-and-ui.md). Define una bandeja de lectura como destino inicial, rutas separadas para importación/configuración/conciliación y acciones futuras condicionadas por estado y permiso.

## Modo debug de datos

`bancos_debug` controla el acceso de datos operativos. Las lecturas de referencia al ERP se hacen siempre en `public.*`, tanto en debug como en producción. Las mutaciones ERP se obtienen mediante `tablaEscrituraErp()`: van a `global_temp.*` en debug y a `public.*` en producción. Las tablas propias de BANCOS usan `global_temp.bancos_*` en debug y `global_prod.bancos_*` en producción. Los repositorios deben obtener las tres rutas desde `AppBancos\Infrastructure\EsquemaBancos`; no pueden interpolar esquemas. El bootstrap rechaza `bancos_debug = true` cuando `entorno = prod`. El Hub de identidad y permisos continúa en `global_prod.hub_*` en ambos modos.

La migración `014_bancos_preparar_debug_global_temp.sql` crea clones **vacíos** de las tablas bancarias y ERP requeridas, recrea las FK operativas y reemplaza los defaults de secuencias por secuencias de `global_temp`; por eso una mutación de prueba no modifica filas ni avanza secuencias productivas. El perfil de conexión de debug debe recibir escritura sólo sobre `global_temp`; esa restricción de privilegios la administra el DBA.

La primera implementación de ese contrato es `AppBancos\Repository\BandejaMensualRepository`: recibe `cuenta_bancaria_id`, período, filtros cerrados, cursor `(fecha, id)` y límite de 1 a 100; construye los nombres operativos desde `EsquemaBancos` y enlaza todos los valores como parámetros PDO. `ContextoBandejaMensualRepository` obtiene desde las importaciones canónicas las cuentas/períodos disponibles y completa sus etiquetas con lecturas ERP. El detalle completo se carga mediante consultas por lote sobre los movimientos visibles, evitando N+1. Ninguno escribe ERP ni estado operativo. `ConsultarBandejaMensual` lo expone en la página interna `?pag=bandeja-mensual`, sometida a la autorización normal del enrutador. El cursor HTTP versión 3 está ligado a cuenta, período y filtros; no se acepta el par de ordenamiento desde el cliente. Sus fixtures y prueba de integración están en `015_bancos_debug_fixtures.sql`, `017_bancos_debug_cargar_casos_sombra.sql` y `tests/BandejaMensualRepositoryIntegrationTest.php`.

El flujo de trabajo mensual conserva en las tablas propias las asociaciones, reservas y borradores cuando pasa a `PARA_CERRAR`; no produce todavía efectos ERP. Sólo un caso de uso de cierre, autorizado por Hub, aplica el efecto definitivo. Volver a `ABIERTO` descarta esa preparación mediante una transacción y deja auditoría; `CERRADO` es terminal. La consulta y exportación del histórico no son responsabilidad de esta aplicación.

`PrepararMovimientoMensual` es la primera aplicación concreta de esa frontera. El controlador resuelve sesión, CSRF y autorización; el caso de uso valida el contexto y delega idempotencia/transacción; `MovimientoMensualRepository` bloquea el movimiento, calcula su estado vigente desde el último historial —o desde la importación si aún no hay eventos— e inserta `PARA_CERRAR`. La acción está deliberadamente cerrada cuando `BANCOS_DEBUG` es falso.

`RevertirPreparacionMovimientoMensual` aplica la transición inversa con motivo obligatorio. Tras bloquear el mismo agregado, desactiva —sin borrar— asociaciones, reservas y borradores, conserva sus líneas e inserta el evento `ABIERTO`. El historial de asignación y la auditoría identifican al operador y el motivo; un fallo en cualquier descarte revierte todo el cambio.

`AsociarValorMovimientoMensual` incorpora la primera preparación de recursos. El importe pertenece al movimiento y no al request. `MovimientoMensualRepository` bloquea el agregado, valida el valor ERP de sólo lectura y crea reserva/asociación en la misma transacción. La exclusividad tiene dos niveles: un recurso activo no puede pertenecer a movimientos distintos y un movimiento no puede tener más de una asociación/reserva activa del mismo tipo. Los índices parciales resuelven también carreras entre conexiones; las verificaciones previas sólo producen mensajes más claros.

`CrearBorradorMovimientoMensual` valida y normaliza el documento contable antes de abrir la transacción. Usa suma decimal por cadenas para no perder precisión, exige balance y valida nodo/cuentas en el gateway de lectura ERP. El repositorio crea cabecera, líneas, reserva y asociación después de bloquear el movimiento. Las líneas son parte del borrador histórico; una reversión desactiva la cabecera y sus vínculos, pero no las elimina.

`BuscarRecursosErpMovimientoMensual` concentra las consultas asistidas previas a confirmar una preparación. Valores y asientos aplican disponibilidad contextual; nodos y cuentas permiten buscar por atributos legibles. El nodo elegido sólo prioriza cuentas y no limita el catálogo, de acuerdo con la evidencia migrada. Los endpoints son `GET`, reutilizan el permiso de la mutación correspondiente, validan movimiento/cuenta/período y no escriben en ERP ni en tablas operativas.

`GestionarMensajesMovimientoMensual` separa publicación y lectura como comandos idempotentes auditados. `MensajeriaMovimientoRepository` bloquea el movimiento dentro del contexto de cuenta/período, toma siempre la identidad del operador y usa recepciones dispersas por usuario. La bandeja recibe el usuario actual sólo para proyectar `leido` y `mensajes_no_leidos`; sin usuario conserva el contrato de lectura usado por herramientas y pruebas.

`AsignarResponsableMovimientoMensual` valida por separado operador y destino. El repositorio resuelve el estado vigente bajo bloqueo, exige que el destino tenga acceso efectivo general a la aplicación e inserta un evento de historial sin transición de estado. Esto permite que el catálogo de responsables siga las altas y bajas de Hub sin una lista paralela dentro del módulo.

`PrevisualizarImportacionExtracto` coordina la validación de contexto con `ImportacionExtractoRepository` y el parser puro `ParserExtractoDelimitado`. El parser no conoce PDO ni sesión, acepta únicamente el contrato delimitado versionable y produce datos normalizados, métricas y errores con fila de origen. La previsualización es de sólo lectura. `ConfirmarImportacionExtracto` vuelve a analizar los bytes y entrega sólo filas completamente válidas al repositorio; `EjecutorComandoIdempotente` controla la transacción, solicitud y auditoría. El repositorio valida de nuevo la configuración, serializa por hash con un bloqueo transaccional y crea lote y movimientos sin DML sobre ERP.

`GenerarReporteErroresImportacion` reutiliza esa previsualización autoritativa y solicita al parser el detalle completo sólo para la respuesta descargable. Construye el CSV en un flujo temporal de memoria, sin archivos persistentes ni DML; la respuesta HTML normal mantiene su límite de 100 errores para no ampliar el JSON ni el DOM.

`ClasificadorImportacionExtracto` recibe las filas normalizadas y las reglas leídas por el repositorio. Compara `codigo_extracto` en forma exacta después de recortar y normalizar mayúsculas, y aplica sólo reglas de sentido `A` o del crédito/débito de la fila. Si queda un subtipo candidato lo asigna; si quedan cero o varios conserva el resultado explícito y no inventa un subtipo. La búsqueda asistida de valores usa el mismo código, configuración y sentido para limitar candidatos; el ID exacto sigue siendo la vía manual para excepciones.
