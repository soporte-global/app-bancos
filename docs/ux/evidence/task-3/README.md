# Evidencia — Tarea 3: navegación y filtros

Fecha: 2026-09-08. Estado: validación técnica completa y aprobación explícita del usuario recibida antes de iniciar la Tarea 4.

## Capturas

- [Desktop — página 1](desktop-page-1-1440.png)
- [Desktop — página 2, con Anterior recuperado](desktop-page-2-1440.png)
- [Mobile — página 1](mobile-page-1-390.png)

## Checklist de aceptación

- [x] `Anterior` y `Siguiente` se presentan juntos, sin mover la tabla ni producir saltos de layout al habilitarse.
- [x] `Siguiente` conserva el enlace y cursor opaco emitido por el servidor; la UI no decodifica ni modifica el token.
- [x] Antes de avanzar, la bandeja guarda en `sessionStorage` la URL y posición visibles; la página siguiente recupera `Anterior` desde ese estado local.
- [x] Volver con `Anterior` restaura la URL previa completa, incluidos cuenta, período, límite y cursor que correspondan.
- [x] La posición se expresa como `Página N · movimientos X–Y`, nunca como IDs o contenido del cursor.
- [x] Un límite distinto del predeterminado aparece como badge (`Límite: 25`) con control accesible para quitarlo.
- [x] Quitar el badge restablece el límite a 50 localmente y no reemplaza ni vacía el listado actual.
- [x] Si una URL con cursor se abre directamente y no existe historial local de bandeja, `Anterior` permanece deshabilitado para evitar una navegación incorrecta.
- [x] No se modificaron rutas, formato del cursor, contratos API, SQL, ERP, permisos ni reglas de negocio.

## Validación ejecutada

```text
php tests/VerificarBandejaResponsiveTest.php
OK: bandeja responsive validada en estructura y render.

php tests/VerificarBarraContextoTest.php
OK: barra de contexto validada en estructura, estado y actualización local.

php tests/VerificarNavegacionFiltrosTest.php
OK: navegación por cursor e indicadores de filtros validados.

Playwright/Chromium
Página 1 · movimientos 1–2
Siguiente -> Página 2 · movimientos 3–4
Anterior -> Página 1 · movimientos 1–2
Quitar Límite: 25 -> input 50 + `Filtros activos: ninguno`
```

Validación técnica firmada por Codex y aceptación de producto confirmada por el usuario.
