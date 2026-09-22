# Despliegue sandbox y sincronización

APP BANCOS separa el entorno de conexión del modo operativo. Una instalación publicada puede usar `entorno = prod` y `bancos_modo_operativo = sandbox`: autenticación, permisos y lecturas ERP son reales, mientras las tablas BANCOS y toda mutación ERP se resuelven en `global_temp`.

La publicación sandbox exige además:

```php
'bancos_modo_operativo' => 'sandbox',
'bancos_sandbox_permitir_en_prod' => true,
'ftweb_user' => 'app_bancos_debug_login',
```

El bootstrap rechaza `postgres`/`root`. Cada conexión web verifica que el usuario sea miembro de `app_bancos_debug_runtime`, pueda insertar en `global_temp` y no pueda insertar en `global_prod.bancos_movimiento_extracto` ni actualizar `public.valor`.

## Sincronización

El comando predeterminado sólo compara las 21 tablas y referencias necesarias:

```powershell
php -d extension=pdo_pgsql app\cli\sincronizar_sandbox.php
```

Informa filas esperadas, presentes, faltantes y conflictos por clave/contenido. Una sincronización incremental requiere una clave idempotente:

```powershell
php -d extension=pdo_pgsql app\cli\sincronizar_sandbox.php --confirmar --clave=sandbox-sync-AAAA-MM-DD-NN
```

Si el sandbox contiene un baseline incompatible, el reinicio inicial debe ejecutarse con una cuenta de migración que posea `TRUNCATE` y `CREATE SCHEMA`; el login de la aplicación no posee esas capacidades:

```powershell
php -d extension=pdo_pgsql app\cli\sincronizar_sandbox.php --confirmar --reiniciar --clave=sandbox-baseline-AAAA-MM-DD-NN
```

El reinicio crea primero un esquema de respaldo fechado, trunca únicamente los destinos sincronizados dentro de la misma transacción, copia el baseline y reserva el rango `8000000000000000000+` para IDs creados en pruebas. Si cualquier paso falla, PostgreSQL revierte respaldo, truncado y carga.

La sincronización nunca copia datos de `global_temp` hacia `global_prod` o `public`. En el corte definitivo se ejecuta el catch-up desde legacy a `global_prod`, se repite la conciliación y se cambia explícitamente el modo operativo.

Si la sincronización devuelve `REQUIERE_DECISION`, primero deben inspeccionarse las filas conflictivas. Sólo cuando se confirmó que la versión sandbox no proviene de una prueba que deba conservarse puede aceptarse explícitamente el origen:

```powershell
php -d extension=pdo_pgsql app\cli\sincronizar_sandbox.php --confirmar --aceptar-origen-conflictos --clave=sandbox-resolucion-AAAA-MM-DD-NN
```

Sin esa opción, el comando copia lo no conflictivo y mantiene intactas las divergencias.

## Baseline confirmado

El 2026-09-22 se confirmó `sandbox-baseline-2026-09-22-01`:

- 3.634.359 filas copiadas;
- 995.277 movimientos;
- 950.242 asociaciones;
- 974.085 reservas;
- 486.527 cabeceras de asiento ERP necesarias como referencia;
- cero faltantes y cero conflictos en la previsualización posterior;
- respaldo previo: `bancos_sandbox_backup_20260922_152347_c3d686c0`.

El fixture mínimo `015` se volvió a aplicar en el rango reservado para conservar las pruebas destructivas aisladas. Las comparaciones `016` y `017` verifican ahora directamente los IDs canónicos del baseline.

Durante la regresión, legacy agregó 50 asociaciones/reservas y 2 borradores. `catchup-2026-09-22-03` los incorporó a `global_prod`. La sincronización `sandbox-sync-2026-09-22-02` copió 147 filas no conflictivas y conservó cuatro divergencias: dos asociaciones y dos reservas que habían sido reemplazadas en origen. Tras verificar que no provenían de pruebas, `sandbox-resolucion-2026-09-22-03` aceptó explícitamente el origen, actualizó cuatro filas y agregó los cuatro reemplazos. El resultado final volvió a cero faltantes/conflictos.
