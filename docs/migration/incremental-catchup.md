# Catch-up incremental desde legacy

## Objetivo

`app/cli/migrar_legacy_incremental.php` concentra en una sola operación el traslado de datos creados en BANCOS_MENSUAL después del snapshot original. El comando no modifica el legado y sólo escribe en `global_prod` cuando recibe confirmación explícita.

La ejecución predeterminada es de sólo lectura:

```powershell
php -d extension=pdo_pgsql app\cli\migrar_legacy_incremental.php
```

Cuando el resultado sea `LISTO`, se confirma con una clave idempotente única:

```powershell
php -d extension=pdo_pgsql app\cli\migrar_legacy_incremental.php --confirmar --clave=catchup-AAAA-MM-DD-01
```

La misma clave devuelve el resultado anterior y no repite escrituras. Una ejecución concurrente se rechaza mediante un advisory lock.

## Fases

1. Inserta cuentas nuevas y sólo acepta equivalencias ERP deterministas.
2. Agrega configuraciones, reglas y mapeos que no existan; un mapeo activo contradictorio bloquea el proceso.
3. Traza e importa períodos y movimientos nuevos usando sus claves legacy.
4. Actualiza asociaciones, reservas, borradores, líneas y mensajes sobre movimientos canónicos.
5. Registra el lote y vuelve a ejecutar el preflight para comprobar que el delta quedó agotado.

Cada fase confirma por separado en una ejecución real. Si una fase falla, las anteriores permanecen idempotentes y la misma clave puede reanudarse luego de corregir la causa.

## Correcciones del legacy

Las asociaciones y reservas creadas por migraciones anteriores son administradas por el catch-up. Si el legacy reemplazó un asiento, el vínculo migrado anterior se conserva inactivo y se crea el vigente. Nunca se reemplazan automáticamente asociaciones manuales o creadas por la aplicación; esos casos producen `REQUIERE_DECISION`.

Los cuatro casos históricos especiales quedaron resueltos por `045`: alcance global, multicuenta, períodos omitidos y Credicoop dólar. Una cuenta nueva sin equivalencia única o un período sin configuración unívoca continúan bloqueando toda escritura.

## Trazabilidad y verificación

La migración `044_bancos_trazabilidad_incremental.sql` agrega `lote_migracion` a las trazas y registra cada intento en `bancos_migracion_incremental_ejecucion`. El lote original queda marcado como `ORIGINAL`, por lo que su evidencia no cambia cuando se ejecutan incrementos.

Después de confirmar se debe ejecutar:

```powershell
php -d extension=pdo_pgsql app\cli\conciliacion_post_migracion.php
```

El catch-up no reemplaza el congelamiento ni la verificación de un respaldo restaurable. Para el corte definitivo se ejecuta dentro de la ventana de un único escritor y se exige una conciliación aprobada.

La prueba integrada ejecuta las tres fases contra los datos reales dentro de una transacción externa, verifica el reintento y revierte todo al finalizar. Luego se confirmó el lote `CATCHUP-370ADAA9731E3DA812764A85` con la clave `catchup-2026-09-22-01`: procesó 3 cuentas, 60 configuraciones, 45 períodos y 18.069 filas de movimiento. La evidencia está en [catchup-2026-09-22-01.md](evidence/catchup-2026-09-22-01.md).
