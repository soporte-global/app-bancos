# Tarea actual

Descubrimiento de legados y referencias completado, incluyendo el catálogo y la validación agregada de tablas `bancos_*`, `bancos_mes_*` y las entidades ERP en modo de solo lectura. Se completó la adopción de la estructura base de `nueva_app` en `app-bancos` para identidad, autenticación, permisos y organización de código; no se ha migrado lógica de negocio todavía.

Diseño vigente:
- Dominio en `global_prod`, con nuevas tablas `bancos_*` y nombres en español.
- `nueva_app` queda definida como plantilla de referencia para identidad/permisos; SGUA se descarta.
- hQuery queda definida como librería compartida de UI/infraestructura cliente (versión externa publicada).

Próximo paso: acordar las decisiones funcionales que los datos no resuelven (transiciones, asociaciones múltiples, reversas y retención), completar configuración de permisos de Hub y luego avanzar con contratos API y funcionalidad de negocio.

Avance realizado: se generó `app/sql/migraciones/004_bancos_tablas_auxiliares.sql` con el primer paquete de tablas auxiliares en `global_prod` (configuración, importación, movimientos, historial, asociaciones, reservas, mensajes y conciliación de cheque), sin ejecutar DDL en producción ni cambiar datos.
