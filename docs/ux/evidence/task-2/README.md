# Evidencia — Tarea 2: barra de contexto cuenta/período

Fecha: 2026-09-08. Estado: validación técnica completa y aprobación explícita del usuario recibida antes de iniciar la Tarea 3.

## Capturas

- [Desktop, 1440 px](desktop-1440.png)
- [Mobile, 390 px](mobile-390.png)
- [Mobile durante scroll, barra sticky](mobile-scroll-sticky-390.png)

## Checklist de aceptación

- [x] La miga muestra `Extractos / Cuenta / Período` en desktop y los mismos niveles apilados en mobile.
- [x] Cuenta y período usan texto explícito; el período se presenta como mes y año legibles.
- [x] La barra muestra estado localizado (`movimientos cargados`, `esperando consulta`, error o carga en curso).
- [x] Se informa el resumen de filtros activos; en este incremento es `ninguno` porque aún no existen filtros opcionales.
- [x] La barra se implementó inicialmente como sticky. La revisión posterior a la Tarea 6 cambió su posición a estática para evitar que oculte movimientos durante el scroll.
- [x] Editar cuenta/período actualiza la barra mediante eventos locales, sin recarga ni mutación de estado global.
- [x] Enviar el formulario cambia sólo el indicador local a `Cargando movimientos…` antes de la navegación existente.
- [x] No se modificaron rutas, contratos API, SQL, repositorios, permisos, lógica de negocio ni mutaciones.

## Validación ejecutada

```text
php tests/VerificarBandejaResponsiveTest.php
OK: bandeja responsive validada en estructura y render.

php tests/VerificarBarraContextoTest.php
OK: barra de contexto validada en estructura, estado y actualización local.

php -l app/html/bandeja-mensual.php
No syntax errors detected.

Playwright/Chromium
Cuenta 1042 -> 2200: actualización inmediata en la barra.
Período julio -> agosto de 2026: actualización inmediata en la barra.
Submit interceptado: estado local `Cargando movimientos…`.
La captura conserva el comportamiento original de esta entrega; el estado vigente y su corrección están documentados en la evidencia de la Tarea 6.
```

Validación técnica firmada por Codex y aceptación de producto confirmada por el usuario.
