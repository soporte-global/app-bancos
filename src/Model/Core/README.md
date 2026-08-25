# Core nuevo

Esta carpeta contiene los contratos del código nuevo. La compatibilidad con las
aplicaciones históricas vive por separado en `src/Model/Legacy`.

```text
Core/
  Identidad/       cuenta, empleado, relaciones, cliente, Zweb y sesión
  Acceso/          aplicaciones, permisos y política de acceso
  Diagnostics/     medición del request
  Infrastructure/
    ApiV2/          cliente y token JWT de APIS-global v2
    Http/           transporte HTTP compartido
    Persistence/    creación y reutilización de conexiones PDO
    Zetti/          token OAuth del servicio histórico de Zetti
```

Los repositorios reciben un `PDO`; no abren conexiones por su cuenta, no usan
variables globales y no devuelven objetos parcialmente válidos con una
propiedad `error`. Cuando no hay un registro opcional devuelven `null`. Cuando
los datos contradicen el contrato lanzan una excepción.

## Identidad y acceso

El Core se organiza por responsabilidad y no por el sistema que originó cada
dato. Hub y RRHH ya convergen en `global_prod`, por lo que cuenta, empleado,
cliente e identidad Zweb forman un único contrato de identidad. El acceso queda
separado porque expresa qué puede hacer esa identidad dentro de cada aplicación.

El modelo tiene cinco conceptos principales:

- `Cuenta` identifica la credencial habilitada del Hub y separa el `username`
  usado para autenticar del `alias` mostrado por la aplicación.
- `Empleado` representa una única identidad laboral activa por cuenta.
- `Sesion` reúne la cuenta, su empleado opcional, su identidad Zweb y cliente
  opcionales y sus permisos de acceso efectivos.
- `PermisoAcceso` representa la habilitación efectiva de una cuenta para una
  aplicación, incluido su nivel y sus permisos internos.
- `PermisoApp` representa un destino o acción disponible dentro de esa
  aplicación.

`RelacionLaboral`, `UsuarioZweb` y `Cliente` son objetos de valor pequeños que
evitan exponer arreglos con claves implícitas. No existe una clase por empresa,
nodo o grupo mientras esos datos no tengan comportamiento independiente.

`Sesion::permisosAcceso()` devuelve el conjunto efectivo;
`permisoAccesoDe($aplicacionId)` busca una aplicación y
`permisosAppDe($aplicacionId)` devuelve sus permisos internos.
`PoliticaAcceso::permisoAcceso()` y `permisosApp()` aplican la misma selección a
la aplicación configurada.

El tipo de cuenta todavía no forma parte de `Cuenta` porque `global_prod` no lo
persiste. No se infiere a partir de la ausencia de empleado: esa ausencia puede
ser una cuenta operativa legítima o una inconsistencia. Hasta incorporar la
clasificación explícita, cada aplicación expresa si exige empleado mediante su
política de acceso.

El hash de contraseña sólo se lee dentro de `Autenticador` y nunca forma parte
de `Cuenta` ni de `Sesion`:

```php
use GlobalApps\Core\Identidad\Autenticador;

$autenticador = new Autenticador($pdo);
$sesion = $autenticador->autenticar($username, $password);

$permisoAcceso = $sesion->permisoAccesoDe(ID_APLICACION);
$empleado = $sesion->empleado();
```

`Autenticador::restaurar()` reconstruye la sesión desde el id de una cuenta
todavía habilitada sin volver a recibir la contraseña. `SesionCache` permite
conservar temporalmente esa sesión. Su vencimiento es absoluto: navegar no lo
renueva, para que las bajas y los cambios de permisos vuelvan a leerse dentro
del plazo configurado.

La vigencia del login es otro plazo. `login_max_age` exige autenticarse
nuevamente a las doce horas, mientras `auth_cache_ttl` puede seguir refrescando
identidad y permisos cada cinco minutos durante esa jornada.

`PoliticaAcceso` recibe el id de aplicación y las opciones `libre`,
`requiere_empleado`, `requiere_cliente` y `requiere_zweb`. `libre` omite sólo
la exigencia de `PermisoAcceso`; los requisitos de identidad siguen vigentes.
La política expone el permiso de acceso y los permisos de app autorizados, y no
incorpora excepciones legacy. Una denegación lanza `AccesoDenegadoException`
para que la aplicación conserve la autenticación global y responda 403.

Actualmente `rrhh_login.usuario` alimenta ambos campos de `Cuenta`. El contrato
ya los mantiene separados para que una futura columna de alias cambie solamente
el repositorio y no a los consumidores.

La identidad Zweb se busca exclusivamente por `Empleado::usuarioZwebId()`.
El Core no infiere usuarios por coincidencia de alias.

La opción `requiere_zweb` de `PoliticaAcceso` no cambia la construcción de
`Sesion`. Cuando el empleado tiene una relación Zweb explícita, la identidad se
carga siempre; la opción sólo decide si una aplicación acepta que sea `null`.
La configuración pública `requiere_zweb_user` se traduce a esa opción al armar
la política.

```text
Sesion
  cuenta: Cuenta(id, username, alias)
  empleado: Empleado(
    id, legajo, nombre, apellido, cuil, dni,
    empleadoFarmacia, usuarioZwebId, clienteId,
    relacionesLaborales[]
  ) | null
  cliente: Cliente(
    id, documento, codigoEntidad, nombre, apellido, entidadAgrupadora,
    cuentaCorrienteGeneral, excepcionesCuentaCorriente[]
  ) | null
  usuarioZweb: UsuarioZweb(id, alias, nombre, mail, nodos[], grupos[]) | null
  permisosAcceso: PermisoAcceso(
    aplicacionId, nivel, permisosApp: PermisoApp[]
  )[]
```

Con `requiere_zweb = false`, una sesión con empleado y sin Zweb es válida. Con
`requiere_zweb = true`, el mismo objeto se construye pero la política de acceso
lo rechaza. `requiere_empleado` y `requiere_cliente` aplican el mismo criterio a
sus relaciones. Una sesión que sí contiene `UsuarioZweb` conserva sus nodos y
grupos en cualquier modo.

`Cliente` se resuelve desde `Empleado::clienteId()`. La regla general de cuenta
corriente es la fila de `nodo_por_entidad` correspondiente a `GRUPO GLOBAL`
(`nodo = 2006202`); una fila de otro nodo reemplaza esa regla únicamente para
ese nodo. Los códigos se conservan como enteros anulables y el Core los
interpreta como `0 = nunca`, `1 = siempre` y `2 = preguntar`.

El navegador recibe un contexto con `sesion`, `app` y `data`. La proyección de
identidad queda dentro de `sesion`:

```text
sesion
  user
    id, username, alias
    permisos[]
      aplicacion_id, nombre, url, imagen, nivel, nombre_nivel
      permisos_internos[]
  empleado | null
    datos personales y relaciones_laborales[]
    cliente | null
    usuario_zweb | null
  nivel_acceso
```

Por defecto `user.permisos` contiene sólo la aplicación actual. Una aplicación
tipo Hub puede declarar `permisos_todas_las_apps = true` para publicar todos los
permisos de acceso efectivos. En esta proyección, cada entrada de `permisos`
corresponde a un `PermisoAcceso` y cada elemento de `permisos_internos` a un
`PermisoApp`. La proyección excluye contraseñas, hashes, tokens y cualquier otra
credencial.

`app` contiene `id`, `nombre`, `pagina` y `ruta`. `data` siempre es un objeto y
se carga desde `app/php/carga_inicial.php`; las listas de negocio viven dentro
de sus propiedades, no como raíz de `data`.

El flujo web mantiene este orden:

1. Resolver la sesión vigente o autenticar las credenciales recibidas.
2. Autorizarla con `PoliticaAcceso`.
3. Resolver y autorizar el destino antes de ejecutar código de negocio.
4. Cargar el objeto de `app/php/carga_inicial.php`.
5. Armar el contexto canónico `sesion/app/data`.
6. Incluir libremente `app/compatibilidad/contexto.php` en el mismo scope.
7. Validar y publicar el contexto como JSON no ejecutable.
8. Incluir header, página y footer.

El include PHP de compatibilidad puede reconstruir `$user`, `$nivel_acceso` u
otras variables históricas para el header y la página. Debe conservar válido
`$contextoApp`. Después, `app/compatibilidad/contexto.js` recibe una copia del
contexto y decide la forma final de `vars`; por defecto lo devuelve sin cambios.
`SesionWeb` y el Core no conocen ni reconstruyen contratos frontend legacy.

## Conexiones

La aplicación puede armar una vez el proveedor y reutilizar sus conexiones:

```php
use GlobalApps\Core\Infrastructure\Persistence\PdoProvider;

$pdo = new PdoProvider([
    'ftweb' => [
        'dsn' => 'pgsql:host=' . HOST . ';port=' . PORT . ';dbname=' . DBASE,
        'user' => USER,
        'password' => PASS,
        'options' => [PDO::ATTR_PERSISTENT => FTWEB_PERSISTENT],
    ],
]);

$autenticador = new GlobalApps\Core\Identidad\Autenticador($pdo->ftweb());
$sesion = $autenticador->restaurar($cuentaId);
```

## Permisos del Hub

`PermisoAccesoRepository` consume la vista
`global_prod.hub_permisos_efectivos_usuario`. La vista combina y desduplica las
concesiones por grupo del Hub, cuenta directa y grupo de Zweb. No existe un
permiso negativo: mientras una ruta activa conceda el permiso, la cuenta lo
conserva.

La migración `003_hub_permisos_zweb_por_empleado.sql` alinea también esa ruta
con `rrhh_empleados.usuario_zweb`. Si una cuenta contradice el modelo y tiene
más de un empleado activo, esa ruta no concede permisos hasta resolver la
ambigüedad.

Al migrar otras aplicaciones, `GRUPOS_CON_ACCESO`, `forzarAplicacion` y las
excepciones hardcodeadas deben convertirse en reglas `global_prod.hub_*`. El
repositorio nuevo no implementa esos atajos. `FREE_FOR_ALL` se aplica en la
frontera de la aplicación, no fabricando permisos dentro del repositorio.

## APIS-global v2

La forma corta de crear el cliente es:

```php
use GlobalApps\Core\Infrastructure\ApiV2\ApiV2Factory;

$apis = ApiV2Factory::crearCliente($app_config);
$datos = $apis->postOrFail('comisiones', $payload);
```

El cliente consulta `?metadata=autenticacion`, obtiene y conserva el JWT sólo
en memoria cuando la API lo requiere, y envía `Authorization: Bearer`. Si el
servidor rechaza un token vencido, lo renueva y repite una sola vez.

La URL base está en `app/config.php`. El usuario y la contraseña viven
únicamente en `app/config.local.php`, que Git ignora. El OAuth histórico de
Zetti usa otro protocolo y otro proveedor: `ZettiTokenProvider`.

## Diagnóstico y rendimiento

El diagnóstico compartido prueba autoload, PostgreSQL, Hub, RRHH, cliente,
Zweb y las APIs v2 sin mostrar credenciales, hashes ni tokens.
`RequestProfiler` agrega el header `Server-Timing` cuando el diagnóstico está
habilitado.
