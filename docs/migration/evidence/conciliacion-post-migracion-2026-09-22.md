# Conciliación post-migración - 2026-09-22

## Dictamen

Estado: `APROBADO`.

La medición final se ejecutó a las 12:42 (America/Buenos_Aires) contra la conexión de túnel configurada. Los 16 controles de paridad del snapshot, los controles de deriva y los pendientes de corte coinciden. No se observó delta incremental al finalizar.

## Resultado verificado

| Dimensión | Resultado |
|---|---:|
| Cuentas legacy trazadas | 92 / 92 |
| Cuentas originales resueltas | 89 / 89 |
| Configuraciones originales mapeadas | 1.703 / 1.703 |
| Períodos trazados | 2.403 / 2.403 |
| Períodos originales importados | 2.357 / 2.357 |
| Períodos incrementales resueltos | 45 / 45 |
| Movimientos trazados | 997.377 / 997.377 |
| Movimientos originales canónicos | 977.208 / 977.208 |
| Movimientos incrementales resueltos | 18.069 / 18.069 |
| Crédito normalizado original | 350.270.366.127,55000 |
| Débito normalizado original | 346.905.701.358,73000 |
| Asociaciones a asiento vigentes | 932.272 / 932.272 |
| Reservas de asiento trazadas | 956.118 / 956.118 |
| Borradores / líneas | 5.197 / 10.857 |
| Fases `012` terminadas | 8 / 8 |
| Excepciones con motivo | 7.945 / 7.945 |

## Tratamientos especiales de cuenta

La migración `045_bancos_resolver_cuentas_legacy.sql` registra de forma explícita los casos que no deben evaluarse como un mapeo automático 1:1:

- `GENERAL`: `ALCANCE_GLOBAL`; sus configuraciones canónicas tienen alcance global.
- `MERCADO PAGO`: `MULTICUENTA`; cada configuración conserva los dos vínculos ERP creados por `010`.
- `191168194623`: `PERIODOS_OMITIDOS`; su único período inválido conserva motivo y no genera importación.
- `0191-168-011973/6`: `CUENTA_UNICA`; coincidencia literal con `BCO CREDICOOP DOLAR`, ID `103500000000524090`, aplicada a sus 20 configuraciones.

El rollback de `045` elimina sólo los vínculos creados por esa migración y devuelve estos cuatro registros a su estado anterior.

## Catch-up

El primer lote, `CATCHUP-370ADAA9731E3DA812764A85`, incorporó cuentas, configuraciones, períodos, movimientos, asociaciones, reservas y borradores acumulados. El segundo lote, `CATCHUP-4C98F6200AA65FCBF8464A78`, incorporó otras 21 asociaciones/reservas. Durante la regresión sandbox legacy volvió a cambiar: `CATCHUP-9D459142E6C2376A8C5743CC`, clave `catchup-2026-09-22-03`, agregó 50 asociaciones, 50 reservas y 2 borradores. Después de los tres lotes todas las métricas incrementales quedaron en cero.

## Excepciones documentadas

| Tipo | Cantidad | Tratamiento |
|---|---:|---|
| Filas duplicadas no canónicas | 1.352 | Se conserva una fila canónica y el resto permanece en la traza. |
| Períodos omitidos | 1 | Sin importación destino y con motivo persistido. |
| Recursos asiento omitidos | 7.944 | Regla y motivo persistidos. |
| Recursos valor omitidos | 1 | Regla y motivo persistido. |

El resultado `APROBADO` certifica la conciliación de datos observada; no sustituye la verificación de un respaldo restaurable ni la definición de la ventana de corte y del único escritor.
