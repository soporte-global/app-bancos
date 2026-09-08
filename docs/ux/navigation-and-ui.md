# UX/UI y navegación objetivo

Estado: diseño de referencia para implementar después de validar lecturas. Fecha: 2026-09-08. No modifica permisos, estados ni operaciones contables.

## Evidencia relevada

| Fuente | Estructura | Aportes que se conservan | Problemas que no se replican |
| --- | --- | --- | --- |
| BANCOS mensual, usuario | Panel fijo de filtros a la izquierda, botonera superior y tabla de movimientos | alcance por cuenta/período/estado, lectura de mensajes, asociación de valor/asiento y trabajo por selección | panel fijo de 15%, controles superpuestos, selección múltiple poco explícita y acciones de cierre al lado de filtros |
| BANCOS mensual, administrador | selección de cuenta, pestañas ABM/Resumen, tabla de períodos y acciones de actualización | separación entre configuración/administración y consulta operativa; resumen contextual de cuenta y período | cambio de cuenta aislado, pestañas sin URL, botones con abreviaturas y acciones críticas sin jerarquía visual |
| BANCOS | pestañas Importación/Configuración/Resumen y pasos internos | secuencia visible de preparar, configurar y revisar; pantalla de resumen comparativa | proceso lineal acoplado a una sola pantalla, alturas fijas y estados bloqueados mediante capas visuales |

Las fuentes se revisaron como evidencia de comportamiento, no como patrón técnico o visual a copiar. La aplicación nueva usa rutas internas autorizables, consultas paginadas por cursor y estados canónicos.

## Principios de diseño

1. **La cuenta y el período son contexto, no filtros dispersos.** Se seleccionan una vez y se muestran de forma persistente como una miga: `Extractos / Cuenta / Período`.
2. **La consulta y las mutaciones se distinguen.** La bandeja es una ruta de lectura; preparar, asociar o cerrar requieren rutas/acciones separadas, permiso explícito y confirmación.
3. **Las acciones dependen del estado.** Cada fila muestra estado, responsable, asociación, mensaje y borrador. Sólo se ofrecen acciones permitidas para ese estado y rol.
4. **La navegación es recuperable.** Cuenta, período, filtros y cursor están en URL; se ofrece volver a la página anterior sin perder el contexto.
5. **La densidad no sacrifica legibilidad.** Tabla con cabecera fija, contraste AA como mínimo, importes alineados a la derecha, fechas uniformes y detalle progresivo.
6. **Móvil no es una tabla comprimida.** En pantallas angostas se muestran primero fecha, referencia, importe, estado y alertas; el detalle se abre por fila.

## Arquitectura de información propuesta

```text
Inicio
└── Extractos mensuales
    ├── Bandeja
    │   ├── Selector de cuenta y período
    │   ├── Lista paginada de movimientos
    │   └── Detalle de movimiento
    │       ├── Asociación / borrador
    │       ├── Mensajes
    │       └── Historial de estado
    ├── Importaciones                 (permiso operativo)
    └── Configuración                 (permiso administración)

Conciliación de cheques               (módulo separado)
└── Importar / Configurar / Revisar
```

`Bandeja` será el primer destino operativo. Importación, configuración y conciliación no se muestran como pestañas de la misma pantalla: son módulos con permisos y riesgos distintos.

## Flujo de bandeja

1. Entrar en **Extractos mensuales / Bandeja**.
2. Elegir cuenta y período; si no hay contexto, mostrar un estado inicial con búsqueda, no una tabla vacía.
3. Mostrar una barra de contexto con cuenta, período, cantidad de resultados cargados y filtros activos.
4. Recorrer por cursor con controles **Anterior** y **Siguiente**. El cursor sigue siendo opaco; el usuario ve “Página” o rango visible, no IDs internos.
5. Abrir el detalle en panel lateral o ruta hija, sin perder la lista ni el cursor.
6. Desde el detalle, las acciones futuras se agrupan como `Preparar`, `Asociar`, `Mensajes` y `Cierre`; las no autorizadas se explican, no se ocultan silenciosamente.

## Diseño de la tabla

### Columnas base de escritorio

| Grupo | Contenido |
| --- | --- |
| Identificación | fecha, referencia, descripción |
| Importe | débito, crédito; nunca ambos destacados a la vez |
| Operación | estado, responsable cuando exista, asociación resumida |
| Seguimiento | indicador de mensaje y resumen de borrador |
| Navegación | abrir detalle; no acciones destructivas en línea |

El estado se expresa con texto y color, nunca sólo color. Mensajes y borradores se resumen en la fila; sus cuerpos, líneas y totales completos pertenecen al detalle. Los IDs ERP se muestran sólo como información secundaria o de diagnóstico.

## Estados visuales y feedback

| Situación | Tratamiento |
| --- | --- |
| Sin cuenta/período | instrucción clara y selector visible |
| Sin movimientos | mensaje contextual, sin tabla vacía extensa |
| Cargando | esqueleto o indicador localizado; no bloquear toda la pantalla |
| Error de consulta | mensaje legible, conservar filtros y permitir reintentar |
| ABIERTO | etiqueta neutra/informativa |
| PARA_CERRAR | etiqueta de atención y explicación de siguiente responsable |
| CERRADO | etiqueta estable, sin acción de reapertura |
| Acción irreversible futura | confirmación con impacto, cuenta/período, motivo y resultado de auditoría |

## Secuencia de implementación posterior

1. Convertir la pantalla actual en layout con barra de contexto, navegación anterior/siguiente y detalle de fila, manteniendo el contrato de lectura actual.
2. Incorporar selector de cuentas desde una consulta ERP de sólo lectura, con búsqueda y etiqueta inequívoca de cuenta/nodo/banco.
3. Añadir filtros permitidos y su serialización en URL: estado, responsable y presencia de asociación/mensaje; medir antes de agregar filtros textuales costosos.
4. Implementar detalle de movimiento y sus secciones de asociación, borrador, mensajes e historial.
5. Recién entonces diseñar acciones operativas bajo permisos Hub, confirmaciones e idempotencia.

## Entregas incrementales

- **Tarea 1 — Layout responsive de la bandeja:** implementada y validada técnicamente el 2026-09-08. La vista usa estilos externos, breakpoints explícitos y filas convertibles a cards en móvil. La evidencia y el checklist están en [`docs/ux/evidence/task-1/`](evidence/task-1/README.md). Pendiente de aprobación explícita antes de iniciar la barra de contexto de la Tarea 2.

## Decisiones explícitas

- No se conserva la navegación por pestañas como única forma de cambiar de módulo: las rutas permiten permisos, enlaces y recarga segura.
- No se repite el panel lateral fijo ni estilos inline heredados; el diseño será responsive y tendrá componentes reutilizables.
- No se expone `Autoaconciliar`, `Cerrar auto` ni acciones de cierre mientras la regla contable y el permiso Hub estén pendientes.
- La primera versión conserva la densidad de información de los legados, pero prioriza lectura, contexto y trazabilidad sobre operaciones masivas.
