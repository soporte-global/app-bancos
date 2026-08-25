# Relevamiento legado: `bancos` (conciliación de cheques)

Fecha de relevamiento: 2026-08-20. Alcance: lectura estática del repositorio legado; no se ejecutaron operaciones contra la base de datos.

## Propósito y arquitectura observada

Herramienta PHP monolítica para conciliar **cheques**. Su punto de entrada es `index.php`, que incluye un controlador procedimental y vistas PHP. El navegador mantiene el estado de importación, configuración y coincidencias; los endpoints PHP realizan lecturas y escrituras con PDO sobre PostgreSQL `ftweb`.

Capas actuales:

- presentación: `view/` y JavaScript embebido en `model/js.php`;
- coordinación y consultas: `controller/controlador_principal.php` y `model/funciones_php.php`;
- infraestructura: `model/connect.php`, `model/config.php` y SQL en `model/querys/`;
- no hay servicios de dominio, API versionada, pruebas de aplicación ni framework identificables.

## Módulos y flujo funcional

1. **Selección de cuenta**: carga las cuentas bancarias activas desde el ERP; la selección queda serializada en la cookie `cuenta`.
2. **Importación**: el usuario pega datos tabulares de Excel. El formato esperado contiene nodo, número de cuenta, fecha, tipo/subtipo, referencia, monto y observación. La importación vive sólo en memoria del navegador.
3. **Configuración de conciliación**: por cuenta se guardan configuraciones con bancos alcanzados, mapeo de subtipo de valor a código de Excel y sentido crédito/débito. La configuración `GENERAL` se crea en memoria cuando no existe una guardada.
4. **Cálculo y propuesta**: se consultan valores del ERP y se generan candidatos de conciliación contra la importación.
5. **Confirmación**: el usuario puede aceptar una propuesta o forzar una conciliación; al confirmar se crean operación, valor, asiento y dos movimientos contables, y se marca el valor origen como conciliado.
6. **Salidas**: vista de resumen, PDF y CSV para importados sin coincidencia, movimientos del sistema sin coincidencia y gastos agrupados.

## Endpoints observados

| Ruta | Método observado | Responsabilidad |
| --- | --- | --- |
| `index.php` | GET/POST | interfaz principal; seleccionar u olvidar cuenta |
| `model/calcular_movimientos_sistema.php` | POST/AJAX | cuenta movimientos del ERP por período/cuenta |
| `model/calcular_movimientos_sistema2.php` | POST/AJAX | obtiene movimientos del ERP por período/cuenta |
| `model/limpiar_configs.php` | POST/AJAX | borra configuración guardada para una cuenta |
| `model/guardar_configs.php` | POST/AJAX | persiste configuraciones y cuentas contables asociadas |
| `model/guardar_conciliaciones.php` | POST/AJAX | ejecuta la conciliación definitiva |
| `view/paso1_importar.php` y `view/resumen_en_pantalla.php` | POST | fragmentos HTML abiertos en ventana emergente |

## Datos e integraciones

Lee el modelo ERP existente, principalmente `entidad`, `cuenta_bancaria`, `nodo`, `valor`, `subtipo_valor`, `cuenta`, `operacion`, `asiento` y `movimiento`. Excluye el banco sin definir de id `1080731`, sólo muestra cuentas con código no vacío y excluye valores en estados `20` y `36`.

Crea tablas auxiliares sin claves, restricciones ni migraciones versionadas: `bancos_guardado_datos`, `bancos_guardado_listabancos`, `bancos_guardado_listareglas`, `bancos_guardado_cuentasbanco` y `bancos_guardado_cuentasvalores`.

La única integración HTTP identificada es `http://200.59.239.233:5001/checkHubOrigin.php`, incluida al inicio. Su contrato no forma parte del repositorio, por lo que el mecanismo de autorización resultante es **no verificable** desde este relevamiento.

No hay procesos batch, colas ni scheduler identificables. La conciliación se dispara exclusivamente desde la interfaz.

## Reglas de negocio inferidas

- Una regla `GENERAL` aplica a todos los bancos; una configuración específica para un banco prevalece sobre la general.
- Cada subtipo de valor se mapea a un texto del extracto y a un sentido `CRÉDITO`/`DÉBITO`; en ausencia de configuración se inicializa como crédito y con una abreviatura del tipo/subtipo.
- El dominio funcional es la conciliación de cheques. Los tipos 4 y 5 (cheques propios y de terceros) se tratan explícitamente; cualquier otro subtipo mostrado funciona como soporte de configuración, no como evidencia de conciliación bancaria general.
- Sólo se propone conciliación para valores no conciliados (estado distinto de `7`), de tipos admitidos para entidades de cuenta bancaria, más los tipos 4 y 5.
- Para ser candidato, un importado debe corresponder a la misma cuenta bancaria, su tipo debe contener el código configurado y debe coincidir en monto dentro de ±1% **o** la referencia del valor debe contener la referencia importada.
- La propuesta se puntúa: monto dentro de tolerancia (+1), monto exacto (+1), referencia contenida (+2), referencia exacta (+2) y fecha exacta (+2). Sólo se autoselecciona un candidato con al menos 4 puntos; siempre se ofrece la alternativa de forzar la conciliación.
- Al confirmar, el valor origen pasa al estado `7`; se crea una operación tipo/estado/discriminador `101/1/101`, un valor bancario tipo `124`, subtipo `79`, estado `4`, un asiento y movimientos de igual monto con débito en la cuenta contable de valor y crédito en la cuenta contable bancaria.

## Riesgos y ambigüedades relevantes

- La escritura final de varias entidades no está encapsulada en una transacción: una falla intermedia puede dejar una conciliación parcial.
- La identidad del usuario es fija (`16529`) en las escrituras; no hay trazabilidad del operador real.
- `guardar_conciliaciones.php`, `limpiar_configs.php` y partes de la persistencia de configuraciones interpolan datos en SQL; tampoco hay protección CSRF ni autorización comprobable por endpoint.
- La configuración de cuentas bancarias se persiste y lee con `cuenta = 'N/A'`, de modo que parece global aunque la UI se presenta como configuración por cuenta. Esto requiere confirmación funcional.
- No hay una restricción de unicidad que impida duplicar configuraciones, reglas o relaciones. Guardar primero borra y luego reinserta, sin transacción.
- La "conciliación forzada" no expresa qué importado la respalda, pero igualmente genera los asientos a partir del valor del sistema.
- Los SQL de algunos endpoints usan `querys/...` en lugar de `model/querys/...`; el comportamiento depende del directorio de ejecución.
