# Tarea actual

Descubrimiento de legados y referencias completado, incluyendo el catálogo y la validación agregada de tablas `bancos_*`, `bancos_mes_*` y las entidades ERP en modo de solo lectura. Se completó la adopción de la estructura base de `nueva_app` en `app-bancos` para identidad, autenticación, permisos y organización de código; no se ha migrado lógica de negocio todavía.

Diseño vigente:
- Dominio en `global_prod`, con nuevas tablas `bancos_*` y nombres en español.
- `nueva_app` queda definida como plantilla de referencia para identidad/permisos; SGUA se descarta.
- hQuery queda definida como librería compartida de UI/infraestructura cliente (versión externa publicada).

Próximo paso: configurar el permiso de cierre en Hub, detallar la secuencia contable de fusión/generación y validar con datos representativos el plan de consultas/índices antes de avanzar con contratos API y funcionalidad de negocio.

Avance realizado: se generó y aplicó `app/sql/migraciones/004_bancos_tablas_auxiliares.sql`, junto con las migraciones de nomenclatura `005` y `006`, para materializar las tablas auxiliares canónicas en `global_prod`. Se documentó la estructura modular y el plan de CTEs, joins, reservas e índices en `docs/database/query-plan.md`; no se modificó DDL ni datos durante esa planificación.
