# Sesión de descubrimiento - 2026-08-25

Se incorporó BANCOS_MENSUAL y se corrigió el alcance de BANCOS a conciliación de cheques. SGUA fue descartada como integración futura de identidad/permisos: se adopta `nueva_app` como referencia y hQuery como futura librería de funciones comunes.

En esta sesión también se copió la estructura base de `nueva_app` hacia `app-bancos` manteniendo intacto el material preexistente. Se agregaron todos los componentes de bootstrap, carpetas compartidas y dependencias en modo incremental (sin sobrescritura), de modo que la base está lista para configurar permisos de Hub y comenzar desarrollo funcional.

Se relevó en modo de solo lectura el catálogo productivo de 21 tablas `bancos_*` y `bancos_mes_*`. El hallazgo principal es la falta generalizada de PK/FK y la existencia de 121 restricciones UNIQUE redundantes en `bancos_mes_periodos_cargados_cuenta`.

La validación agregada confirmó que `(id_periodo, serial_seq)` es única, mientras que `id_movimiento` tiene 1.055 valores duplicados; también confirmó los tres estados vigentes, dos decimales de precisión y el uso combinado de configuración global y por cuenta. Se actualizó el modelo destino y se incorporó `docs/database/production-validation.md`. Como convención de diseño se definió que las nuevas tablas estarán en `global_prod`, conservarán el prefijo `bancos_` y usarán nombres en español. Permanecen pendientes sólo las decisiones funcionales que los datos no conservan: transiciones, asociaciones múltiples, reversas/fusiones y retención. No se modificó producción.

Hoy también se materializó y ejecutó correctamente el primer paquete de DDL en `app/sql/migraciones/004_bancos_tablas_auxiliares.sql`, cubriendo tablas para configuración, importación de extractos, movimientos, historial, asociaciones, reservas, borradores, mensajes y conciliación de cheque. La corrida final se realizó el 2026-08-25 contra `ftweb` en `localhost:5500` con usuario `postgres` y finalizó con `MIGRACION_OK`.

También se ejecutó `app/sql/migraciones/005_bancos_campos_zetti.sql` para cambiar nombres de columnas `*_erp_*` a `*_zetti_*` en `global_prod.bancos_*`, y quedó verificado sin columnas ni constraints residuales con `erp` en esos objetos.

También se relevó el catálogo ERP necesario. La relación entre operación y valor es `operacion_valor`; los importes contables ERP son `numeric(20,5)` y los IDs de valor, operación, asiento, cuenta, entidad y cuenta bancaria son `bigint`, mientras que nodo usa `integer`. El modelo objetivo y `docs/database/erp-structure.md` fueron ajustados en consecuencia. No se modificó producción.

Se planificó la implementación superadora como monolito modular: Extractos Mensuales y Conciliación de Cheques comparten autenticación Hub, configuración, importación, repositorios y gateway ERP, pero conservan casos de uso y reglas contables propios. `docs/database/query-plan.md` documenta consultas con CTEs reducidas —considerando su materialización en PostgreSQL 9.6—, joins por PK, paginación por cursor, reserva/asociación atómica e índices a validar. Esta sesión sólo actualizó documentación; no modificó DDL ni datos.

Se resolvieron las ambigüedades funcionales principales: todos los usuarios autorizados preparan movimientos; el cierre queda limitado a administración/permiso especial; `CERRADO` no se reabre; se admite valor, asiento, borrador multílínea y valor más asiento; la preparación se descarta al volver a `ABIERTO`; la reversa inicial marca `asiento.rev`; la fusión precede al cierre del período; y se conserva todo el histórico. Siguen pendientes el permiso Hub concreto, las validaciones adicionales de preparación y el procedimiento contable final de fusión/generación.

## Actualización post-migración - 2026-08-31

Se informó completada la carga de datos legacy a `global_prod.bancos_*`, incluyendo las migraciones `007` a `013`. La continuación no es otra carga: se debe conciliar el corte, conservar respaldo recuperable, definir convivencia con un único escritor y adoptar primero lecturas con comparación en sombra. Las escrituras ERP permanecen fuera de alcance hasta configurar el permiso de cierre, cerrar el diseño contable y validar integración, idempotencia, concurrencia y rendimiento.

## Modo debug de datos - 2026-08-31

Se preparó el modo `bancos_debug`, desactivado por defecto. Al activarlo, los futuros repositorios BANCOS leerán el ERP en `public`, pero dirigirán mutaciones ERP y tablas operativas a `global_temp`; la identidad y permisos Hub permanecen en `global_prod`. El 2026-09-04 se ejecutó `014_bancos_preparar_debug_global_temp.sql`, creando las estructuras vacías necesarias; se verificaron 49 tablas `bancos_*` y cinco dependencias ERP antes faltantes. Sólo queda configurar un usuario de depuración sin privilegios de escritura sobre `public` ni `global_prod`.

## Validación del sandbox - 2026-09-07

La auditoría de defaults se completó en una transacción de solo lectura: no hay secuencias de `global_temp.bancos_*` que apunten a `public` o `global_prod`. Luego se cargaron fixtures mínimos versionados en `global_temp` y se implementó la primera consulta paginada de extractos usando `EsquemaBancos`; las lecturas ERP siguen en `public`. El próximo incremento expone esa lectura y la compara en sombra con el legado. Sólo queda como prerrequisito de infraestructura el usuario de depuración con permisos de escritura exclusivos en `global_temp`.

## Primera lectura en sandbox - 2026-09-07

Se creó y aplicó `015_bancos_debug_fixtures.sql`: deja una cuenta ERP sólo referenciada desde `public` y cinco movimientos propios en `global_temp`, sin modificar producción. `BandejaMensualRepository` implementa la consulta keyset por cuenta/período y la prueba de integración verificó la primera página de dos filas y la segunda de tres.

## Pantalla inicial de bandeja - 2026-09-07

La bandeja ya se expone como página interna de sólo lectura en `?pag=bandeja-mensual`. `ConsultarBandejaMensual` valida la entrada, limita la página a 100 filas y usa un cursor firmado vinculado a la cuenta y al período, por lo que no acepta fechas o IDs de ordenamiento manipulados por HTTP. El siguiente incremento compara el resultado con el legado en períodos representativos; no habilita mutaciones ERP ni operativas.

## Lote real de sombra - 2026-09-08

Se aplicó `016_bancos_debug_cargar_lote_sombra_2026_07.sql`: toma una única importación ya migrada de `global_prod`, reconstruye sus relaciones internas en `global_temp` y conserva 51 movimientos y 47 asociaciones a asientos que siguen siendo referencias de sólo lectura a `public`. La precondición, los marcadores `SOMBRA-016`, la verificación de conteos y el rollback hacen repetible el lote. La pantalla devolvió tres movimientos y cursor siguiente para esa cuenta/período; lo que resta es la comparación de paridad contra el legado.

## Comparación de sombra inicial - 2026-09-08

`tests/CompararLoteSombra016Test.php` confirmó, en modo de sólo lectura, que no existen diferencias entre el lote legacy, `global_prod` y `global_temp`: 51 movimientos y 47 asociaciones de asiento. La prueba usa la traza de migración para no asociar por el `id_movimiento` legacy ambiguo. El siguiente lote debe cubrir eventos que éste no contiene: historial, mensajes, valores, borradores y excepciones.

## Casos especiales en sandbox - 2026-09-08

`017_bancos_debug_cargar_casos_sombra.sql` cargó dos movimientos seleccionados por su cobertura: dos mensajes, una asociación a valor, un borrador y dos líneas contables. El lote es idempotente y escribe sólo en `global_temp`; la comparación automática de esos casos y la selección de una excepción quedan como siguiente incremento.

`CompararCasosSombra017Test.php` verificó esos campos frente a `global_prod` y confirmó la presencia de una excepción de asociación a asiento documentada por migración. La próxima mejora visible es exponer esos datos en la bandeja, todavía en modo de sólo lectura.

## Diseño UX/UI - 2026-09-08

Se relevaron las pantallas de usuario y administración de BANCOS_MENSUAL y las tres pestañas de BANCOS. El diseño resultante, documentado en `docs/ux/navigation-and-ui.md`, conserva el alcance operativo pero reemplaza paneles fijos, pestañas sin URL y acciones mezcladas con la grilla por rutas autorizables, contexto de cuenta/período, detalle progresivo y acciones futuras separadas por permiso/estado.

## Sistema visual de Consulta RRHH - 2026-09-09

Se revisaron la vista, los estilos estructurales, los temas, la paleta compartida, las interacciones y las pruebas de `rrhh_consulta`, y se contrastaron con la bandeja actual. El resultado está en `docs/ux/rrhh-style/`: índice, auditoría, fundamentos, layout responsive, componentes, estados/accesibilidad, plan de implementación y QA visual. La decisión central es adoptar el sistema visual sin copiar el dominio ni el JavaScript de RRHH. El tema pasará a ser responsabilidad del shell y los paneles bancarios adoptarán la composición continua y densa de la referencia. No se modificó código de aplicación en esta sesión.

En el incremento siguiente del mismo día se aplicó la especificación. Se incorporaron la paleta y sus aliases calculados, el resolver de temas, el selector compartido del header y cache busting de CSS. La bandeja quedó dividida en `bancos.css`, `tema_componentes.css`, `tema_claro.css` y `tema_oscuro.css`; conserva el formulario en `main`, elimina sombras decorativas y transforma la tabla en filas planas para móvil. El drawer corrige las colisiones con el `header` global, bloquea el fondo, contiene Tab/Shift+Tab y devuelve el foco. Las pruebas PHP/JS pasaron y el fixture se revisó en navegador a 1440 y 390 px, claro/oscuro, con resultados, error y detalle abierto. No se habilitaron escrituras.

Como pulido final, la fila de tema recuperó el layout de la referencia dentro del menú hamburguesa, el encabezado pasó de `NUEVA APP` a `APP BANCOS` y el pie de la tabla ahora informa página, rango y cantidad de registros de la página actual. No se alteró la paginación keyset ni se infiere un total global.

La prueba integrada detectó que esa posición se perdía al navegar con el cursor de la URL porque sólo vivía en `sessionStorage`. Se versionó el cursor firmado para transportar página e inicio, se mantuvo lectura compatible de cursores anteriores y el render pasó a usar exclusivamente los metadatos validados por el servidor. La clave keyset sigue formada por fecha e ID.

Se corrigió además el detalle con páginas cortas: el filtro dinámico del shell establece al `<body>` como bloque contenedor de los elementos fijos, por lo que una sola fila recortaba visualmente el panel y el overlay. El shell conserva ahora al menos `100dvh`, y ambos elementos declaran una altura completa con fallback `100vh`.

## Acceso inicial de APP BANCOS - 2026-09-09

Se creó y aplicó `018_bancos_hub_acceso_inicial.sql` en el entorno conectado por túnel. La migración registra `APP BANCOS` con ID 12, crea el permiso de acceso administrador y el destino interno `bandeja-mensual`, y los asigna directamente a las cuentas activas `mcaballero` y `hvega`. También invalida cualquier ruta heredada del permiso administrador para mantener ese rol limitado a ambos usuarios. La verificación sobre `hub_permisos_efectivos_usuario` confirmó dos administradores y cuatro rutas efectivas en total, todas directas. `app/config.php` ya exige la aplicación 12; se agregó un rollback que desactiva de forma auditable las filas creadas.

## Lectura funcional completa de la bandeja - 2026-09-09

Se reemplazaron los campos manuales de cuenta y período por opciones derivadas de las importaciones del esquema operativo y etiquetas completadas desde el ERP de sólo lectura. La bandeja admite filtros parametrizados por estado, responsable, asociación y mensajes. El cursor versión 3 firma esos filtros además del contexto y la posición. El repositorio evita duplicar filas cuando coexisten asociaciones y carga en cuatro consultas por lote todas las asociaciones, conversaciones, borradores/líneas e historial de los movimientos visibles. Los casos de sombra `017` validan mensajes, asociación y las dos líneas del borrador. No se agregaron mutaciones.

## Infraestructura segura de escritura - 2026-09-09

Se agregó `api.php` como front controller JSON autenticado y el contrato `GET ?accion=csrf`. Las clases comunes separan CSRF, autorización por acción, idempotencia, auditoría y coordinación transaccional. El nivel administrador Hub cubre toda APP BANCOS; un futuro usuario general necesitará el permiso interno exacto. La migración reversible `019` creó las tablas de solicitudes idempotentes y eventos de auditoría en producción y sandbox, sin DML de negocio ni cambios ERP.

La prueba unitaria cubre token y matriz de autorización. La prueba integrada en `global_temp` confirmó que dos solicitudes equivalentes ejecutan el comando una sola vez, que el reintento devuelve la respuesta persistida, que una clave incompatible se rechaza, que una excepción revierte toda fila parcial y que existe un único evento de auditoría. La limpieza dejó ambas tablas del sandbox sin filas. La próxima acción segura es implementar una primera mutación propia de preparación, manteniendo fuera de alcance el cierre y el ERP.

## Primera preparación de movimiento - 2026-09-09

Se implementó `POST api.php?accion=movimiento.preparar` y su acción visible en el detalle de la bandeja. La operación exige sesión, nivel administrador o permiso `movimiento-preparar`, CSRF, JSON e idempotencia. Sólo está disponible con `bancos_debug = true`; bloquea el movimiento dentro de la cuenta/período recibidos y admite exclusivamente `ABIERTO -> PARA_CERRAR`. Inserta historial, actualiza la identidad de modificación y registra auditoría en una sola transacción. No requiere todavía asociaciones/borradores y no escribe ERP.

La migración reversible `020` registró el permiso interno sin asignaciones nuevas. La prueba integrada creó un movimiento temporal aislado, confirmó una sola transición/auditoría ante reintento, rechazó una segunda transición y otro contexto, y limpió todas sus filas. El siguiente incremento seguro es la reversión a `ABIERTO` con motivo y descarte transaccional de preparación.

## Reversión controlada de preparación - 2026-09-10

Se implementó `POST api.php?accion=movimiento.revertir-preparacion` y el formulario con motivo dentro del detalle de un movimiento `PARA_CERRAR`. La acción está limitada a debug, exige nivel administrador o el nuevo permiso granular, CSRF, JSON e idempotencia. Bloquea el movimiento, desactiva asociaciones, reservas y borradores activos, registra `ABIERTO` con motivo/operador y conserva todas las filas históricas. No modifica mensajes, líneas ni ERP.

La migración reversible `021` agregó el permiso interno sin concedérselo a usuarios nuevos. La prueba integrada construyó preparación auxiliar completa y comprobó descarte, conteos, motivo, identidad, reintento y transición inválida; luego dejó el sandbox sin filas de prueba. El próximo incremento funcional seguro es crear y reservar recursos de preparación con garantías de exclusividad concurrente.
