# Mapa de implementación

## Alcance

Este plan migra la presentación de la bandeja mensual y el contrato compartido de temas. No cambia dominio, SQL, endpoints, cursores, permisos ni habilita mutaciones.

## Fase 0: línea base

1. Ejecutar las pruebas actuales de bandeja y guardar resultados.
2. Regenerar capturas de los estados existentes en 1440 px y 390 px.
3. Registrar qué pruebas requieren base debug o fixture PHP.
4. No actualizar evidencia histórica: crear una carpeta de evidencia nueva.

Salida: comportamiento actual reproducible y comparación antes/después.

## Fase 1: contrato compartido de temas

Archivos a incorporar o adaptar desde `rrhh_consulta`:

| Destino | Acción |
| --- | --- |
| `_shared/css/paleta_colores.css` | Actualizar bases y carga de compatibilidad. |
| `_shared/css/compatibilidad_colores.css` | Incorporar aliases históricos calculados para no romper consumidores compartidos. |
| `_shared/php/temas.php` | Incorporar resolución y validación de hojas. |
| `_shared/html/html-head.html` | Cargar CSS estructural y link de tema con cache busting. |
| `_shared/html/header.php` | Mostrar el selector sólo cuando existan ambos temas. |
| `_shared/js/header.js` | Alternar link/cookie desde el selector compartido. |
| `app/config.php` | Declarar `tema_claro_css` y `tema_oscuro_css`. |

Antes de copiar, comparar cada archivo porque `app-bancos` evolucionó por separado. Integrar sólo el contrato de tema y preservar cambios bancarios o de seguridad.

Pruebas requeridas:

- portar/adaptar `temas-shared.test.php` y `temas-shared.test.js`;
- verificar cero, una y dos hojas configuradas;
- cookie inválida vuelve a claro;
- rutas no salen de la raíz pública;
- selector y link publican atributos coherentes.

## Fase 2: separar estructura y apariencia

1. Crear `app/css/bancos.css` desde las reglas estructurales necesarias de `app/css/shared.css`.
2. Crear `app/css/tema_claro.css` y `app/css/tema_oscuro.css` con bases y reglas de apariencia.
3. Mantener temporalmente `shared.css` sólo como puente o retirarlo en el mismo cambio cuando todas sus reglas estén clasificadas.
4. Encapsular reglas bajo `.bancos-app`; no usar selectores globales de `body` salvo que pertenezcan al shell.
5. Eliminar literales de color del CSS estructural.

Clasificación:

- estructura: display, grid, flex, tamaños, padding, overflow, posición, responsive;
- tema: color, background, border-color, outline-color y sombras;
- compartido: únicamente lo consumido por más de una aplicación sin semántica bancaria.

## Fase 3: adaptar markup

Archivo principal: `app/html/bandeja-mensual.php`.

Cambios:

- agregar raíz `.bancos-app` y un `h1` inequívoco;
- integrar contexto, filtros y resultado como paneles contiguos;
- mantener `form`, nombres de campos y query params;
- mantener tabla, `data-label`, templates de detalle y cursor;
- retirar el botón local de tema;
- no mover el formulario al footer;
- conservar estados PHP inicial/error/vacío/listo;
- incorporar `aria-busy`, focus targets y encabezados donde falten.

No se cambian nombres HTTP ni DTOs para satisfacer CSS.

## Fase 4: simplificar JavaScript visual

Archivo: `app/js/shared.js`.

Retirar:

- `configurarTema` y la clave `bandeja-tema` de `localStorage`;
- mutaciones de `data-tema` y clases de tema en `body`;
- movimiento del formulario al footer;
- clases globales de scroll que sólo compensan ese movimiento.

Conservar y reforzar:

- filtros activos y limpieza;
- carga y reintento;
- historial de cursores;
- apertura/cierre de detalle;
- devolución de foco;
- actualización accesible de contexto.

Agregar focus trap al drawer si aún no existe y asegurar que handlers sean idempotentes.

## Fase 5: componentes y responsive

Orden sugerido:

1. shell, fondo y contexto;
2. filtros y botones;
3. encabezado/métricas;
4. tabla y estados;
5. paginación;
6. drawer;
7. 900, 650, 520, 480, 420 y 360 px;
8. movimiento reducido.

Cada componente se valida en claro y oscuro antes de continuar.

## Fase 6: pruebas y evidencia

Actualizar o agregar pruebas de contrato para:

- separación de colores y estructura;
- ausencia del tema local;
- ausencia de movimiento de nodos al footer;
- estados y ARIA;
- breakpoints requeridos;
- drawer con Escape, trap y retorno de foco;
- tabla móvil y paginación por cursor;
- regresión visual según [visual-qa.md](visual-qa.md).

Ejecutar al menos:

```powershell
php tests/VerificarBandejaResponsiveTest.php
php tests/VerificarEstadosVisualesTest.php
php tests/VerificarDetalleMovimientoTest.php
php tests/VerificarNavegacionFiltrosTest.php
php tests/VerificarCorreccionesVisualesTest.php
php tests/VerificarRegresionVisualTest.php
```

Además, ejecutar las pruebas de tema que se incorporen y `git diff --check`.

## Riesgos y mitigaciones

| Riesgo | Mitigación |
| --- | --- |
| Actualizar la paleta rompe login/header. | Mantener aliases calculados y capturar también pantallas compartidas. |
| Flash de tema incorrecto. | Resolver CSS en PHP antes del primer render. |
| Tabla demasiado densa en móvil. | Mantener `data-label`, permitir scroll y probar datos largos reales. |
| Copia literal acopla bancos a RRHH. | Renombrar por rol y conservar contratos bancarios. |
| Cambios visuales alteran navegación/foco. | No reordenar DOM; pruebas de teclado y diálogo. |
| `color-mix()` varía por navegador. | Validar navegadores objetivo y ofrecer fallback sólo si el parque real lo exige. |

## Rollback

La migración debe dividirse en commits coherentes: contrato de tema, CSS/markup y JS/pruebas. El rollback visual no toca datos. Durante la transición puede conservarse `shared.css` detrás de una inclusión única, pero no deben coexistir dos selectores de tema visibles ni dos autoridades de persistencia.

