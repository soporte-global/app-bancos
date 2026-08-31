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
| unicidad de `id_movimiento` | 1.055 IDs duplicados, 2.407 filas afectadas y 1.352 filas excedentes | son repeticiones exactas de negocio; conservar sólo el menor `serial_seq` y mantener trazabilidad |
| unicidad de `(id_periodo, serial_seq)` | 0 duplicados en 979.308 movimientos | adoptar como clave de migración y conservar `numero_fila_origen` |
| períodos de los IDs duplicados | ningún ID repetido aparece en períodos distintos | la ambigüedad está dentro del período y no se resuelve sólo con el ID |
| relaciones heredadas | 0 movimientos sin período; 0 asignaciones, valores, asientos o exclusiones sin movimiento | respaldar el backfill, pero crear FKs nuevas |
| impacto de IDs duplicados | 1.055 asignaciones de usuario, 53 de valor, 871 de asiento y 924 exclusiones referencian IDs duplicados | requieren correspondencia explícita a la fila `(id_periodo, serial_seq)` durante la migración |
| estado | `CERRADO`: 898.879; `PARA CERRAR`: 60.085; `ABIERTO`: 18.512; sin nulos | catálogo inicial de tres estados; faltan transiciones históricas |
| importe de extracto | 0 filas con crédito y débito simultáneos; 0 sin importe; 38 con importe negativo | error confirmado de carga: invertir columna y signo durante el backfill |
| escala monetaria | máximo de dos decimales en crédito, débito, debe y haber; ERP contable en `numeric(20,5)` | usar `numeric(20,5)` cuando el importe se asocie o genere efectos ERP |
| total del período | 3 de 2.358 períodos no coinciden con el conteo de movimientos; diferencia absoluta acumulada 1.627 | tratar `total_movs` como derivado, no como dato rector |
| valor asignado | 18.060 filas; sin `id_valor` ni monto nulos; `mult_valor` nunca verdadero | asociación a valor es actualmente 0..1 por ID heredado |
| asiento asignado | 917.688 filas; sin `id_asiento` ni monto nulos; `mult_asiento` verdadero en 468.501; `debita` nulo en todas las filas | no trasladar `debita`; la semántica de fusión se aplica por recurso: si algún vínculo del asiento es múltiple, el asiento queda compartido |
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
- El backfill normaliza `debito < 0` a `credito = abs(debito)` y `credito < 0` a `debito = abs(credito)`. La traza conserva ambos importes originales.
- Para cada grupo duplicado por `id_movimiento`, se conserva la fila de menor `serial_seq`; las demás se vinculan al movimiento canónico mediante la tabla de trazabilidad.
- El mapa de cuentas acepta sólo normalización determinista de separadores y ceros iniciales. El relevamiento resolvió así 15 de 16 configuraciones con formato distinto; no se habilita similitud aproximada.
- La excepción `0191-168-005919/1` se asocia manualmente con `19116800059191` (CREDICOOP, SOC DON BOSCO): ambos registros pertenecen a la configuración `001/GENERAL` y sólo difieren por un cero omitido.
- El período `191168194623122022` se excluye del destino: no fue validado, su cuenta no existe en ERP y contiene 748 movimientos que se preservan sólo en la trazabilidad.
- `NNNNN` es un placeholder confirmado: se migra como configuración de alcance `GLOBAL`, limitada a los diez bancos que le están asociados, y no como cuenta bancaria ERP.
- Las 4.673 reglas mensuales tienen el sentido nulo. El subtipo no permite inferirlo: algunos subtipos aparecen como crédito y débito en BANCOS. Se conserva la semántica mediante `sentido = 'A'` (ambos).
- Las tablas legacy `*_cuentasbanco` contienen cuentas contables base que no son redundantes con los mapeos por subtipo: 19 de 19 filas en BANCOS y 1.444 de 1.603 en BANCOS_MENSUAL no aparecen en `*_cuentasvalores`. El destino las conserva en `bancos_configuracion.cuenta_contable_zetti_id`.

## Límites y acciones restantes

Los datos permiten responder claves, alcance, estados observados, escala monetaria e integridad básica. La fusión de asientos fue confirmada: entre los recursos usados por más de un movimiento hay 9.081 totalmente marcados como múltiples y 389 mixtos; en los mixtos se conserva la semántica de recurso compartido cuando exista alguna marca múltiple. De los 58 asientos reutilizados sin ninguna marca múltiple, 20 tienen un único movimiento cuyo importe coincide con sus líneas ERP y se conserva sólo esa asociación; los otros 38 no tienen coincidencia y se omiten sus 124 asociaciones. El único valor ERP duplicado (`103500000012894309`) se resuelve explícitamente como duplicación o rectificación operativa: se conserva el débito del 16/03/2020 y se traza/omite la imputación del 01/06/2020. El preflight de `012` bloquea cualquier otro valor duplicado no declarado. El asiento legacy `0` se trata como centinela y no se migra.

## Evidencia y monitoreo de la migración 012

`012_bancos_migrar_dependencias.sql` está dividido en siete fases confirmadas por separado: preparación de conjuntos, preflight, asociaciones a valor, asociaciones a asiento, reservas a valor, reservas a asiento, borradores y mensajes. Antes de las fases de mayor volumen materializa la traza canónica y los agregados por asiento en tablas auxiliares indexadas, por lo que no repite los joins de gran escala contra la traza original. Tras la carga informada como completa, sus registros y trazas deben incorporarse a la conciliación de corte, no descartarse.

La tabla `global_prod.bancos_migracion_ejecucion` conserva el inicio, fin, estado y filas afectadas de cada fase. Sus filas `OK`, junto con los conteos de destino, son evidencia para el informe de corte. El monitor [012_bancos_monitorear.sql](../../app/sql/migraciones/012_bancos_monitorear.sql) se conserva para una eventual repetición controlada o diagnóstico; una fila en `EN_CURSO` identifica el bloque activo y `pg_blocking_pids` muestra bloqueadores reales.

No usar `pg_terminate_backend` como primer recurso. Si una fase no progresa, comprobar primero `wait_event`, bloqueadores y el registro de etapa; `pg_cancel_backend` es la cancelación controlada. El rollback de `012` elimina las fases ya confirmadas y sus tablas auxiliares.

Para los borradores de asiento, la cuenta se resuelve por `codigo_cuenta` sin imponer el nodo de creación: las 10.798 líneas tienen una cuenta ERP única por código, tras resolver cuatro líneas sin código desde su contrapartida gemela del mismo borrador (mismo nombre, importes invertidos y único código no vacío). El preflight bloquea cualquier otra línea que no resulte unívoca.

Los IDs de emisor de los 13 mensajes legacy corresponden a `public.login_users.idu`, no a `global_prod.rrhh_login.id`. Se verificaron por alias `1003`/`NCAROL` → login `34` y `1007`/`CMARCHANT` → login `307`; ambos se guardan en un mapa auditable. El emisor `1001` fue eliminado y se migra como evento de sistema, sin atribuirlo a una persona: `emisor_hub_id` admite `NULL` para este caso y el mensaje queda con tipo `SISTEMA`.

La cuenta legacy `1911680119736` se resuelve manualmente a `103500000000002952` (BCO CREDICOOP - GLOBAL, pesos). La normalización la hacía ambigua con la cuenta dólar `0191-168-011973/6`, pero el código en pesos coincide literalmente con el legado. Las reservas legacy con `id_asiento = 0` se omiten como centinela técnico y no como asiento ERP. Una exclusión de asiento no compartido se conserva activa sólo cuando es la única exclusión de ese asiento y su importe coincide con las líneas ERP; las demás se preservan como reservas históricas inactivas, sin imponer una exclusividad que el ERP no respalda.

Las líneas legacy de borrador con `debe = 0` y `haber = 0` se omiten: no forman parte de un asiento contable válido en el modelo destino.
