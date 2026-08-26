# app-bancos

Repositorio base en fase de reconstrucción.

Este proyecto quedó inicializado a partir de la estructura de `nueva_app` para reutilizar su núcleo de sesión, permisos y organización de código. No se tocaron los legados `bancos` / `gestion_usuarios`; siguen como referencias para validar reglas de negocio.

## Estado actual

- Descubrimiento y levantamiento de datos completado.
- Estructura de `nueva_app` copiada de forma incremental (sin sobrescritura).
- Arquitectura objetivo definida como monolito modular para extractos mensuales y conciliación de cheques; el plan de consultas e índices está en `docs/database/query-plan.md`.
- Pendiente: configuración de permisos de Hub, contratos de API y flujo funcional.
