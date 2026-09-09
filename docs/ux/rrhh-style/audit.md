# Relevamiento y diferencias

## Alcance revisado

Se inspeccionaron la vista principal, los tres CSS propios, el controlador visual JavaScript, las pruebas de UI y la infraestructura compartida de temas de `rrhh_consulta`. En `app-bancos` se revisaron la bandeja mensual, `app/css/shared.css`, `app/js/shared.js`, el head compartido, la configuración y las evidencias visuales existentes.

No se evaluó el diseño a partir de imágenes aisladas: la especificación surge de reglas efectivas y pruebas del repositorio de referencia.

## Rasgos del front de referencia

| Rasgo | Implementación observada |
| --- | --- |
| Densidad | Controles de 30 a 38 px; paneles compactos; información visible sin grandes zonas decorativas. |
| Composición | Superficies contiguas separadas por bordes de 1 px; pocas sombras; radios moderados de 8 px sólo en límites externos o controles. |
| Navegación local | Selector superior de tres modos y navegación secundaria por vistas con `aria-pressed`. |
| Jerarquía | Etiquetas pequeñas en mayúsculas (`0.72rem`, peso 800, tracking `0.09em`), títulos contenidos y métricas prominentes. |
| Color | Bases mínimas por tema y tonos derivados en el lugar de uso con `color-mix()`. Estados usan bases compartidas de éxito, advertencia y error. |
| Temas | CSS estructural independiente de `tema_claro.css` y `tema_oscuro.css`; selección centralizada por cookie. |
| Estados | `inicial`, `cargando`, `listo`, `vacio`, `parcial`, `no_disponible` y `error`, publicados con texto, icono y atributos ARIA. |
| Responsive | Reordenamiento principal a 900 px; reducción a una columna desde 650 px; ajustes puntuales a 520, 480 y 360 px. |
| Accesibilidad | Encabezado `h1` visualmente oculto, landmarks, `aria-live`, `aria-busy`, foco visible, labels reales y movimiento reducido. |

## Distancia con `app-bancos`

| Área | Estado actual de bancos | Estado objetivo |
| --- | --- | --- |
| Tema | Alternador propio en `app/js/shared.js`, `localStorage`, `data-tema` y clases en `body`. | Selector compartido, cookie derivada de la carpeta y enlaces a dos hojas de tema. |
| Tokens | Capa `--bandeja-*` construida sobre aliases históricos. | Bases `--color-*` por tema y estados `--paleta-*`; tokens semánticos locales sólo cuando expresen un rol estable. |
| Superficies | Tarjetas con radios de 10/12 px y sombras de 14/16 px. | Panel continuo y denso; borde de 1 px; sombra nula salvo overlays o elevación imprescindible. |
| Contexto | Barra elevada con miga, filtros y alternador de tema. | Franja integrada al shell; contexto y estado permanecen, el alternador migra al header compartido. |
| Filtros | Panel independiente y luego movido al footer por JavaScript. | Selector compacto, estable y contiguo al resultado. No mover nodos entre landmarks. |
| Tabla móvil | Cada fila se convierte en tarjeta sombreada. | Una columna legible, pero con contenedores planos y separación por borde/fondo alterno. |
| Estado | Bloques grandes con icono circular. | Franja o panel compacto con texto directo; icono auxiliar; mismo vocabulario semántico. |
| Tipografía | Arial dentro de la bandeja, distinta del shell. | Heredar `Roboto Mono`, como el front de referencia; Orbitron queda reservado al título global del header. |
| Breakpoints | 1100, 768 y 420 px. | 900, 650, 520, 480 y 360 px, manteniendo excepciones justificadas por la tabla. |

## Qué se adopta

- Arquitectura de tres capas: paleta compartida, CSS estructural y dos hojas de tema.
- Contrato del selector claro/oscuro y persistencia por cookie.
- Densidad, radios, bordes, foco y ausencia deliberada de sombras decorativas.
- Etiquetas de sección, pestañas compactas, métricas, tabla, resultados y estados.
- Breakpoints y `prefers-reduced-motion`.
- Disciplina de pruebas: los temas sólo contienen bases propias y derivan el resto.

## Qué se adapta

- Las tres pestañas de RRHH se traducen a destinos bancarios sólo cuando existan rutas reales. En la bandeja actual no se inventan pestañas.
- La ficha de empleado se traduce a contexto de cuenta/período, no a una tarjeta personal.
- La grilla de resultados se traduce a indicadores y tabla de movimientos.
- Los modales de detalle de RRHH sirven como lenguaje visual; el panel lateral bancario puede conservarse si mantiene foco, cierre y lectura progresiva.
- El responsive de tabla conserva las etiquetas `data-label` existentes, con estética plana.

## Qué no se copia

- JavaScript de consultas, liquidaciones, bajas, ajustes o exportaciones de RRHH.
- Selectores con nombres de dominio como `.ficha_empleado`, `.zona_peligro` o `.tabla_general` usados sin renombrar.
- HTML condicionado por permisos laborales.
- Valores de negocio, textos, iconos o endpoints.
- Dependencias nuevas o una segunda implementación del tema.

## Hallazgos técnicos que condicionan la migración

1. `app-bancos/_shared/css/paleta_colores.css` no coincide con la referencia y no posee su pareja de compatibilidad en el mismo estado.
2. El head de bancos no carga CSS de tema configurable ni aplica cache busting de forma uniforme.
3. `app-bancos/app/config.php` todavía no declara `tema_claro_css` y `tema_oscuro_css`.
4. La bandeja implementa oscuro después del render mediante JavaScript local; esto puede producir parpadeo de tema.
5. Los filtros se mueven al footer en tiempo de ejecución, lo que altera el orden semántico y complica la réplica de la composición de RRHH.
6. La tabla, el drawer y la paginación ya tienen buen contrato semántico; deben restilarse, no reescribirse sin necesidad.

