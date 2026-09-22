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

## Asociación segura de valor - 2026-09-10

Se implementó `POST api.php?accion=movimiento.asociar-valor` y el formulario por ID exacto dentro del detalle de movimientos `ABIERTO`. El importe se toma del movimiento, no del request. La API valida existencia, estado, monto y disponibilidad del valor; bloquea el movimiento y crea reserva/asociación dentro de la transacción idempotente. No modifica el ERP ni aplica todavía sugerencias automáticas.

La migración reversible `022` agregó los índices únicos parciales por movimiento/tipo después de comprobar cero duplicados activos en producción y sandbox. También registró el permiso granular sin asignaciones. La prueba integrada enfrentó dos movimientos por el mismo valor y confirmó una sola reserva/asociación, auditoría completa, reintento seguro y ausencia de filas parciales; la limpieza dejó `global_temp` sin datos artificiales. El siguiente incremento es el borrador contable multílínea.

## Borrador contable multílínea - 2026-09-10

Se caracterizaron 5.169 borradores activos migrados: todos balanceados, entre 2 y 141 líneas, pero sólo 2.338 coinciden con el importe del extracto. El contrato nuevo exige balance y 2 a 200 líneas, admite cuentas repetidas y no fuerza igualdad con el movimiento. Los importes se normalizan a cinco decimales con suma exacta por cadenas.

Se implementó `POST api.php?accion=movimiento.crear-borrador` y un editor dinámico en el drawer. La acción valida nodo y cuentas en ERP de sólo lectura y crea cabecera, líneas, reserva, asociación y auditoría en una transacción idempotente de `global_temp`. La migración `023` registró el permiso sin usuarios nuevos. La prueba rechazó un desbalance de `0.00001`, confirmó persistencia y exclusividad y limpió todas sus filas. El siguiente incremento seguro es asociar asientos ERP existentes sin materializarlos ni modificarlos.

## Asociación segura de asiento ERP - 2026-09-10

Se caracterizaron 911.337 asociaciones activas: 441.654 exclusivas con coincidencia exacta de importe y 469.683 compartidas sobre 9.481 asientos. Todos los asientos asociados tenían líneas balanceadas y no existían mezclas de modalidad. Con esa evidencia se implementó `POST api.php?accion=movimiento.asociar-asiento`, limitado a movimientos `ABIERTO` y a debug.

La operación valida el asiento y sus líneas mediante lectura de ERP, serializa el recurso con un advisory lock, deriva el monto del extracto y crea reserva/asociación/auditoría en una transacción idempotente. La migración `024` registró el permiso sin asignaciones. La prueba integrada confirmó exclusividad, dos usos compartidos, rechazo de mezcla y limpieza del sandbox. El próximo incremento visible es sustituir IDs manuales por búsquedas asistidas empezando por los recursos de estas acciones.

## Búsqueda asistida de valores y asientos - 2026-09-10

Se agregaron dos consultas GET autenticadas que reutilizan los permisos de asociación. Valores se acota por cuenta, estado, monto y disponibilidad; asientos se acota por fecha, balance, importe y modalidad exclusiva/compartida. La interfaz presenta hasta 12 opciones seleccionables, mantiene el ID exacto como alternativa y no aplica puntaje ni selección automática.

Las consultas sólo leen ERP y tablas operativas, vuelven a validarse al confirmar y proyectan los IDs ERP como texto para preservar `bigint` en JavaScript. La prueba integrada verificó opciones reales, límites, contexto ABIERTO y ausencia de cambios en asociaciones/reservas. El siguiente incremento de asistencia corresponde a nodo y cuentas contables del borrador.

## Búsqueda asistida de nodos y cuentas - 2026-09-10

El editor de borradores reemplazó los IDs manuales por selectores buscables, conservando el ingreso de un ID exacto como alternativa. Nodo busca por ID, código y nombre y prioriza el asociado a la cuenta bancaria. Cada línea busca cuentas por ID, código o nombre, conserva IDs `bigint` como texto y muestra nodo e imputabilidad antes de seleccionar.

La caracterización evitó una regla incorrecta: 10.777 de 10.801 líneas activas y 5.157 de 5.169 borradores usan cuentas de un nodo distinto al de la cabecera; además existen 10 líneas con cuentas no imputables. Por eso el nodo y la imputabilidad ordenan e informan, pero no filtran. Los nuevos `GET` reutilizan `movimiento-crear-borrador`, no agregan DDL ni DML, y la prueba integrada confirmó resultados, límites, validación contextual y ausencia de cambios operativos. El siguiente incremento funcional vuelve al circuito colaborativo de bajo riesgo: mensajes, lecturas y asignación de responsable.

## Mensajería y lecturas - 2026-09-10

Se implementaron `movimiento.agregar-mensaje` y `movimiento.marcar-mensajes-leidos` con identidad de sesión, contexto cuenta/período, CSRF, idempotencia y auditoría. La publicación fija `USUARIO`, admite conversación en cualquier estado y registra al emisor como lector propio. La lectura crea o completa sólo las recepciones del operador para mensajes ajenos; no materializa listas de destinatarios.

La decisión surge de los datos: los 13 mensajes productivos pertenecen a movimientos hoy cerrados y no existe ninguna recepción migrada. La bandeja ahora proyecta pendientes por usuario y ofrece ambas acciones en el drawer. La migración `025` registró los dos permisos sin asignaciones nuevas y fue aplicada correctamente. La prueba integrada usó `mcaballero` como emisor y `hvega` como lector, verificó reintentos, contexto, auditoría, render antes/después y limpieza. El siguiente incremento es asignar/reasignar responsable una vez cerrado el conjunto válido de destinatarios.

## Asignación de responsable - 2026-09-10

El conjunto válido se cerró con la fuente de autoridad de Hub: sólo pueden elegirse cuentas activas con permiso general efectivo para APP BANCOS. Actualmente devuelve exactamente `mcaballero` y `hvega`; futuras altas aparecerán sin modificar código. `movimiento.asignar-responsable` conserva el estado vigente, inserta historial únicamente ante un cambio y separa responsable de operador mediante historial, observación técnica y auditoría.

La migración `026` registró el permiso granular sin concederlo a usuarios nuevos y fue aplicada. La prueba integrada confirmó asignación, reasignación, idempotencia, rechazo de un usuario externo, conservación del estado, operador y catálogo exacto, y eliminó todos sus datos artificiales. Con esto queda completo el primer bloque colaborativo de bajo riesgo. El próximo incremento corresponde a importación/configuración o, antes de eso, a cerrar los prerrequisitos operativos de corte que siguen abiertos.

## Previsualización de importación - 2026-09-10

La etapa 6 comenzó con una pantalla independiente de importaciones y `POST importacion.previsualizar`. El parser acepta CSV/TSV UTF-8 de hasta 5 MB y 10.000 filas, detecta tabulación, punto y coma o coma, normaliza tres formatos de fecha y formatos monetarios regionales, exige un único sentido por fila y devuelve hash SHA-256, totales, muestra y errores sin persistir.

El relevamiento confirmó un máximo histórico de 5.892 movimientos por lote, 2.357 importaciones sin hash migrado y múltiples configuraciones activas en cada una de las 70 cuentas vinculadas. Por ello el límite deja margen, el hash rige sólo nuevos lotes y la selección de configuración será obligatoria al confirmar. La migración `027` registró destino/permiso y concedió la ruta solamente a `mcaballero` y `hvega`. La prueba cubre archivo válido, errores por fila, formatos regionales, cuenta configurada, hash y ausencia de DML. El siguiente incremento es persistir lote y movimientos en una transacción idempotente después de seleccionar configuración.

## Confirmación de importación - 2026-09-10

La pantalla ahora filtra las configuraciones activas por cuenta y exige una selección explícita antes de previsualizar. `POST importacion.confirmar` vuelve a parsear el archivo, valida nuevamente configuración/cuenta dentro de la transacción y crea el lote `ABIERTO` con todos sus movimientos, filas de origen, hash, versión y operador. Un advisory lock evita carreras y el índice existente rechaza el mismo hash para cuenta/período/versión aunque se use otra clave.

La migración reversible `028` registró `importacion-confirmar` sin asignaciones nuevas y se aplicó dos veces para validar repetibilidad. La prueba integrada confirmó creación atómica, reintento idempotente, auditoría única, conflicto por hash, rechazo de configuración inválida y limpieza del sandbox. El siguiente incremento de la etapa 6 es definir cómo las reglas de clasificación impactan el alta y comenzar el mantenimiento de configuraciones.

## Clasificación durante la importación - 2026-09-10

La caracterización de 89.571 reglas detectó 16.562 grupos código/sentido que apuntan a varios subtipos. Se descartó por eso la asignación por primera coincidencia del legado. El clasificador nuevo normaliza código y sentido, informa resultados `UNIVOCA`, `MULTIPLE`, `SIN_REGLA` y `SIN_CODIGO`, y sólo persiste el subtipo en el primer caso. `validar_automaticamente` se informa pero no desambigua.

La búsqueda asistida de valores ahora limita candidatos a los subtipos de la regla cuando existe código de extracto. La migración reversible `029` cargó 165 reglas reales de las configuraciones sombra en `global_temp` y fue verificada como repetible. Las pruebas cubren sentido, normalización, ambigüedad, persistencia unívoca, búsqueda restringida y limpieza. El siguiente incremento es el reporte descargable de errores o el primer mantenimiento seguro de configuraciones.

## Reporte de errores de importación - 2026-09-10

La previsualización inválida ahora habilita una descarga CSV con todos los errores por fila, aunque la lista visible continúe acotada a 100. El servidor vuelve a validar cuenta, configuración, período y archivo, genera el contenido en memoria con BOM UTF-8 y separador punto y coma, y responde como adjunto sin persistir datos ni requerir clave idempotente.

El endpoint `importacion.reporte-errores` reutiliza el permiso de previsualización y mantiene el límite general de 5 MB y 10.000 movimientos. La prueba verifica el detalle completo, cabecera, codificación, nombre seguro, rechazo de archivos válidos y ausencia de DML. El siguiente incremento de la etapa 6 es el primer mantenimiento seguro de configuraciones.

## Validación automática de reglas - 2026-09-11

Se implementó `?pag=configuraciones` para consultar configuraciones activas y todas sus reglas. El alcance inicial se restringe a alternar `validar_automaticamente`: no permite altas, bajas ni cambios de código, sentido o subtipo, porque esas operaciones todavía no cuentan con una política de versionado o baja lógica.

`configuracion.actualizar-validacion-automatica` exige JSON, CSRF, idempotencia, sandbox y permiso específico; bloquea la regla dentro de la configuración indicada y audita valor anterior, valor solicitado y operador. La caracterización confirmó 89.571 reglas productivas, sólo 3.908 automáticas y un máximo de 55 reglas por configuración. La migración reversible `030` se aplicó dos veces, asignó la navegación sólo a `mcaballero` y `hvega` y dejó el permiso granular sin asignaciones. El próximo incremento debe definir alta/versionado o baja lógica antes de ampliar las mutaciones de reglas.

## Versionado y baja lógica de reglas - 2026-09-17

Se definió una política no destructiva: `activo` determina participación operativa, `version` crece por reemplazo y `reemplaza_regla_id` conserva la cadena. Editar desactiva la fila vigente y crea otra; retirar sólo desactiva. La unicidad pasa a considerar exclusivamente reglas activas y un bloqueo de configuración serializa las mutaciones concurrentes.

La pantalla permite crear, editar y retirar con motivo obligatorio, y las APIs usan sesión, permiso, CSRF, idempotencia y auditoría. Importación y búsqueda asistida filtran versiones inactivas. Las migraciones `031` y `032` se aplicaron repetidamente contra el sandbox; `031` contempla además el índice heredado con nombre autogenerado de `global_temp`. La regresión completa pasó con 28 pruebas PHP y la prueba JavaScript, incluidas las integraciones de versionado y validación automática. Las pruebas limpiaron sus filas operativas artificiales.

## Vínculos entre cuentas y configuraciones - 2026-09-21

Se eliminó la autorización implícita derivada de importaciones históricas: sólo `bancos_configuracion_cuenta.activo=true` habilita nuevas cargas. Producción ya tenía 1.651 vínculos y ningún par importado faltante; los cuatro pares faltantes del sandbox se sembraron como `IMPORTACION`. La interfaz permite vincular, desvincular y reactivar con motivo obligatorio, baja lógica, sesión, permiso, CSRF, idempotencia y auditoría.

Las migraciones repetibles `033` y `034` agregaron vigencia/procedencia y el permiso `configuracion-administrar-cuentas` sin asignaciones directas. La prueba integrada confirmó alta, reintento, bloqueo de importación tras la baja, reactivación sin duplicado y limpieza del conjunto artificial.

## Mapeos de cuentas contables - 2026-09-21

La caracterización de 88.951 filas productivas confirmó una sola cuenta por configuración/subtipo y ningún uso efectivo de `regla_orden`. La migración `035` agregó vigencia y cadena de versiones, impuso esa unicidad sólo sobre filas activas y cargó 165 mapeos sombra. `036` registró `configuracion-administrar-mapeos` sin asignaciones directas.

La pantalla permite crear, editar y retirar mapeos con motivo. Los comandos validan subtipo y cuenta en ERP de sólo lectura, bloquean la configuración y usan CSRF, idempotencia y auditoría. La prueba integrada confirmó las dos versiones, baja lógica, exclusión de retirados y limpieza.

La regresión completa quedó en 30 pruebas PHP más JavaScript, sin fallos en ese corte.

## Reglas de responsables automáticos - 2026-09-21

Se detectó que `010` no cargó reglas porque `bancos_mes_raau.id_usuario` pertenece a `login_users`, no a Hub. Hay 9.320 reglas legacy: 5.674 traducibles a seis cuentas Hub activas, pero ninguna tiene acceso vigente a APP BANCOS; las demás apuntan a cuentas eliminadas, aliases sin correspondencia o `!default`. No se concedieron accesos ni se inventaron responsables.

`037` agregó versión y unicidad activa por configuración/subtipo, y `038` registró `configuracion-administrar-asignaciones` sin asignaciones directas. La pantalla administra alta, reemplazo y retiro con motivo. La confirmación de importación revalida el acceso efectivo y asigna únicamente movimientos con clasificación unívoca, registrando su historial inicial `ABIERTO`.

La regresión vigente quedó en 31 pruebas PHP más JavaScript, sin fallos.

## Preflight de cierre - 2026-09-21

La etapa 7 comenzó sin habilitar efectos ERP. El relevamiento del legado confirmó que el cierre masivo cambiaba estados después de liquidar valores no cheque, crear asientos desde borradores o no hacer nada para asientos existentes; los cheques pendientes abrían una conciliación separada. En producción, los 947.077 cierres migrados se distribuyen en 889.361 con asiento, 39.724 sin asociación, 17.991 con valor y uno con valor más asiento. Esa evidencia impide bloquear por defecto el caso sin asociación o la combinación valor/asiento.

Se implementó el GET `movimiento.prevalidar-cierre`, protegido por `movimiento-cerrar`, con plan de efectos, advertencias y bloqueos. `039` registró el permiso sin usuarios generales. No existe endpoint de ejecución y no se escribe ERP. Las consultas tipadas usan los índices parciales existentes y fueron medidas con `EXPLAIN (ANALYZE, BUFFERS)`. La regresión vigente quedó en 32 pruebas PHP más JavaScript, sin fallos.

## Cierre sin efectos ERP - 2026-09-21

Se agregó `movimiento.cerrar-sin-erp`, disponible sólo en sandbox. Después de reclamar la clave idempotente bloquea el movimiento, repite el preflight y admite exclusivamente ausencia de asociaciones o un asiento ERP existente que no necesita cambios. Registra `CERRADO`, operador y auditoría en la misma transacción; valores y borradores continúan bloqueados. La interfaz exige preflight exitoso y confirmación explícita antes de mostrar y ejecutar el cierre.

La integración comprobó un único cierre ante reintento, el caso con asiento, rollback completo ante un valor y contención con dos conexiones mediante `FOR UPDATE`/`lock_timeout`. Tras liberar el bloqueo, la misma clave pudo reintentarse porque el intento fallido no dejó solicitud parcial. La regresión vigente quedó en 33 pruebas PHP más JavaScript, sin fallos.

## Cierre de valores no cheque en sandbox - 2026-09-21

Se agregó `movimiento.cerrar` como contrato de la interfaz. Mantiene bloqueo, preflight, permiso, CSRF, idempotencia y auditoría, y admite además el efecto `VALOR/LIQUIDAR_ESTADO_7`. `ValorErpGateway` compara el valor productivo de sólo lectura con su copia `global_temp`, bloquea esta última y cambia exclusivamente su estado a `7` dentro de la misma transacción que registra `CERRADO`.

Para que el circuito sea completo, `movimiento.asociar-valor` crea ahora esa copia sandbox dentro de su propia transacción cuando el valor seleccionado desde ERP aún no existe en `global_temp`. Si encuentra una copia con estado distinto, rechaza la asociación; si falla cualquier operación posterior, la inserción se revierte.

La prueba integrada confirmó reintento sin duplicación, efecto y evidencia de auditoría, inmutabilidad de `public.valor` y rollback completo ante una copia sandbox desfasada. Cheques y borradores siguen bloqueados. La identidad Hub del operador permanece en auditoría; no se escribe `valor.usuario_modificacion` hasta acordar la identidad ERP. La regresión vigente quedó en 34 pruebas PHP más JavaScript, sin fallos.

## Materialización de borradores en sandbox - 2026-09-21

`movimiento.cerrar` admite ahora `BORRADOR_ASIENTO/CREAR_ASIENTO`. El gateway bloquea y revalida el borrador, crea la cabecera y sus movimientos en `global_temp`, enlaza el asiento, marca el borrador `CERRADO` y luego registra el cierre del movimiento dentro de la misma transacción idempotente. El flujo legacy de asiento manual no creaba `operacion`; se conserva esa regla. El modelo es el nombre del asiento, mientras número y usuarios ERP quedan nulos en lugar de heredar el usuario fijo `16529`.

La FK sandbox a `public.asiento` impedía persistir el resultado aislado. `040` agrega unicidad sobre el ID de `global_temp.asiento` y redirige sólo esa FK. Se comprobó aplicación repetida, rollback protegido y reaplicación. La nueva integración verificó las dos líneas, balance, fecha, nodo, nombre, enlace, estados e idempotencia, y limpió todas las filas artificiales. La regresión vigente quedó en 35 pruebas PHP más JavaScript, sin fallos.

## Atomicidad combinada y límite de fusión - 2026-09-21

Se agregó cobertura integral para valor+borrador. El caso exitoso liquida la copia del valor, crea asiento/líneas, enlaza el borrador y cierra el movimiento con alcance `VALOR_Y_BORRADOR_ASIENTO`; repetir la clave no duplica efectos. El caso de compensación presenta un asiento ya materializado cuyas líneas no coinciden: el valor alcanza a actualizarse dentro de la transacción, el gateway del borrador rechaza el asiento y el rollback restaura el valor y elimina la idempotencia parcial.

La fusión no se implementó por inferencia. La caracterización productiva encontró 5.169 borradores activos y 9.481 asientos compartidos, pero cero movimientos activos con asiento+borrador y cero con valor+borrador. El esquema actual no identifica la lista de asientos fuente a revertir. Hasta definir ese contrato, `DESTINO_CONTABLE_AMBIGUO` sigue bloqueando asiento+borrador. La regresión vigente quedó en 36 pruebas PHP más JavaScript, sin fallos.

## Preflight de conciliación de cheques - 2026-09-21

La etapa 8 empezó sin habilitar DML. El contraste de BANCOS y BANCOS_MENSUAL confirmó cuatro subtipos mensuales (`13`, `14`, `10070`, `10071`), exclusión de estados `20`/`36`, liquidación del origen a `7` y creación futura de operación, valor resultante, relaciones, concepto `376`, asiento y movimientos. No se heredó el usuario fijo `16529` ni la falta de transacción del legado.

Se agregó `cheque.prevalidar-conciliacion`, protegido por el nuevo permiso `cheque-conciliar`. Valida contexto `PARA_CERRAR`, única asociación de valor, reserva, ausencia de destino contable ambiguo, estado y subtipo; informa diferencia, vínculos ERP y eventual conciliación ya registrada. La consulta siempre devuelve `ejecutable_ahora=false` y no escribe; el comando separado `cheque.conciliar` vuelve a validar todo dentro de su transacción antes de ejecutar. `041` fue aplicada repetidamente sin asignaciones directas. La integración cubre cheque pendiente, liquidado y excluido, e inmutabilidad operativa/ERP.

El preflight fue ampliado para resolver las dos cuentas contables canónicas: base bancaria desde la configuración de la importación y valor desde el mapeo activo del subtipo. También informa nodo, moneda, diferencia absoluta/porcentual y la tolerancia histórica del 1% únicamente como evidencia. Las diferencias no exactas quedan bloqueadas porque el legado no define una cuenta segura para ellas. La caracterización productiva halló cuatro cheques activos pendientes; todos tienen ambas cuentas y monto exacto. La prueba integrada agrega resolución de cuentas y bloqueo de una diferencia artificial, manteniendo sólo lectura.

## Conciliación transaccional en sandbox - 2026-09-21

Se agregó `cheque.conciliar`, limitado por configuración a debug. Bloquea el movimiento, repite el preflight y serializa por cheque antes de materializar operación `101/1/101`, dos relaciones operación-valor, valor `124/79/4`, concepto `376`, asiento, dos movimientos balanceados, estado `7` de la copia y registro de conciliación. El movimiento permanece `PARA_CERRAR`; el preflight de cierre reconoce luego `SIN_CAMBIOS_YA_CONCILIADO`. Número y usuarios ERP no se inventan, y la cuenta Hub queda en auditoría.

La alternativa inicial de retargetizar las FK sandbox fue descartada al comprobar que PostgreSQL requería bloquear las tablas `public` referenciadas. Las sesiones de migración propias se cancelaron inmediatamente. `042` usa en cambio un reemplazo seguro de la tabla sandbox vacía, conserva la definición anterior para rollback y crea FK sólo hacia `global_temp`. Aplicación repetida y rollback quedaron probados sin bloquear ERP. La integración valida efectos, balance, idempotencia, inmutabilidad de `public.valor`, continuidad hacia cierre y limpieza. La regresión vigente quedó en 38 pruebas PHP más JavaScript.

La prueba de conciliación fue reforzada antes de considerar tráfico productivo. Un trigger limitado a la clave artificial de la prueba provoca una excepción en el último `INSERT`, después de los efectos contables; se verificó que no quedan operación, valor, asiento, conciliación, cambio de estado ni solicitud idempotente. Con conexiones independientes se comprobó además que una misma clave en proceso se serializa, que el bloqueo consultivo del cheque produce contención recuperable y que una segunda clave posterior no duplica la conciliación. Todos los recursos artificiales se eliminan al finalizar.

El mismo escenario cubre ahora el circuito funcional completo. Después de conciliar ejecuta `movimiento.cerrar`, repite su clave y verifica un único historial `CERRADO`, alcance `SIN_EFECTO_ERP`, cero efectos aplicados y presencia de `SIN_CAMBIOS_YA_CONCILIADO`. Una instantánea de conteos antes y después confirma que el cierre no duplica ni elimina operación, relaciones, valores, concepto, asiento, líneas o conciliación.

La bandeja mensual proyecta ahora `bancos_conciliacion_cheque` por lote para los movimientos visibles. El drawer agrega una sección de trazabilidad con estado, fecha, operador Hub, valor origen, operación, valor resultante, asiento, modalidad y evidencia. No se agregó un endpoint ni una consulta desde el navegador. La integración del comando lee luego el movimiento por `BandejaMensualRepository` y confirma que la proyección contiene exactamente los IDs recién creados; el fixture visual cubre el render completo.

La consulta principal de bandeja deriva `conciliacion_codigo`: prioriza un registro `CONCILIADO`, identifica como `PENDIENTE` una asociación activa a los subtipos `13`, `14`, `10070` o `10071` cuyo valor ERP no está en estado `7`, y clasifica el resto como `NO_REQUIERE`. La tabla muestra una etiqueta y el formulario permite filtrar los tres valores. `ConsultarBandejaMensual`, el repositorio, JavaScript, paginación y firma de cursor comparten el mismo contrato. Las pruebas cubren las tres transiciones observables y el rechazo de valores inventados.
