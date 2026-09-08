# Evidencia — Tarea 6: render y regresión visual

Fecha: 2026-09-08. Estado: completa.

## Comparación antes/después

- [Antes — captura de la página real aportada por el usuario](antes-pagina-real.png)
- [Después — desktop con 10 filas](despues-desktop-10-filas.png)
- [Después — mobile con 10 filas](despues-mobile-10-filas.png)

Las capturas incrementales de las entregas anteriores permanecen en [`task-1`](../task-1/README.md), [`task-2`](../task-2/README.md), [`task-3`](../task-3/README.md), [`task-4`](../task-4/README.md) y [`task-5`](../task-5/README.md).

## Diferencias observadas y correcciones

| Área | Antes | Después |
| --- | --- | --- |
| Scroll vertical | `html`, `body` y `.afterheader` heredaban alturas calculadas y `overflow-y: hidden`; las filas inferiores quedaban inaccesibles | La página agrega `bandeja-scroll` sólo cuando existe esta bandeja; documento y contenedor crecen y desplazan normalmente |
| Tabla desktop | Fecha y estado se partían; asociación recibía poco espacio útil y el botón de detalle quedaba comprimido | Diez columnas explícitas suman 100%; descripción y asociación reciben prioridad, importes permanecen alineados y estado/detalle no se cortan |
| Tipografía | La fuente monoespaciada global amplificaba el ancho y reducía densidad | La bandeja usa Arial/Helvetica proporcional; `code` conserva fuente monoespaciada |
| Filtros activos | `Filtros activos: ninguno` podía coexistir con `Límite: 10` por una regla global aplicada a `<span hidden>` | El estado vacío se oculta de forma explícita cuando existe un badge |
| Panel de detalle | Las reglas globales de `<header>` ensanchaban y desplazaban el encabezado interno; el header de aplicación podía tapar el cierre | Los encabezados internos se neutralizan y el panel/backdrop quedan sobre el shell, sin overflow horizontal |
| Fondo en páginas cortas | El color heredado del contenedor aparecía debajo de páginas con pocas filas | El shell completo conserva el fondo de la bandeja |

## Matriz de casos cubierta

| Caso | Evidencia | Resultado |
| --- | --- | --- |
| Responsive desktop, 10 filas | [captura](despues-desktop-10-filas.png) | Tabla completa, ancho del documento 1440/1440 px, scroll vertical operativo |
| Responsive mobile, 10 filas | [captura](despues-mobile-10-filas.png) | Cards, sin overflow horizontal, scroll vertical operativo |
| Sin cuenta/período | [captura](estado-sin-contexto.png) | Instrucción y CTA; sin tabla |
| Sin movimientos | [captura](estado-sin-movimientos.png) | Mensaje contextual; sin tabla |
| Cargando | [captura](estado-cargando.png) | Spinner/skeleton localizado |
| Error | [captura](estado-error.png) | Estado preservado y reintento |
| Cursor anterior/siguiente | [página 2](cursor-pagina-2.png) | `Página 2 · movimientos 11–12`; Anterior recuperado |
| Detalle cerrado | [lista desktop](despues-desktop-10-filas.png) | Lista y cursor visibles |
| Detalle abierto | [captura](detalle-abierto.png) | Cuatro secciones, cierre visible, sin overflow |
| Barra de contexto | [corrección](correccion-barra-estatica.png) | Posición estática; deja de cubrir movimientos al recorrer la lista |

## Mediciones automatizadas

```text
Desktop 1440 × 900
document.scrollHeight: 1505 px
scroll probado: 0 -> 500 px
barra de contexto: posición estática
tabla/región: 1324/1326 px
columnas: 99, 86, 265, 126, 113, 126, 179, 106, 106, 119 px
overflow horizontal del documento: no

Mobile 390 × 844
scroll probado: 0 -> 700 px
barra de contexto: posición estática
overflow horizontal del documento: no
```

## Correcciones de revisión final

Capturas aportadas por el usuario:

- [Detalle con scroll de fondo activo](correccion-antes-detalle.png)
- [Barra sticky ocultando filas](correccion-antes-sticky.png)

Resultado corregido:

- [Barra estática durante el scroll](correccion-barra-estatica.png)
- [Detalle con documento bloqueado](correccion-detalle-scroll-bloqueado.png)
- [Modo oscuro](correccion-modo-oscuro.png)

Validaciones de navegador:

```text
Barra de contexto: position static; al desplazar 500 px queda fuera del viewport.
Detalle abierto: overflow-y hidden en html/body; wheel no modifica scrollY (500 -> 500).
Detalle corto: scrollHeight/clientHeight 900/900; no genera desplazamiento vacío.
Detalle cerrado: scrollY restaurado a 500.
Modo oscuro: preferencia `oscuro` persistida en localStorage y recuperada al recargar.
Paleta: 0 colores hex, rgb(), rgba(), hsl() o hsla() declarados en app/css/shared.css.
```

Esta revisión reemplaza las mediciones sticky registradas en la primera pasada de la Tarea 6.

## Checklist de aceptación

- [x] Captura real “antes” y capturas “después” desktop/mobile conservadas junto con la evidencia de cada entrega.
- [x] Casos responsive, vacío, carga, error, cursor anterior/siguiente, detalle abierto/cerrado y barra de contexto cubiertos.
- [x] Diferencias visuales y correcciones documentadas.
- [x] Las seis validaciones incrementales pasan.
- [x] Sintaxis PHP y JavaScript válidas; `git diff --check` sin errores.
- [x] No se ejecutaron pruebas contables ni SQL.
- [x] No se modificaron repositorios legacy, SQL, backend, contratos, permisos Hub ni lógica de negocio.

Checklist revisado y firmado por **Codex**, 2026-09-08.
