# Reglas de negocio

## Reglas confirmadas

- BANCOS se utiliza exclusivamente para conciliar cheques.
- BANCOS_MENSUAL importa extractos por cuenta y período, asigna su resolución y cierra movimientos.
- La asociación automática exige un único candidato válido y considera cuenta, monto, referencia, subtipo y estado.
- Todos los usuarios autorizados de la aplicación pueden pasar un movimiento de `ABIERTO` a `PARA_CERRAR`; por ahora no hay precondiciones funcionales adicionales. Estas se depurarán mediante reglas explícitas, sin cambiar retrospectivamente el historial.
- Sólo un administrador o quien posea un permiso especial de cierre puede pasar un movimiento de `PARA_CERRAR` a `CERRADO`. El nombre y la asignación del permiso se definen al configurar Hub.
- Un movimiento `CERRADO` no se reabre. Un movimiento `PARA_CERRAR` puede volver a `ABIERTO`, con motivo obligatorio y evento de auditoría.
- Un movimiento puede asociar un valor ERP, un asiento ERP existente o un borrador de asiento con varias líneas; también se permite la combinación de valor y asiento. Los valores son exclusivos. Un asiento puede ser exclusivo o compartido, pero nunca mezclar ambas modalidades mientras tenga usos activos.
- La exclusividad y la modalidad del recurso se hacen cumplir al reservar y asociar dentro de una misma transacción; una lectura previa de disponibilidad no es suficiente ante concurrencia. La asociación exclusiva exige que el importe del extracto coincida con el mayor movimiento contable del asiento; la compartida no exige igualdad individual.
- Al pasar a `PARA_CERRAR`, las asociaciones, reservas y borradores permanecen en las tablas auxiliares; no se revierte ni genera un asiento ERP. Si vuelve a `ABIERTO`, se descartan esos cambios auxiliares de forma transaccional y auditada.
- La conversación de un movimiento permanece disponible en todos sus estados, incluido `CERRADO`. El servidor identifica al emisor y fija el tipo `USUARIO`. Cada operador registra únicamente sus propias lecturas; la ausencia de recepción equivale a pendiente y el mensaje propio se considera leído al crearlo.
- El responsable sólo puede ser una cuenta activa con acceso efectivo general a APP BANCOS. Asignar o reasignar conserva el estado vigente en un nuevo evento de historial; el campo `usuario_hub_id` representa al nuevo responsable y la auditoría identifica al operador que ejecutó el cambio. Repetir el responsable actual no agrega otro evento.
- Una importación se previsualiza antes de persistir: cuenta, configuración y período son explícitos, todas las fechas pertenecen al mes, cada movimiento contiene sólo crédito o débito positivo y los importes de entrada admiten hasta dos decimales. El hash se calcula sobre los bytes originales y el servidor vuelve a analizar el archivo al confirmar. La configuración nunca se infiere por orden o coincidencia aproximada. Un mismo hash y versión no puede crear dos lotes para la misma cuenta y período, aunque cambie la clave idempotente.
- Si existen filas inválidas, el operador puede descargar todos sus errores en CSV sin persistir el lote. La descarga vuelve a validar el mismo archivo y contexto; la lista visible puede estar acotada, pero el reporte no omite errores por fila.
- La clasificación compara el código del extracto de forma exacta, ignorando espacios exteriores y mayúsculas, y respeta el sentido `C`, `D` o `A` de la regla. Sólo una coincidencia con un único subtipo lo asigna automáticamente. Una coincidencia múltiple, un código desconocido o la ausencia de código no bloquean la importación y dejan el subtipo sin definir; esas situaciones deben mostrarse al operador. `validar_automaticamente` habilita un tratamiento posterior, pero no resuelve por sí solo una ambigüedad.
- La reversa inicial de un asiento se materializa marcando `public.asiento.rev = true`. La fusión sólo se permite antes del cierre del período; su efecto definitivo se realiza al cerrar, no al preparar el movimiento.
- Se conserva el histórico completo de movimientos, asociaciones, conciliaciones, eventos y mensajes. Esta app no ofrece consultas ni exportaciones históricas; tales consumidores se resolverán fuera de ella.
- La autorización futura debe aplicarse por aplicación y destino interno después de autenticar la sesión; no se debe confiar en `idu`, cookies legacy ni permisos de SGUA.
- La autoasignación sólo se confirma si el caso de uso obtiene un único candidato que supera el umbral acordado; la consulta devuelve evidencia y puntaje, pero no modifica estado por sí misma.

## Pendiente de detalle

- Validaciones adicionales para pasar a `PARA_CERRAR`.
- Nombre, alcance y administración del permiso especial de cierre en Hub.
- Secuencia contable exacta de la fusión y de la generación de asiento al efectuar el cierre.

## Primera regla operativa implementada

La transición `ABIERTO -> PARA_CERRAR` está implementada únicamente en el sandbox. El movimiento se bloquea y se valida dentro de su cuenta/período; se registra el usuario Hub y el evento de historial en la misma transacción idempotente. La ausencia de asociaciones o borrador no impide todavía la preparación, de acuerdo con la regla confirmada. No se alteran recursos ERP.

La transición `PARA_CERRAR -> ABIERTO` también está implementada únicamente en el sandbox. Exige un motivo de 3 a 200 caracteres y desactiva asociaciones, reservas y borradores activos sin eliminarlos. Las líneas del borrador, mensajes e historial permanecen disponibles como evidencia. Todo ocurre en una transacción idempotente y no altera el ERP.

La asociación manual de valor está habilitada en sandbox para movimientos `ABIERTO`. El usuario identifica el valor, pero el importe asociado se deriva del movimiento. Se rechazan valores inexistentes, con monto insuficiente, en estados ERP `7`, `20` o `36`, ya reservados/asociados, o cuando el movimiento ya tiene otro valor activo. Reserva y asociación son atómicas; el ERP permanece de sólo lectura. La selección automática y su puntaje todavía no forman parte de esta regla.

Los borradores nuevos se crean en sandbox sobre movimientos `ABIERTO`, con nodo, fecha, modelo y entre 2 y 200 líneas. Cada línea contiene sólo debe o sólo haber, con hasta cinco decimales, y el total debe coincidir exactamente con el haber. El total no tiene que igualar el importe del extracto: la migración demuestra que esa condición no es universal. Nodo y cuentas deben existir en ERP, pero sólo se leen. La cuenta no se restringe al nodo del borrador ni se rechaza automáticamente por `imputable`: ambos atributos se informan y ordenan en la búsqueda, porque el histórico contiene excepciones válidas. Un movimiento admite un único borrador activo; cabecera, líneas, reserva, asociación y auditoría se crean atómicamente.
