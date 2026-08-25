# Estructura ERP involucrada en procesos bancarios

Fecha: 2026-08-25. Fuente: catálogo de PostgreSQL `ftweb`, esquema `public`. Método: lectura de `information_schema` y `pg_catalog`; no se leyeron filas de negocio ni se ejecutó DDL o DML.

## Alcance

Este documento releva las entidades ERP usadas por BANCOS y BANCOS_MENSUAL para buscar, asociar, crear o modificar valores, operaciones, asientos y movimientos. Define los tipos que deben usar las futuras tablas `global_prod.bancos_*`; no convierte las tablas ERP en parte del nuevo dominio.

## Mapa relacional relevante

```text
public.entidad (id bigint)
  -> public.cuenta_bancaria (id bigint, banco, moneda)
  -> public.chequera (id bigint, cuenta_bancaria)

public.valor (id bigint, entidad, tipo_valor, subtipo_valor, estado, moneda)
  <- public.operacion_valor (valor bigint, operacion bigint)
       -> public.operacion (id bigint, asiento bigint)

public.asiento (id bigint, periodo, nodo_creacion)
  <- public.movimiento (asiento bigint, cuenta bigint, monto numeric(20,5))
       -> public.cuenta (id bigint, nodo integer)

public.nodo (id integer)
```

`operacion_valor` es la relación ERP explícita entre `operacion` y `valor`. No se debe intentar reemplazarla con una columna inexistente en `valor` ni asumir que `operacion.asiento` sea siempre una FK validada: la restricción existente está marcada `NOT VALID`.

## Tablas y claves verificadas

| Tabla ERP | PK y campos relevantes | Relaciones y restricciones relevantes |
| --- | --- | --- |
| `public.valor` | `id bigint`; `tipo_valor smallint`; `subtipo_valor smallint`; `entidad bigint`; `estado smallint`; `moneda smallint`; `monto_principal numeric(20,5)`; fechas de emisión, vencimiento, creación y modificación | FK a entidad, tipo/subtipo de valor, estado, moneda, período y nodos; PK en `id` |
| `public.operacion` | `id bigint`; `tipo_operacion smallint`; `estado_operacion smallint`; `fecha`; `asiento bigint`; `nodo_creacion integer`; auditoría | FK a tipo/estado de operación, nodo y usuarios; FK a asiento `NOT VALID`; PK en `id` |
| `public.operacion_valor` | `id bigint`; `valor bigint`; `operacion bigint`; `estado smallint`; `comprobante boolean` | FKs a valor, operación y estado de valor; `UNIQUE(valor, operacion)` y unicidad parcial de comprobante por par |
| `public.asiento` | `id bigint`; `periodo integer`; `fecha`; `numero bigint`; `nombre`; `nodo_creacion integer`; `revierte bigint`; `rev boolean` | FKs a período, nodos, usuarios, operación de desagrupación y asiento revertido; PK en `id` |
| `public.movimiento` | `id bigint`; `asiento bigint`; `cuenta bigint`; `debita boolean`; `monto numeric(20,5)` | FKs a asiento y cuenta; PK en `id`; índices por asiento y cuenta |
| `public.cuenta` | `id bigint`; `codigo`; `nombre`; `nodo integer`; `imputable boolean`; `tipo` | FK a nodo; PK en `id`; `UNIQUE(codigo, nodo)` |
| `public.entidad` | `id bigint`; `nombre`; `codigo`; `tipo_entidad smallint`; `estado smallint`; `nodo_creacion integer` | entidad base para especializaciones; PK en `id`; unicidad por código, tipo y nodo |
| `public.cuenta_bancaria` | `id bigint`; `banco bigint`; `tipo_cuenta_bancaria smallint`; `moneda smallint`; límites e `cbu` | PK y FK de `id` a `entidad(id)`; FKs a banco, moneda y tipo de cuenta bancaria |
| `public.chequera` | `id bigint`; `cuenta_bancaria bigint`; rango y próximo número | PK y FK de `id` a `entidad(id)`; FK a cuenta bancaria |
| `public.nodo` | `id integer`; `codigo_jerarquico`; `nombre`; `tipo_nodo smallint` | PK en `id`; unicidad de código jerárquico, nombre e identificador interno |
| catálogos | `tipo_valor`, `subtipo_valor`, `estado_valor`, `tipo_operacion`, `estado_operacion`, `moneda`, `periodo` | IDs `smallint` excepto `periodo.id integer`; son referencias, no textos libres |
| `public.valor_concepto` | `id bigint`; `valor bigint`; `concepto smallint`; `monto numeric(20,5)` | FK a valor y concepto; `UNIQUE(valor, concepto)`. Se usa al crear el valor resultado de la conciliación heredada |

## Implicaciones para `global_prod.bancos_*`

1. Las referencias nuevas a valor, operación, asiento, cuenta, entidad y cuenta bancaria deben ser `bigint`.
2. Las referencias a nodo deben ser `integer`; las de tipo, subtipo, estado y moneda deben ser `smallint`.
3. Todo importe que se asocie o copie hacia ERP debe conservar `numeric(20,5)`. El formato de dos decimales del extracto es una propiedad de entrada, no de la contabilidad ERP.
4. El efecto de conciliación debe registrar los cuatro IDs ERP resultantes cuando existan: valor origen, operación, valor generado y asiento generado. La relación operación-valor se aplica mediante `public.operacion_valor`.
5. Un borrador de asiento debe producir líneas compatibles con `public.movimiento`: cuenta ERP, sentido booleano y monto. No debe intentar escribir un importe separado de debe/haber sin convertirlo a la forma ERP.
6. Las consultas de candidatos deben usar las claves reales e índices existentes: valor por entidad, estado, tipo y fechas; asiento por fecha/nodo; movimiento por asiento/cuenta. Los índices nuevos de `global_prod` no sustituyen los del ERP.

## Riesgos de integración observados

- La FK `public.operacion.asiento` existe pero no está validada; el nuevo proceso no debe asumir integridad histórica perfecta.
- Los timestamps ERP son `timestamp without time zone`. El módulo nuevo puede usar `timestamptz` para sus eventos, pero debe definir una conversión explícita al interactuar con ERP.
- El legado usa IDs y códigos de tipo/estado fijos al crear conciliaciones. El catálogo demuestra que existen las tablas de referencia, pero no valida que esos códigos sigan siendo la regla canónica; deben reemplazarse por configuración o reglas acordadas.
- Una FK desde `global_prod` a `public` refuerza integridad, pero también acopla el módulo a la disponibilidad del ERP. Debe decidirse por referencia con FK o por validación transaccional según la estrategia de despliegue.
