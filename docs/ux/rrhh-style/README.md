# Sistema visual de `rrhh_consulta` para `app-bancos`

Estado: implementado el 2026-09-09; pendiente únicamente la evidencia sobre la instalación integrada con datos reales.

## Objetivo

Replicar en `app-bancos` el lenguaje visual vigente de `rrhh_consulta` sin trasladar su dominio, su HTML particular ni su archivo JavaScript monolítico. La interfaz bancaria debe conservar sus rutas, contexto cuenta-período, estados de lectura, paginación por cursor y detalle de movimientos.

La réplica se considera correcta cuando ambas aplicaciones comparten:

- la misma arquitectura de tema claro/oscuro;
- las mismas bases cromáticas y reglas de derivación;
- la misma densidad, continuidad entre paneles, jerarquía y tratamiento de controles;
- los mismos patrones visuales de estado, foco, carga, vacío y error;
- los mismos cortes responsive y reglas de movimiento reducido;
- una apariencia reconociblemente común en desktop y móvil, aunque muestren datos distintos.

## Documentos

1. [Relevamiento y diferencias](audit.md): fuentes revisadas, rasgos observados y distancia respecto del front actual.
2. [Fundamentos visuales](foundations.md): temas, tokens, tipografía, espaciado, bordes, sombras y movimiento.
3. [Composición y responsive](layout-responsive.md): shell, paneles, grillas y comportamiento por ancho.
4. [Catálogo de componentes](components.md): contrato visual y semántico para cada pieza bancaria.
5. [Interacción, estados y accesibilidad](states-accessibility.md): máquinas de estado, teclado, foco y anuncios.
6. [Mapa de implementación](implementation-plan.md): cambios por archivo, fases, riesgos y límites.
7. [Plan de validación visual](visual-qa.md): matriz de capturas y criterios de aceptación.

## Fuentes de verdad

La referencia primaria es el código de `rrhh_consulta`, revisado el 2026-09-09:

- `app/css/home.css`: estructura, densidad y responsive;
- `app/css/tema_claro.css` y `app/css/tema_oscuro.css`: apariencia por tema;
- `_shared/css/paleta_colores.css` y `_shared/css/compatibilidad_colores.css`: bases compartidas;
- `app/html/home.html`: jerarquía, semántica y composición;
- `app/js/home.js`: estados e interacciones observables;
- `_shared/php/temas.php`, `_shared/html/html-head.html`, `_shared/html/header.php` y `_shared/js/header.js`: contrato de selección de tema;
- `tests/home-ui.test.js`, `tests/temas-shared.test.js` y `tests/temas-shared.test.php`: invariantes comprobables.

La fuente de verdad funcional de `app-bancos` sigue siendo su código y [navigation-and-ui.md](../navigation-and-ui.md). Si una decisión visual entra en conflicto con seguridad, permisos, semántica bancaria o accesibilidad, prevalece el contrato funcional y se adapta la presentación.

## Decisiones rectoras

- Se replica un sistema visual, no una pantalla de RRHH.
- Se conserva HTML semántico: formulario, tabla, navegación, diálogo y regiones de estado.
- Se reemplaza el selector de tema local de la bandeja por el contrato compartido de la aplicación.
- Estructura y color quedan separados en archivos distintos.
- Los estados no dependen sólo del color ni de iconos.
- No se copia `rrhh_consulta/app/js/home.js`: contiene reglas laborales ajenas y más de una responsabilidad.
- No se habilitan escrituras bancarias como parte de esta migración visual.

## Definición de terminado

La implementación futura estará terminada únicamente cuando:

- no queden tokens visuales `--bandeja-*` que dupliquen el sistema de tema;
- el tema elegido se aplique antes de pintar la página y sobreviva a la recarga mediante la cookie de aplicación;
- claro y oscuro pasen las mismas pruebas de estructura y contraste;
- desktop, tablet y móvil cumplan la matriz de [visual-qa.md](visual-qa.md);
- teclado, foco, lector de pantalla y movimiento reducido se verifiquen;
- las pruebas funcionales actuales de la bandeja sigan pasando;
- no cambien consultas, cursores, permisos ni escrituras.
