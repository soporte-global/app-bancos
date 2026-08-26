# Modelo de dominio: estado de descubrimiento

- **Cuenta bancaria**: cuenta ERP, banco, nodo y jerarquía.
- **Importación de extracto** y **movimiento de extracto**: lote inmutable por cuenta/período, fila de origen estable, crédito/débito, fecha, referencia, tipo y observación. El movimiento posee PK propia; `(id_periodo, serial_seq)` sólo se conserva para migración.
- **Regla de clasificación** y **asignación de responsable**: tipo de extracto, subtipo/contabilidad, validación y usuario.
- **Propuesta de asociación y reserva**: movimiento con valor ERP, asiento ERP existente y/o borrador con múltiples líneas; una combinación de valor y asiento está permitida. Cada recurso activo se reserva de forma exclusiva y la asociación expresa la decisión vigente.
- **Mensaje**: conversación vinculada a movimiento.
- **Conciliación de cheque**: efecto sobre cheque, operación, valor y asiento ERP.
- **Cuenta/Sesión/Permiso de acceso**: modelo transversal proveniente del Core de `nueva_app`; una sesión reúne cuenta, empleado opcional, identidad Zweb/cliente opcionales y permisos efectivos por aplicación.

La relación exacta entre conciliación de cheques y cierre mensual debe confirmarse: comparten entidades ERP, pero sus flujos y datos auxiliares difieren.
