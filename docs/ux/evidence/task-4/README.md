# Evidencia — Tarea 4: estados visuales

Fecha: 2026-09-08. Estado: validación técnica completa. Las correcciones transversales se revisarán al finalizar la secuencia, según indicación del usuario.

## Capturas

- [Desktop — sin cuenta/período](desktop-sin-contexto-1440.png)
- [Desktop — sin movimientos](desktop-sin-movimientos-1440.png)
- [Desktop — error preservado](desktop-error-1440.png)
- [Desktop — carga localizada](desktop-cargando-1440.png)
- [Mobile — sin cuenta/período](mobile-sin-contexto-390.png)

## Checklist de aceptación

- [x] Sin cuenta/período se muestra una instrucción breve y el CTA `Seleccionar cuenta y período`; no se renderiza una tabla vacía.
- [x] El CTA enfoca el primer dato de contexto faltante.
- [x] Una consulta sin movimientos muestra un estado específico con cuenta y período legibles; no se renderiza la tabla.
- [x] La carga usa spinner, skeleton y texto dentro de un panel localizado, sin overlay ni bloqueo de pantalla completa.
- [x] La carga conserva visualmente cuenta, período y filtros; el indicador de la barra cambia a `Cargando movimientos…`.
- [x] El error explica el problema, conserva todos los controles y ofrece `Reintentar consulta`.
- [x] Reintentar reutiliza el formulario actual, preserva sus valores y reemplaza el mensaje anterior por la carga localizada.
- [x] `ABIERTO`, `PARA_CERRAR`, `CERRADO` y el estado genérico usan etiqueta textual además del color.
- [x] Los pares de texto/fondo de estados y acción primaria superan contraste WCAG AA 4.5:1.
- [x] Spinner y skeleton respetan `prefers-reduced-motion`.
- [x] No se modificaron backend, permisos, flujo de guardado, SQL, ERP ni reglas de negocio.

## Validación ejecutada

```text
php tests/VerificarBandejaResponsiveTest.php
OK: bandeja responsive validada en estructura y render.

php tests/VerificarBarraContextoTest.php
OK: barra de contexto validada en estructura, estado y actualización local.

php tests/VerificarNavegacionFiltrosTest.php
OK: navegación por cursor e indicadores de filtros validados.

php tests/VerificarEstadosVisualesTest.php
OK: estados vacío, carga, error y contraste AA validados.

Playwright/Chromium
CTA sin contexto -> foco en cuenta_bancaria_id.
Sin movimientos -> 0 tablas renderizadas.
Reintentar error -> carga visible + cuenta/período preservados.
Estados de fila -> ABIERTO y PARA_CERRAR visibles como texto.
```

Validación técnica firmada por Codex.
