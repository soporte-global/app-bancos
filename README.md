# app-bancos

Repositorio base en fase de reconstrucción.

Este proyecto quedó inicializado a partir de la estructura de `nueva_app` para reutilizar su núcleo de sesión, permisos y organización de código. No se tocaron los legados `bancos` / `gestion_usuarios`; siguen como referencias para validar reglas de negocio.

## Estado actual

- Descubrimiento, levantamiento y migración de datos a las tablas canónicas completados.
- Estructura de `nueva_app` copiada de forma incremental (sin sobrescritura).
- Arquitectura objetivo definida como monolito modular para extractos mensuales y conciliación de cheques; el plan de consultas e índices está en `docs/database/query-plan.md`.
- Base de modo debug incluida: `bancos_debug` mantiene lecturas ERP en `public` y dirige mutaciones BANCOS/ERP a `global_temp`; el Hub de identidad queda en sólo lectura.
- Primera pantalla de sólo lectura disponible en `?pag=bandeja-mensual`, con cursor firmado para recorrer extractos mensuales.
- Siguiente fase: conciliación post-migración, corte controlado de lecturas y construcción del flujo funcional.

El plan de continuidad, los controles de corte y los criterios de rollback están en
[docs/migration-strategy.md](docs/migration-strategy.md).
