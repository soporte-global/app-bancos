# Evidencia — Tarea 1: layout responsive de la bandeja

Fecha: 2026-09-08. Estado: validación técnica completa y aprobación explícita del usuario recibida antes de iniciar la Tarea 2.

## Capturas

- [Desktop, 1440 px](desktop-1440.png)
- [Mobile, 390 px](mobile-390.png)

Las capturas usan dos movimientos representativos: uno sin seguimiento y otro con asociación, borrador y mensaje. El fixture sólo alimenta la vista existente; no usa ni modifica base de datos.

## Checklist de aceptación

- [x] Desktop conserva la tabla completa y una región de scroll horizontal propia cuando el ancho intermedio no alcanza.
- [x] Mobile (`max-width: 768px`) transforma cada fila en una card y conserva las nueve etiquetas de campo.
- [x] Se definieron breakpoints explícitos en 1100, 768 y 420 px.
- [x] Contenedor, filtros, panel de resultados y tabla tienen clases reutilizables y estilos externos.
- [x] No hay `vh`, alturas rígidas de bandeja ni estilos inline en la vista productiva.
- [x] La única acción de fila sigue siendo la lectura de sus datos; no se agregaron acciones destructivas inline.
- [x] No se modificaron rutas, enlaces existentes, parámetros, cursor, contratos API, SQL, repositorios, modelos de dominio, permisos ni lógica de negocio.

## Validación ejecutada

```text
php tests/VerificarBandejaResponsiveTest.php
OK: bandeja responsive validada en estructura y render.

php -l app/html/bandeja-mensual.php
No syntax errors detected.

Playwright/Chromium
1440 px: tabla visible, sin overflow del documento.
390 px: filas en modo card, sin overflow horizontal del documento.
```

Validación técnica firmada por Codex y aceptación de producto confirmada por el usuario.
