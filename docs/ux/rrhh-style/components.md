# Catálogo de componentes

## Convención

Los nombres siguientes describen roles. La implementación puede conservar selectores `.bandeja-*` durante una transición, pero debe agruparlos bajo una raíz única, por ejemplo `.bancos-app`, y evitar que el CSS afecte otras páginas.

## Selector de modo o destino

Sólo se incorpora cuando existan al menos dos destinos funcionales y autorizables. Se construye con radio buttons reales o enlaces a rutas reales.

- Tres columnas en desktop si hay tres destinos.
- Altura mínima de 52 px; icono de 26 px; título y ayuda breve.
- Seleccionado: borde lateral/superior y superficie del panel; borde inferior eliminado.
- Foco: outline de 3 px con acento al 25% y offset de 2 px.
- En móvil se oculta la ayuda secundaria antes que el título.

No usar pestañas para filtros, estados o acciones.

## Barra de contexto

Contenido mínimo:

- origen o módulo;
- cuenta bancaria;
- período;
- estado de carga;
- filtros activos removibles.

Debe usar `nav` sólo si representa navegación/miga; en otro caso, `section` con encabezado accesible. Los chips de filtro llevan botón con nombre “Quitar filtro …”. El indicador de estado incluye texto además del punto de color.

## Campos y búsqueda

- Altura de 38 px, radio de 8 px y borde de 1 px.
- Label visible encima o inmediatamente antes.
- Icono decorativo con `aria-hidden="true"`.
- Foco visible mediante borde de acento más halo de 3 px.
- Error mediante `aria-invalid="true"`, texto asociado con `aria-describedby` y color de error.
- Búsqueda con botón de limpiar que desaparece con `hidden`, sin dejar hueco.

## Botones

| Variante | Uso | Apariencia |
| --- | --- | --- |
| Primario | Consultar, aplicar o confirmar una acción permitida. | Fondo/acento principal, texto con contraste y altura de control. |
| Secundario | Reintentar, restablecer, descartar cambios no persistidos. | Superficie hundida, borde y texto principal. |
| Peligro | Acción irreversible o de alto impacto; no aplica aún a la bandeja de lectura. | Bases `--paleta-error*`, texto explícito e icono auxiliar. |
| Vista | Cambiar presentación del mismo conjunto de datos. | Botón plano; estado activo con acento, borde y `aria-pressed`. |
| Icono | Cerrar, limpiar, anterior/siguiente. | Área táctil mínima de 38 px y nombre accesible. |

`disabled` y `aria-busy="true"` deben ser distinguibles. Un botón ocupado conserva ancho y texto estable o anuncia el cambio.

## Métricas

- Cada métrica es un `article` o un grupo de `dt`/`dd`.
- Etiqueta pequeña y valor prominente.
- Grilla de cuatro columnas en desktop, dos en móvil y una sólo si el contenido lo exige.
- Separación por fondo del contenedor y `gap: 1px`.
- Los importes se alinean a la derecha y mantienen signo/unidad.

Para la bandeja inicial, métricas útiles son cantidad visible, crédito total visible, débito total visible y movimientos con asociación. Deben derivarse de datos ya disponibles; si no están en el contrato, no se simulan.

## Tabla de resultados

- `caption` accesible aunque sea visualmente oculto.
- `th scope="col"` y encabezado persistente dentro de la región scrolleable cuando resulte estable.
- Importes a la derecha; identificadores y fechas sin corte ambiguo.
- Filas alternas con una diferencia sutil y hover que no borra el estado.
- Celdas sin dato muestran texto, no un hueco.
- Estado como pill textual.
- La acción de detalle es un botón y conserva un nombre con referencia del movimiento.

En móvil cada `td` conserva `data-label`. La fila puede volverse una grilla, pero usa borde y superficie plana, no una tarjeta con sombra.

## Estados de contenido

Los estados inicial, carga, vacío, parcial, error y no disponible comparten la misma caja y cambian contenido/semántica. Estructura:

```html
<section class="estado-contenido" data-estado="vacio" role="status">
    <i aria-hidden="true"></i>
    <div>
        <h2>…</h2>
        <p>…</p>
    </div>
</section>
```

El error recuperable agrega “Reintentar”. Un error no recuperable explica qué dato o permiso falta. Carga usa `aria-busy` en la región afectada y no reemplaza toda la página.

## Paginación

- Muestra posición legible y controles anterior/siguiente.
- Un enlace no disponible no lleva `href`, usa `aria-disabled="true"` y no entra en el tab order.
- Conserva filtros y contexto al avanzar.
- No muestra números de página ficticios cuando el backend usa cursor y desconoce el total.

## Drawer de detalle

- `role="dialog"`, `aria-modal="true"` y título asociado.
- Overlay separado, cabecera y cuerpo scrolleable.
- Secciones: resumen, asociación, mensajes, borrador e historial disponible.
- Apertura enfoca cerrar o el título; cierre devuelve foco al botón originario.
- Tab y Shift+Tab quedan contenidos mientras está abierto.
- Escape y clic en overlay cierran sólo si no hay cambios pendientes.

## Mensajes y resultados de acción

Los mensajes breves usan `role="status"` y `aria-live="polite"`; errores de una operación solicitada pueden usar `role="alert"`. La apariencia se deriva de las bases compartidas:

- listo/éxito: `--paleta-exito*`;
- parcial/vacío/advertencia: `--paleta-advertencia*`;
- error: `--paleta-error*`;
- carga/inicial: acento y neutros del tema.

