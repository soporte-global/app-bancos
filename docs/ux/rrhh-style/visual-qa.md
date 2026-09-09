# Plan de validación visual

## Propósito

Demostrar que `app-bancos` adopta el lenguaje visual de `rrhh_consulta` y que conserva su comportamiento. Las capturas son evidencia de una validación; no reemplazan pruebas de interacción ni contraste.

## Viewports obligatorios

| Nombre | Viewport | Motivo |
| --- | --- | --- |
| Desktop amplio | 1440 × 900 | Tabla completa, densidad y panel continuo. |
| Desktop bajo | 1366 × 768 | Comprobar que no se oculta información por altura. |
| Tablet | 900 × 1000 | Corte principal y reordenamiento. |
| Móvil | 390 × 844 | Uso habitual y tabla apilada. |
| Móvil estrecho | 360 × 800 | Último corte de datos y controles. |

Cada caso se captura en claro y oscuro.

## Estados obligatorios

1. Sin cuenta/período.
2. Contexto válido antes de consultar.
3. Cargando.
4. Resultados con al menos diez filas y textos largos.
5. Segunda página con anterior y siguiente según corresponda.
6. Sin movimientos.
7. Error recuperable.
8. Filtros activos y uno removido.
9. Drawer abierto con mensajes, asociación y borrador.
10. Drawer con secciones sin datos.
11. Foco visible en campo, botón, paginación y cerrar.
12. Movimiento reducido habilitado.

## Comparación de estilo

La revisión lado a lado debe confirmar:

- mismo fondo y progresión de superficies que RRHH;
- paneles contiguos, bordes de 1 px y ausencia de sombras decorativas;
- tipografía monoespaciada y jerarquía equivalente;
- etiquetas pequeñas en mayúsculas;
- controles de 38 px y radios de 8 px;
- selección/foco con el mismo acento;
- estados derivados de la paleta compartida;
- densidad semejante: el viewport muestra contexto, selector y una porción útil de resultados;
- reordenamiento equivalente en 900 y 650 px;
- sin flash perceptible al cargar oscuro.

No se exige pixel perfect entre dominios distintos. Sí se exige equivalencia de sistema: color, escala, geometría, densidad, jerarquía, estado y responsive.

## Criterios funcionales de no regresión

- Consultar conserva parámetros y dispara una sola solicitud/navegación.
- Limpiar filtros actualiza URL y contexto correctamente.
- Cursor inválido sigue rechazado por backend.
- Anterior/siguiente conservan cuenta, período y límite.
- El detalle muestra el movimiento correcto.
- Escape, overlay y botón cierran el drawer.
- El foco vuelve al disparador.
- No aparece ninguna acción de escritura.
- Header, login, diagnóstico y footer siguen utilizables en ambos temas.

## Accesibilidad manual

- Recorrer toda la página sólo con teclado.
- Verificar orden de foco contra orden visual.
- Probar zoom al 200% sin pérdida de contenido ni scroll horizontal global.
- Probar lector de pantalla: encabezados, labels, tabla, estado de carga, error y diálogo.
- Medir contraste de texto, controles, foco y pills en ambos temas.
- Confirmar que un usuario con reducción de movimiento no recibe spinners o transiciones continuas.

## Evidencia

Crear una carpeta nueva, por ejemplo:

```text
docs/ux/evidence/rrhh-style/
  README.md
  antes-desktop-1440.png
  despues-claro-desktop-1440.png
  despues-oscuro-desktop-1440.png
  despues-claro-mobile-390.png
  despues-oscuro-mobile-390.png
  estado-cargando.png
  estado-vacio.png
  estado-error.png
  detalle-abierto.png
  foco-visible.png
```

El `README.md` de evidencia debe registrar fecha, commit, URL/fixture, navegador, viewport, tema, pasos para reproducir y resultado de pruebas. No sobrescribir `docs/ux/evidence/task-*`: representan cortes históricos.

## Aprobación

La migración visual puede cerrarse cuando:

- todos los casos obligatorios tienen evidencia o una justificación documentada;
- no existen diferencias críticas de estilo contra esta especificación;
- no hay regresiones funcionales ni de teclado;
- contraste y zoom cumplen;
- las pruebas automatizadas pasan;
- cualquier desviación deliberada queda registrada en el README de evidencia.

