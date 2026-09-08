# Comparación de legados y hallazgos de descubrimiento

Fecha: 2026-08-20.

## Conclusión

Los repositorios no son implementaciones alternativas del mismo dominio: son aplicaciones complementarias. `bancos` concilia cheques y `BANCOS_MENSUAL`, alojada históricamente dentro de `gestion_usuarios/aplicaciones/bancos_mensual`, gestiona extractos mensuales, su asignación operativa y cierre. SGUA queda desestimada como integración futura de identidad/permisos; sólo se conserva como evidencia histórica y como contenedor del código de BANCOS_MENSUAL.

| Dimensión | BANCOS | BANCOS_MENSUAL | SGUA |
| --- | --- | --- |
| arquitectura | PHP procedimental + HTML/JS embebido; AJAX | PHP procedimental, AJAX y variantes admin/user | PHP por páginas y redirects |
| dominio | conciliación de cheques | extractos mensuales, asignación y cierre | identidad/permisos históricos; no adoptar |
| base de datos | ERP `ftweb` + 5 tablas auxiliares | ERP `ftweb` + `bancos_mes_*` | tablas legacy de login |
| autenticación | no local; inclusión HTTP no verificable | `idu` legacy; no adoptar | cookie `idu`; desestimada |
| frontend | tres pestañas, jQuery y exportación PDF/CSV | interfaces admin y user con jQuery | HTML/CSS con formularios |

## Comportamiento que debe preservarse o confirmar antes de reescribir

1. En BANCOS, la conciliación de cheques, la importación tabular, las configuraciones general/específica por banco, la tolerancia de monto, el scoring de candidatos, la selección manual y las salidas de excepción.
2. En BANCOS_MENSUAL, la carga de extractos por período/cuenta, asociación y exclusividad de valor/asiento, asignación de responsable, estados, mensajería y cierre.
3. La semántica contable completa de confirmar una conciliación o crear/modificar/fusionar asientos, incluidos estados, tipos de operación/valor, cuentas de débito/crédito y numeración de asiento. Los identificadores heredados requieren validación con negocio/ERP antes de codificarse como regla canónica.
4. De `nueva_app`, el contrato de sesión, autorización, contexto frontend y separación entre Core, compartido, aplicación y compatibilidad.

## Decisiones canónicas: aún no tomadas

No hay evidencia suficiente para decidir como comportamiento canónico:

- si las cuentas contables por banco son globales (`N/A`) o deben ser específicas de cada cuenta bancaria;
- si se permite y cómo se audita una conciliación forzada;
- qué identidad debe figurar como creadora/modificadora de los efectos ERP;
- cómo configurar la aplicación y sus permisos internos en el Hub de `nueva_app`;
- qué significado funcional tienen los códigos de estado/tipo heredados y si siguen vigentes.

## Prioridades para la siguiente etapa

1. Crear el proyecto desde `nueva_app` y acordar su aplicación/roles/permisos internos en el Hub.
2. Modelar y probar la operación de conciliación como transacción atómica y auditable antes de implementar escrituras ERP.
3. Obtener ejemplos reales de importación y de conciliaciones válidas/rechazadas para convertir las reglas inferidas en pruebas de caracterización.
4. Definir claves, restricciones, pertenencia de configuración y migraciones reversibles para las tablas auxiliares.
5. Documentar con el área contable los códigos del ERP y la política para conciliaciones forzadas, duplicados y reversas.

## Navegación y UX

El relevamiento visual posterior confirmó que los legados resuelven tareas distintas mediante panel fijo de filtros, tablas densas, pestañas y controles de acción mezclados con la consulta. Se conserva la necesidad de filtrar por cuenta/período/estado y de visualizar mensajes, asociaciones y borradores; no se adopta su implementación con estilos inline, alturas fijas, pestañas sin URL ni acciones críticas en la grilla. La propuesta de reorganización está en [navigation-and-ui.md](../ux/navigation-and-ui.md).
