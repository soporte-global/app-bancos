# Evidencia — Tarea 5: detalle visual de mensajes y borradores

Fecha: 2026-09-08. Estado: validación técnica completa y aprobación explícita del usuario recibida antes de iniciar la Tarea 6.

## Capturas

- [Desktop — lista compacta](desktop-lista-compacta-1440.png)
- [Desktop — detalle abierto](desktop-detalle-abierto-1440.png)
- [Mobile — detalle abierto](mobile-detalle-abierto-390.png)

## Checklist de aceptación

- [x] Cada fila resume estado, asociación, borrador y último mensaje en una estructura compacta y consistente.
- [x] Los textos extensos de mensaje se limitan visualmente a dos líneas en desktop; el cuerpo completo permanece en el detalle.
- [x] `Ver detalle` abre un panel lateral de lectura sin navegación ni nueva consulta.
- [x] Abrir y cerrar conserva íntegramente la URL, incluidos cuenta, período, filtros y cursor.
- [x] En mobile se verificó que el scroll vuelve exactamente a la posición previa (700 px) y el foco regresa al botón de la fila.
- [x] El detalle contiene secciones visibles de asociación, mensajes, borradores e historial.
- [x] Asociación, mensaje y borrador muestran todo lo disponible en la consulta actual.
- [x] Historial muestra el estado actual y declara explícitamente que la consulta vigente no incluye eventos adicionales; no inventa datos ni amplía consultas.
- [x] El panel sólo ofrece apertura/cierre; no incorpora acciones críticas, ejecutivas ni mutaciones de negocio.
- [x] No se modificaron rutas, API, SQL, ERP, permisos, repositorios ni lógica de negocio.

## Validación ejecutada

```text
php tests/VerificarBandejaResponsiveTest.php
php tests/VerificarBarraContextoTest.php
php tests/VerificarNavegacionFiltrosTest.php
php tests/VerificarEstadosVisualesTest.php
php tests/VerificarDetalleMovimientoTest.php
OK en las cinco validaciones incrementales.

Playwright/Chromium
Panel abierto: Asociación | Mensajes | Borradores | Historial.
URL antes/abierto/cerrado: idéntica, con cursor preservado.
Mobile scroll antes/abierto/cerrado: 700/700/700 px.
Foco al cerrar: restaurado al botón `Ver detalle` original.
```

Validación técnica firmada por Codex.
