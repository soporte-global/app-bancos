# Fundamentos visuales

## Arquitectura CSS

La implementación objetivo usa este orden de carga:

```text
_shared/css/paleta_colores.css
_shared/css/general.css y componentes compartidos
app/css/bancos.css              estructura y responsive
app/css/tema_componentes.css    apariencia común basada en roles
app/css/tema_claro.css          bases y apariencia clara
o app/css/tema_oscuro.css       bases y apariencia oscura
```

`bancos.css` no debe contener colores literales. Los archivos de tema pueden declarar colores literales sólo en su primer bloque `:root`; las reglas posteriores deben consumir esas bases o `--paleta-*` y derivar variaciones con `color-mix()`.

## Paleta compartida

Bases de referencia:

```css
:root {
    --hue-principal: 207;
    --paleta-principal1: hsl(var(--hue-principal), 85%, 50%);
    --paleta-principal2: hsl(var(--hue-principal), 100%, 50%);
    --paleta-principal3: hsl(var(--hue-principal), 60%, 50%);
    --paleta-secundario1: #ffffff;
    --paleta-secundario2: #000000;
    --paleta-secundario3: #68737d;
    --paleta-exito1: hsl(120, 100%, 50%);
    --paleta-exito2: #2a9d57;
    --paleta-exito3: hsl(105, 96%, 50%);
    --paleta-advertencia1: hsl(35, 100%, 50%);
    --paleta-advertencia2: hsl(35, 74%, 50%);
    --paleta-advertencia3: #d99a16;
    --paleta-error1: hsl(0, 100%, 50%);
    --paleta-error2: #c83d3d;
    --paleta-detalle1: var(--paleta-principal2);
}
```

Estos nombres pertenecen a componentes compartidos. Los aliases históricos pueden mantenerse temporalmente mediante `compatibilidad_colores.css`, pero ningún CSS nuevo de bancos debe consumirlos.

## Bases por tema

Tema claro:

```css
:root {
    --color-principal1: hsl(var(--hue-principal), 85%, 50%);
    --color-principal2: #7698ad;
    --color-principal3: #8bc8e8;
    --color-secundario1: #ffffff;
    --color-secundario2: #000000;
    --color-secundario3: #607184;
    --color-detalle1: hsl(147, 77%, 28%);
}
```

Tema oscuro:

```css
:root {
    --color-principal1: hsl(var(--hue-principal), 85%, 50%);
    --color-principal2: hsl(var(--hue-principal), 35%, 38%);
    --color-principal3: #7698ad;
    --color-principal4: hsl(var(--hue-principal), 48%, 48%);
    --color-secundario1: #ffffff;
    --color-secundario2: #000000;
    --color-secundario3: #a5b9c7;
    --color-detalle1: hsl(145, 55%, 58%);
}
```

`--color-secundario1` y `--color-secundario2` son extremos de mezcla, no equivalentes semánticos fijos de fondo y texto. Por eso conservan blanco y negro en ambos modos y cambian los porcentajes.

## Roles semánticos recomendados

Los siguientes aliases locales facilitan la traducción bancaria sin volver a crear una paleta independiente:

| Token local | Claro | Oscuro | Uso |
| --- | --- | --- | --- |
| `--ui-fondo-app` | mezcla 88% de secundario 1 | mezcla 6% de secundario 1 | Fondo raíz. |
| `--ui-superficie` | mezcla 97% de secundario 1 | mezcla 13% de secundario 1 | Paneles principales contiguos. |
| `--ui-superficie-hundida` | mezcla 93% de secundario 1 | mezcla 11% de secundario 1 | Tabla, métricas y subpaneles. |
| `--ui-borde` | 15% de secundario 2 transparente | 15% de secundario 1 transparente | Separadores de 1 px. |
| `--ui-texto` | principal 1 al 24% con secundario 2 | principal 1 al 12% con secundario 1 | Texto principal. |
| `--ui-texto-suave` | 40% de secundario 1 con secundario 2 | 75% de secundario 1 con secundario 2 | Ayuda y metadatos. |
| `--ui-acento` | `--color-principal1` | `--color-principal1` | Acción, selección y foco. |

Los porcentajes se documentan como guía de equivalencia. Deben verificarse en capturas y contraste; no deben multiplicarse aliases para cada selector.

## Tipografía

- La aplicación hereda `'Roboto Mono', monospace` del shell compartido.
- Orbitron se reserva para el título global existente en el header.
- Texto base: tamaño heredado del shell, con `line-height` mínimo de 1.35.
- Etiqueta/sobretítulo: `0.72rem`, peso 800, `letter-spacing: 0.09em`, mayúsculas.
- Texto auxiliar: entre `0.64rem` y `0.72rem`, nunca como única portadora de información crítica.
- Los números e importes mantienen ancho visual estable gracias a la fuente monoespaciada y alineación a la derecha.

## Geometría y espaciado

- Altura normal de control: 38 px.
- Altura compacta contextual: 30 a 35 px.
- Radio de control y límite externo: 8 px.
- Pills de estado: `999px`, sólo para estados o chips.
- Separación mínima: 6 px.
- Escala recomendada: 6, 8, 10, 12, 18, 22 y 26 px.
- Bordes: 1 px. Los paneles contiguos comparten borde y eliminan el borde duplicado.
- Sombras: ninguna en paneles de contenido. Se admiten en overlay, menú o drawer para expresar elevación real.

## Movimiento

Las transiciones funcionales pueden durar 160 ms y limitarse a `background-color`, `border-color`, `box-shadow`, `color` y transformaciones pequeñas. Con `prefers-reduced-motion: reduce`:

```css
.bancos-app *,
.bancos-app *::before,
.bancos-app *::after {
    scroll-behavior: auto !important;
    transition-duration: 0.01ms !important;
}

.bancos-app .fa-spin {
    animation: none !important;
}
```

No se usan animaciones para comunicar éxito, error o progreso como única señal.
