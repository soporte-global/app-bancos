# Validación productiva para el modelo objetivo

Fecha: 2026-08-25. Fuente: PostgreSQL `ftweb`, esquema `public`. Método: consultas agregadas dentro de transacciones de solo lectura. No se consultaron valores textuales de negocio ni se efectuaron cambios en producción.

## Propósito

Validar las claves, relaciones, reglas de importe, estados y alcances de configuración que condicionan el diseño de las futuras tablas `global_prod.bancos_*`. Este documento complementa el catálogo en [legacy-production-schema.md](legacy-production-schema.md) y fundamenta las decisiones actualizadas en [proposed-target-model.md](proposed-target-model.md).

## Resultado ejecutivo

El modelo objetivo es viable con dos correcciones importantes respecto de la propuesta inicial:

1. La migración no puede usar `id_movimiento` como identificador técnico; debe usar `(id_periodo, serial_seq)` y generar una PK propia.
2. La configuración debe admitir alcance global y por cuenta, porque ambos usos existen en los legados.

No se detectaron huérfanos entre movimientos, períodos, asignaciones, valores, asientos o exclusiones cuando se los une por los identificadores heredados. Esa consistencia observada no reemplaza FKs en el modelo nuevo.

## Controles ejecutados y evidencia

| Control | Resultado | Implicación para el destino |
| --- | --- | --- |
| unicidad de `id_movimiento` | 1.055 IDs duplicados, 2.407 filas afectadas y 1.352 filas excedentes | no usarlo como PK ni FK destino |
| unicidad de `(id_periodo, serial_seq)` | 0 duplicados en 979.308 movimientos | adoptar como clave de migración y conservar `numero_fila_origen` |
| períodos de los IDs duplicados | ningún ID repetido aparece en períodos distintos | la ambigüedad está dentro del período y no se resuelve sólo con el ID |
| relaciones heredadas | 0 movimientos sin período; 0 asignaciones, valores, asientos o exclusiones sin movimiento | respaldar el backfill, pero crear FKs nuevas |
| impacto de IDs duplicados | 1.055 asignaciones de usuario, 53 de valor, 871 de asiento y 924 exclusiones referencian IDs duplicados | requieren correspondencia explícita a la fila `(id_periodo, serial_seq)` durante la migración |
| estado | `CERRADO`: 898.879; `PARA CERRAR`: 60.085; `ABIERTO`: 18.512; sin nulos | catálogo inicial de tres estados; faltan transiciones históricas |
| importe de extracto | 0 filas con crédito y débito simultáneos; 0 sin importe; 38 con importe negativo | crear `CHECK` de exclusividad; caracterizar las 38 excepciones antes de rechazar negativos |
| escala monetaria | máximo de dos decimales en crédito, débito, debe y haber; ERP contable en `numeric(20,5)` | usar `numeric(20,5)` cuando el importe se asocie o genere efectos ERP |
| total del período | 3 de 2.358 períodos no coinciden con el conteo de movimientos; diferencia absoluta acumulada 1.627 | tratar `total_movs` como derivado, no como dato rector |
| valor asignado | 18.060 filas; sin `id_valor` ni monto nulos; `mult_valor` nunca verdadero | asociación a valor es actualmente 0..1 por ID heredado |
| asiento asignado | 917.688 filas; sin `id_asiento` ni monto nulos; `mult_asiento` verdadero en 468.501; `debita` nulo en todas las filas | no trasladar `debita`; validar la semántica de `mult_asiento` |
| asociaciones incompatibles | 1 ID con valor y asiento; 2 valores y 78 asientos sin exclusión | la exclusividad no es perfecta y debe expresarse en reservas activas auditables |
| borradores | 5.148 cabeceras y 10.762 líneas; 0 cabeceras o líneas huérfanas | migrar a borrador y líneas con FK obligatoria |
| exclusiones | 935.700 filas; 15.864 con valor y asiento, 202 con borrador, ninguna sin recurso | una exclusión mezcla varias clases de recurso; separar reserva y tipo de recurso |
| configuración BANCOS | 19 mapeos bancarios con `cuenta = 'N/A'`; una única cuenta distinta en esa tabla | evidencia de alcance global en parte de BANCOS |
| configuración BANCOS_MENSUAL | sin `N/A`; 85 cuentas en mapeos bancarios y 86 en mapeos de valores | evidencia de alcance por cuenta en mensual |
| calidad mínima de configuración | `bancos_extracto_config` no duplica `(banco, subtipo_valor)`; reglas y RAAU no tienen nulos en los campos revisados | conservar claves funcionales y formalizarlas con restricciones |
| mensajería | 13 mensajes, todos con movimiento y emisor; actividad entre 2019-09-24 y 2020-05-19 | el historial es escaso y no permite definir retención ni lectura canónica |

## Decisiones de diseño derivadas

- Toda tabla nueva se crea en `global_prod` y comienza con `bancos_`.
- `bancos_movimiento_extracto` tendrá PK propia; la migración conservará `id_periodo` y `serial_seq` como correspondencia de origen única.
- `bancos_configuracion` modelará el alcance `GLOBAL` o `CUENTA`; `bancos_configuracion_cuenta` sólo se usará cuando corresponda una cuenta explícita.
- `bancos_asociacion_movimiento` y `bancos_reserva_recurso` conservarán el tipo de destino y su vigencia, en lugar de sobrecargar exclusiones.
- `bancos_historial_asignacion` reemplazará el único estado mutable y permitirá auditar las transiciones futuras.
- Los importes nuevos que puedan asociar o generar efectos ERP usarán `numeric(20,5)`, igual que el ERP. La entrada de extractos se validará a dos decimales cuando ese sea su formato de origen.

## Límites y acciones restantes

Los datos permiten responder claves, alcance, estados observados, escala monetaria e integridad básica. No permiten inferir transiciones de estados, política de retención, autorización de reversas/fusiones ni la regla de una o varias asociaciones de asiento. Esas decisiones deben validarse con los responsables contables antes de implementar DDL o migraciones.
