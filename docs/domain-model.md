# Modelo de dominio: estado de descubrimiento

- **Cuenta bancaria**: cuenta ERP, banco, nodo y jerarquía.
- **Período mensual** y **movimiento de extracto**: importación, crédito/débito, fecha, referencia, tipo y observación.
- **Regla de clasificación** y **asignación de responsable**: tipo de extracto, subtipo/contabilidad, validación y usuario.
- **Propuesta de asociación**: movimiento con valor, asiento existente o nuevo, monto y reserva.
- **Mensaje**: conversación vinculada a movimiento.
- **Conciliación de cheque**: efecto sobre cheque, operación, valor y asiento ERP.
- **Cuenta/Sesión/Permiso de acceso**: modelo transversal proveniente del Core de `nueva_app`; una sesión reúne cuenta, empleado opcional, identidad Zweb/cliente opcionales y permisos efectivos por aplicación.

La relación exacta entre conciliación de cheques y cierre mensual debe confirmarse: comparten entidades ERP, pero sus flujos y datos auxiliares difieren.
