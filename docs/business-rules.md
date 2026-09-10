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

Los borradores nuevos se crean en sandbox sobre movimientos `ABIERTO`, con nodo, fecha, modelo y entre 2 y 200 líneas. Cada línea contiene sólo debe o sólo haber, con hasta cinco decimales, y el total debe coincidir exactamente con el haber. El total no tiene que igualar el importe del extracto: la migración demuestra que esa condición no es universal. Nodo y cuentas deben existir en ERP, pero sólo se leen. Un movimiento admite un único borrador activo; cabecera, líneas, reserva, asociación y auditoría se crean atómicamente.
