# Composición y responsive

## Shell objetivo

La bandeja mantiene un único `main` después del header. Dentro de él, contexto, selector y resultados forman una superficie continua.

```text
┌────────────────────────────────────────────────────────────┐
│ Contexto: Extractos / Cuenta / Período        Estado       │
├────────────────────────────────────────────────────────────┤
│ Cuenta             Período           Límite     Consultar  │
├────────────────────────────────────────────────────────────┤
│ Resumen / filtros activos / navegación de vista futura     │
├────────────────────────────────────────────────────────────┤
│ Indicadores compactos                                     │
├────────────────────────────────────────────────────────────┤
│ Tabla de movimientos                                      │
├────────────────────────────────────────────────────────────┤
│ Estado de consulta                        Paginación        │
└────────────────────────────────────────────────────────────┘
```

No se mueve el formulario al footer. El footer vuelve a ser infraestructura compartida y conserva su lugar en el orden del documento.

## Áreas

### Contexto

- Conserva cuenta, período, cantidad cargada y filtros activos.
- Ocupa una franja, no una tarjeta elevada.
- El alternador de tema desaparece de esta zona.
- En ausencia de contexto muestra “Sin seleccionar” sin colapsar la estructura.

### Selector

- Usa una grilla de controles con acción primaria al final.
- Comparte borde con contexto y resultado.
- Labels visibles; los placeholders no reemplazan labels.
- El botón de consulta mide lo mismo que los campos.

### Resultado

- El encabezado combina título, descripción y estado.
- Las métricas usan una grilla plana con divisores internos.
- La región de tabla conserva `tabindex="0"` y nombre accesible.
- Paginación y estado viven en la última fila del panel.

### Detalle

El drawer actual puede mantenerse porque ofrece detalle progresivo. Su apariencia cambia a:

- cabecera compacta y fija;
- secciones separadas por borde, sin tarjetas anidadas con sombra;
- máximo de `min(92vw, 560px)`;
- overlay como única superficie elevada;
- foco contenido, cierre por Escape y devolución del foco al disparador.

## Desktop: más de 900 px

- El `main` usa todo el ancho disponible bajo el header.
- Padding lateral del panel: hasta 60 px; reducir con `clamp()` si la tabla necesita espacio.
- Filtros en una fila mientras cada campo conserve ancho útil.
- Métricas en cuatro columnas.
- Tabla tradicional con encabezado visible.
- Estado a la izquierda y paginación a la derecha.
- No ocultar empresas, contexto ni metadatos por altura de viewport.

Para alturas menores a 800 px se permite reducir padding vertical y altura de zonas auxiliares, nunca eliminar información.

## Tablet: 651 a 900 px

- Las áreas pasan a una columna sin cambiar el orden DOM.
- Los filtros pueden usar dos columnas más la acción completa.
- Métricas en dos columnas.
- Navegaciones de cuatro opciones, si existieran, usan cuatro columnas compactas.
- Drawer de detalle conserva su lateralidad mientras entre en pantalla.
- La tabla puede seguir horizontal con región scrolleable; no se debe forzar tarjeta si perjudica la comparación entre columnas.

## Móvil: hasta 650 px

- Padding exterior: `20px 12px 48px` como referencia.
- Selector, resultado y estado quedan en una columna.
- Botón primario ocupa todo el ancho.
- Métricas usan dos columnas; a 480 px pueden pasar a una.
- La tabla adopta filas apiladas con `data-label`, fondo plano y divisores de 1 px.
- Estado y paginación se apilan.
- El drawer se transforma en panel casi completo.

## Ajustes estrechos

| Corte | Ajuste |
| --- | --- |
| 520 px | Acciones dobles y descargas pasan a dos columnas; su etiqueta ocupa toda la fila. |
| 480 px | Se oculta sólo la descripción secundaria de pestañas; métricas a una columna cuando los valores no entren. |
| 420 px | Las celdas apiladas pueden pasar de etiqueta/valor en dos columnas a una sola. |
| 360 px | Datos de definición pasan a una columna y se recalculan radios del primer/último elemento. |

## Reglas de continuidad

- Paneles vecinos no duplican radios en la unión.
- Un contenedor interno no agrega margen sólo para simular una tarjeta.
- Las grillas internas pueden usar `gap: 1px` y el fondo del padre como separador.
- Los scrolls se aplican a regiones de datos, no al `body` mediante clases específicas de una pantalla.
- El header y footer compartidos no reciben clases `bandeja-*` ni nodos movidos desde `main`.

