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

La interfaz consume contratos API y no conoce IDs heredados, SQL ni códigos ERP. `hQuery` queda limitado a funciones UI; su estado técnico vive en `vars.hquery` y el contexto funcional bajo `sesion`, `app`, `data` y `estado`. El detalle de CTEs, joins, paginación, reservas concurrentes e índices está en [query-plan.md](database/query-plan.md).

La navegación y los límites de UX resultantes del relevamiento de BANCOS y BANCOS_MENSUAL están en [navigation-and-ui.md](ux/navigation-and-ui.md). Define una bandeja de lectura como destino inicial, rutas separadas para importación/configuración/conciliación y acciones futuras condicionadas por estado y permiso.

## Modo debug de datos

`bancos_debug` controla el acceso de datos operativos. Las lecturas de referencia al ERP se hacen siempre en `public.*`, tanto en debug como en producción. Las mutaciones ERP se obtienen mediante `tablaEscrituraErp()`: van a `global_temp.*` en debug y a `public.*` en producción. Las tablas propias de BANCOS usan `global_temp.bancos_*` en debug y `global_prod.bancos_*` en producción. Los repositorios deben obtener las tres rutas desde `AppBancos\Infrastructure\EsquemaBancos`; no pueden interpolar esquemas. El bootstrap rechaza `bancos_debug = true` cuando `entorno = prod`. El Hub de identidad y permisos continúa en `global_prod.hub_*` en ambos modos.

La migración `014_bancos_preparar_debug_global_temp.sql` crea clones **vacíos** de las tablas bancarias y ERP requeridas, recrea las FK operativas y reemplaza los defaults de secuencias por secuencias de `global_temp`; por eso una mutación de prueba no modifica filas ni avanza secuencias productivas. El perfil de conexión de debug debe recibir escritura sólo sobre `global_temp`; esa restricción de privilegios la administra el DBA.

La primera implementación de ese contrato es `AppBancos\Repository\BandejaMensualRepository`: recibe únicamente `cuenta_bancaria_id`, período, cursor `(fecha, id)` y límite de 1 a 100; construye los nombres operativos desde `EsquemaBancos` y enlaza todos los valores como parámetros PDO. No escribe ERP ni estado operativo. `ConsultarBandejaMensual` lo expone en la página interna `?pag=bandeja-mensual`, que queda sometida a la autorización normal del enrutador. El cursor HTTP es un token firmado, ligado a cuenta y período; no se acepta el par de ordenamiento desde el cliente. Sus fixtures y prueba de integración están en `015_bancos_debug_fixtures.sql` y `tests/BandejaMensualRepositoryIntegrationTest.php`.

El flujo de trabajo mensual conserva en las tablas propias las asociaciones, reservas y borradores cuando pasa a `PARA_CERRAR`; no produce todavía efectos ERP. Sólo un caso de uso de cierre, autorizado por Hub, aplica el efecto definitivo. Volver a `ABIERTO` descarta esa preparación mediante una transacción y deja auditoría; `CERRADO` es terminal. La consulta y exportación del histórico no son responsabilidad de esta aplicación.
