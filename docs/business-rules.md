# Reglas de negocio

## Reglas confirmadas

- BANCOS se utiliza exclusivamente para conciliar cheques.
- BANCOS_MENSUAL importa extractos por cuenta y período, asigna su resolución y cierra movimientos.
- La asociación automática exige un único candidato válido y considera cuenta, monto, referencia, subtipo y estado.
- Todos los usuarios autorizados de la aplicación pueden pasar un movimiento de `ABIERTO` a `PARA_CERRAR`; por ahora no hay precondiciones funcionales adicionales. Estas se depurarán mediante reglas explícitas, sin cambiar retrospectivamente el historial.
- Sólo un administrador o quien posea un permiso especial de cierre puede pasar un movimiento de `PARA_CERRAR` a `CERRADO`. El nombre y la asignación del permiso se definen al configurar Hub.
- Un movimiento `CERRADO` no se reabre. Un movimiento `PARA_CERRAR` puede volver a `ABIERTO`, con motivo obligatorio y evento de auditoría.
- Un movimiento puede asociar un valor ERP, un asiento ERP existente o un borrador de asiento con varias líneas; también se permite la combinación de valor y asiento. Cada recurso ERP activo sigue siendo exclusivo de un único movimiento.
- La exclusividad de valor/asiento se hace cumplir al reservar y asociar dentro de una misma transacción; una lectura previa de disponibilidad no es suficiente ante concurrencia.
- Al pasar a `PARA_CERRAR`, las asociaciones, reservas y borradores permanecen en las tablas auxiliares; no se revierte ni genera un asiento ERP. Si vuelve a `ABIERTO`, se descartan esos cambios auxiliares de forma transaccional y auditada.
- La reversa inicial de un asiento se materializa marcando `public.asiento.rev = true`. La fusión sólo se permite antes del cierre del período; su efecto definitivo se realiza al cerrar, no al preparar el movimiento.
- Se conserva el histórico completo de movimientos, asociaciones, conciliaciones, eventos y mensajes. Esta app no ofrece consultas ni exportaciones históricas; tales consumidores se resolverán fuera de ella.
- La autorización futura debe aplicarse por aplicación y destino interno después de autenticar la sesión; no se debe confiar en `idu`, cookies legacy ni permisos de SGUA.
- La autoasignación sólo se confirma si el caso de uso obtiene un único candidato que supera el umbral acordado; la consulta devuelve evidencia y puntaje, pero no modifica estado por sí misma.

## Pendiente de detalle

- Validaciones adicionales para pasar a `PARA_CERRAR`.
- Nombre, alcance y administración del permiso especial de cierre en Hub.
- Secuencia contable exacta de la fusión y de la generación de asiento al efectuar el cierre.
