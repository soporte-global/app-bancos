# Guía de uso de APP BANCOS

Esta guía describe las pantallas y acciones disponibles en la versión actual de APP BANCOS. La disponibilidad de cada acción depende del permiso del usuario, del estado del movimiento y del modo operativo de la instalación. La aplicación puede estar conectada a datos reales y, a la vez, trabajar en **modo de prueba**: el aviso superior identifica ese modo.

## 1. Acceso y navegación

Ingresá con tu usuario habitual. La aplicación verifica la sesión y los permisos del Hub. Si la sesión vence, volverás al inicio de sesión. El menú superior permite abrir **Bandeja mensual**, **Importaciones**, **Configuraciones** y **Acerca de**, según los permisos concedidos. Acerca de está disponible para cualquier persona que ya pueda ingresar a APP BANCOS.

El modo de prueba se reconoce por el aviso **MODO DE PRUEBA · los cambios se guardan únicamente en global_temp**. En él se leen cuentas y recursos ERP reales como referencia, pero las operaciones de la app se guardan en el esquema de pruebas. No interpretes una operación hecha allí como un cambio productivo en el ERP.

Si falta una pantalla del menú o una acción del detalle, primero verificá que tu usuario tenga el permiso correspondiente. No todos los usuarios tienen las mismas funciones. El nivel administrador dispone del alcance completo de las acciones habilitadas en la instalación.

## 2. Bandeja mensual: elegir contexto

Abrí **Bandeja mensual**. Elegí una cuenta bancaria y luego un período disponible; la lista de períodos depende de la cuenta. Seleccioná los filtros que necesites y pulsá **Consultar**. La barra de contexto confirma cuenta, período y filtros activos.

La bandeja permite filtrar por estado, responsable, presencia de asociación, mensajes y situación de conciliación de cheque. También podés elegir el límite de filas. Los filtros forman parte de la URL, por lo que una recarga conserva la consulta. **Anterior** y **Siguiente** recorren páginas sin pedir el conjunto completo de movimientos. El enlace de paginación contiene un cursor opaco: no hay que editarlo manualmente.

Cada fila resume fecha, referencia, descripción, crédito o débito, estado, asociación, borrador y último mensaje. **Ver detalle** abre un panel lateral sin perder la posición de la bandeja. En móvil, la misma información se organiza en tarjetas. Si el resultado está vacío, revisá cuenta, período y filtros antes de asumir que faltan importaciones.

## 3. Detalle del movimiento

El panel muestra el contexto completo: asociaciones activas a valor o asiento, borradores y líneas contables, mensajes, historial de estados y, cuando existe, trazabilidad de conciliación de cheque. Cerralo con el botón de la cabecera o con Escape para volver a la lista.

Las acciones visibles cambian según el estado del movimiento. Un movimiento **ABIERTO** puede recibir asociaciones o borradores y pasar a **PARA_CERRAR**. Un movimiento **PARA_CERRAR** puede volver a **ABIERTO** con motivo o entrar al circuito de validación de cierre. **CERRADO** es terminal: no existe reapertura desde la aplicación. Los mensajes y la consulta del historial siguen disponibles en cualquier estado.

## 4. Mensajes y responsable

En **Mensajes**, escribí el texto y pulsá **Publicar mensaje**. La aplicación toma tu identidad de la sesión. Los mensajes ajenos pendientes pueden marcarse como leídos; esa lectura es individual y no marca los mensajes para otros operadores. La conversación puede continuar incluso después del cierre.

En **Responsable**, elegí un usuario del listado y pulsá **Asignar responsable**. Sólo aparecen personas con acceso efectivo a APP BANCOS. La reasignación no cambia el estado del movimiento y queda en el historial. Si repetís el responsable vigente, no se crea otro evento de cambio.

## 5. Asociar recursos y preparar

En un movimiento **ABIERTO**, **Asociar valor ERP** permite buscar valores disponibles o indicar un ID exacto, revisar el candidato y pulsar **Reservar y asociar**. La reserva evita que otro movimiento tome el mismo valor. La asociación por sí sola no modifica el valor ERP.

**Asociar asiento ERP** busca un asiento existente y balanceado. Elegí si será exclusivo o compartido antes de confirmar. La modalidad exclusiva exige la compatibilidad de importe del asiento con el movimiento. Un asiento compartido puede servir a varios movimientos, pero no se mezclan modalidades mientras tenga usos activos. Si alguien ya reservó el recurso, la aplicación informa un conflicto y debés elegir otro o revisar el caso.

**Crear borrador contable** pide nodo, fecha, modelo y entre 2 y 200 líneas. Cada línea lleva importe en Debe o en Haber, nunca en ambos, y los totales deben balancear exactamente. La búsqueda de nodo y cuentas ayuda a seleccionar IDs válidos. Crear el borrador no genera todavía un asiento ERP.

Cuando la preparación esté revisada, pulsá **Marcar para cerrar**. La transición **ABIERTO → PARA_CERRAR** conserva las asociaciones y el borrador. Si necesitás rehacerla, **Volver a abierto** exige un motivo y desactiva las asociaciones, reservas y borradores activos; mensajes e historial permanecen como evidencia. Revisá ese impacto antes de confirmar.

## 6. Cierre y cheques

Para un movimiento **PARA_CERRAR**, **Validar cierre** muestra el resultado del preflight, advertencias, bloqueos y efectos previstos. La validación es de sólo lectura. Sólo cuando el caso es ejecutable y el usuario tiene permiso aparece **Cerrar movimiento**. El cierre se confirma por separado y deja el estado **CERRADO**. La aplicación no admite reabrirlo.

En el sandbox se pueden cerrar casos sin efecto ERP, con un asiento existente válido, con un valor que no sea cheque, con un borrador balanceado o con la combinación admitida por la validación. Algunos casos siguen bloqueados, entre ellos un asiento existente combinado con un borrador cuyo origen de fusión no está definido. El resultado del preflight explica el motivo concreto.

Si el detalle presenta **Conciliación de cheque**, pulsá **Validar conciliación**. Una diferencia contable no exacta, un estado no admitido o vínculos incompletos bloquean la operación. Si la validación permite continuar, **Conciliar cheque** crea la trazabilidad y los efectos de prueba; el movimiento permanece **PARA_CERRAR**. Después ejecutá nuevamente **Validar cierre** y, si corresponde, **Cerrar movimiento**. La bandeja distingue cheque **PENDIENTE**, **CONCILIADO** y **NO_REQUIERE**; el indicador es informativo, no sustituye al preflight.

## 7. Importar un extracto

Abrí **Importaciones**. Seleccioná **Cuenta bancaria**, **Configuración** y **Período**, y elegí un archivo CSV o TSV. Sólo aparecen cuentas y configuraciones activas vinculadas explícitamente. Pulsá **Analizar archivo**; este paso **no crea** un lote ni movimientos.

El archivo debe estar en UTF-8, pesar como máximo **5 MB** y contener como máximo **10.000 movimientos**. La primera fila es la cabecera. Las columnas obligatorias son `fecha_operacion`, `descripcion`, `credito` y `debito`. Son opcionales `referencia` y `codigo_extracto`. Se aceptan fechas `AAAA-MM-DD`, `DD/MM/AAAA` o `DD-MM-AAAA`; todas deben pertenecer al período seleccionado. Cada fila debe tener un importe positivo sólo en crédito o sólo en débito, con hasta dos decimales de entrada.

Revisá el resumen de filas, importes, clasificación y muestra. La clasificación usa el código del extracto y el sentido del importe; una coincidencia ambigua o ausente deja el subtipo sin definir. Si hay filas inválidas, corregí el archivo y repetí el análisis. **Descargar errores CSV** entrega el detalle completo de errores aunque la vista muestre sólo una muestra.

Cuando el análisis esté correcto, pulsá **Confirmar importación**. El servidor vuelve a analizar el archivo antes de guardar, crea el lote y sus movimientos **ABIERTO** en una única operación auditada y evita duplicar el mismo archivo para la misma cuenta y período. Si necesitás corregir un archivo ya importado, no lo vuelvas a subir con otro nombre: consultá el procedimiento operativo correspondiente.

## 8. Configuraciones bancarias

Abrí **Configuraciones** y elegí una configuración en el selector. El resumen informa reglas de clasificación y validaciones automáticas habilitadas. Todos los cambios de esta pantalla afectan operaciones futuras; no reclasifican ni eliminan movimientos históricos.

**Cuentas bancarias vinculadas:** elegí una cuenta y explicá el motivo para habilitar futuras importaciones con esa configuración. **Desvincular** aplica una baja lógica: impide nuevas importaciones con el vínculo, pero conserva lotes anteriores.

**Mapeos contables:** vinculá un subtipo de valor con una cuenta contable y registrá un motivo. **Editar** crea una versión nueva; **Retirar** deja inactiva la relación vigente sin borrar su historia. Revisá cuidadosamente cuenta y subtipo antes de guardar.

**Responsables automáticos:** vinculá un subtipo con una persona que ya tenga acceso efectivo a la app. Las futuras filas importadas con una clasificación unívoca podrán asignarse a esa persona. Editar o retirar la regla no modifica responsables de movimientos existentes.

**Reglas de clasificación:** elegí subtipo, sentido (**Crédito**, **Débito** o **Ambos**), código de extracto y motivo. Podés crear, editar, retirar o habilitar/deshabilitar **Validación automática**. La edición genera una versión y la anterior queda inactiva. Si varias reglas producen distintos subtipos para un mismo código y sentido, la importación mostrará la ambigüedad en lugar de elegir arbitrariamente.

## 9. Problemas frecuentes

- **Importación o configuración deshabilitada:** la instalación no está en modo sandbox o no dispone de la capacidad de escritura correspondiente. Consultá al responsable técnico; cambiar de navegador no altera ese modo.
- **La cuenta no aparece:** confirmá que exista en el catálogo y que esté vinculada activamente a la configuración.
- **El período no aparece en la bandeja:** verificá si existe una importación para esa cuenta y ese mes, y quitá filtros de la consulta.
- **El análisis rechaza el archivo:** comprobá UTF-8, cabeceras, fechas dentro del mes, límite de tamaño y que cada fila tenga un único importe positivo. Descargá el CSV de errores.
- **El recurso ya está reservado:** otro movimiento lo tomó. Recargá el detalle y verificá asociaciones antes de intentar otra opción.
- **No aparece Cerrar movimiento o Conciliar cheque:** revisá el estado, tus permisos y el resultado de **Validar cierre** o **Validar conciliación**.
- **La sesión venció o se denegó acceso:** ingresá nuevamente; si persiste, solicitá revisión de tus permisos Hub.

## 10. Límites de esta versión

La instalación de prueba opera sobre `global_temp`; no constituye el corte productivo del nuevo sistema. Legacy continúa generando registros hasta que se complete ese corte. La búsqueda de candidatos ERP no confirma asociaciones automáticamente. Los cierres y conciliaciones sólo se ejecutan en los casos que el preflight declara válidos. El histórico se conserva, pero esta versión no ofrece exportaciones históricas generales.
