# Interacción, estados y accesibilidad

## Modelo de estado de consulta

```text
inicial ── consultar ──> cargando ── éxito con filas ──> listo
   │                       │  ├──── éxito sin filas ───> vacio
   │                       │  ├──── datos incompletos ─> parcial
   │                       │  └──── fallo ─────────────> error
   │                       └──── requisito ausente ────> no_disponible
   └──────── cambio de contexto invalida el resultado anterior
```

Reglas:

- `data-estado` refleja el estado visual; no reemplaza el estado de aplicación.
- `aria-busy="true"` se asigna a la región que actualiza, no al documento entero.
- Al iniciar una nueva consulta se conserva visible el contexto elegido.
- Un AbortError causado por una consulta posterior no se presenta como error.
- Un reintento reutiliza el contexto vigente, no valores visuales obsoletos.
- Estado y texto cambian juntos; nunca se comunica sólo con color.

## Estado del tema

El tema es parte del shell, no de la bandeja:

1. PHP resuelve rutas clara/oscura y valida que existan.
2. Lee únicamente `claro` u `oscuro` de una cookie derivada de `FolderName`.
3. El head carga directamente la hoja resuelta antes del contenido.
4. El selector aparece en Ajustes de color sólo si existen ambas hojas.
5. Al cambiar, actualiza el `href` del link y la cookie con `SameSite=Lax`.

Se elimina `bandeja-tema` de `localStorage`, `data-tema` de la raíz y `bandeja-tema-oscuro` de `body`. Debe existir una sola autoridad de tema.

## Navegación por teclado

- El orden de tabulación sigue el DOM y el orden visual.
- No se mueven filtros al footer después de cargar.
- Radio buttons o pestañas conservan foco visible; si se implementa navegación tipo tabs, flechas cambian opción según el patrón ARIA.
- Enter envía el formulario cuando es válido.
- Escape cierra el drawer y devuelve foco.
- Anterior/siguiente deshabilitados no reciben foco.
- El scroll horizontal de tabla es alcanzable por teclado mediante una región nombrada.

## Foco

Todos los elementos interactivos usan `:focus-visible` con un outline de 3 px y offset de 2 px. No se elimina el outline sin reemplazo equivalente. Al producir un error de validación:

1. se marca el campo con `aria-invalid`;
2. se publica el mensaje asociado;
3. se enfoca el primer campo inválido;
4. no se abre un modal genérico sólo para explicar el error.

## Lectores de pantalla

- Un único `h1` identifica la página; puede ser visualmente oculto si el header ya muestra el nombre.
- Cada `section` importante tiene encabezado visible o `aria-label` estable.
- `output` se usa para contexto calculado; los estados dinámicos usan regiones live.
- Los iconos decorativos tienen `aria-hidden="true"`.
- Botones de icono tienen `aria-label` específico.
- La tabla conserva su semántica en desktop y móvil; CSS no cambia elementos a roles incompatibles.
- El drawer tiene nombre, modalidad y foco contenido.

## Contraste y color

- Texto normal: objetivo WCAG AA de 4.5:1.
- Texto grande y componentes: mínimo 3:1.
- Foco: mínimo 3:1 respecto de colores adyacentes.
- Los estados incluyen palabra o código legible.
- Las filas alternas, hover y selección deben conservar contraste en ambos temas.
- Se verifican combinaciones calculadas con el motor real que soporta `color-mix()`.

## Movimiento, carga y percepción

- Respetar `prefers-reduced-motion`.
- Skeletons son decorativos y llevan `aria-hidden="true"`.
- La región anuncia “Cargando movimientos” una vez, sin repetir por cada placeholder.
- Evitar parpadeo entre claro y oscuro cargando el CSS correcto desde el servidor.
- No ocultar contenido anterior antes de que exista una señal clara de carga si hacerlo deja la pantalla en blanco.

## Contenido

- Español rioplatense consistente con las pantallas existentes: “Elegí”, “Completá”, “Podés”.
- Mensajes breves, orientados a recuperación.
- No exponer nombres de tablas, esquemas o flags de debug a usuarios finales.
- Identificadores técnicos se muestran sólo cuando ayudan a soporte o conciliación.
- Los textos del front de RRHH son ejemplos de tono, no contenido para copiar.

