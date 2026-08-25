# Contexto para retomar nueva_app

Actualizado: 2026-08-18.

Este archivo permite continuar el trabajo desde otra instalacion de Codex sin
depender del historial de esta conversacion. Antes de modificar el proyecto,
leer tambien `MIGRACION.md`, `README.md`, `src/Model/Core/README.md` y
`src/Model/Legacy/README.md`.

## Identidad del proyecto

- repositorio y plantilla: `nueva_app`;
- rama de trabajo: `Produccion`;
- carpeta fisica actual en la notebook: `Desarrollos/nueva_app`;
- `app/config.php` distingue el nombre de la app de su carpeta publicada.

Para reproducir la misma ubicacion en otra PC, usar una carpeta
`Desarrollos/nueva_app` y abrir esa carpeta como proyecto. El repositorio
remoto privado es `https://github.com/SrBestia/nueva_app`. Para clonarlo con la
misma carpeta y rama:

```text
git clone -b Produccion https://github.com/SrBestia/nueva_app.git nueva_app
```

## Objetivo actual

Construir la primera aplicacion sobre un Core compartido simple y reusable,
conservar temporalmente los contratos HClasses de las aplicaciones antiguas y
usar esta experiencia para preparar futuras migraciones.

La proxima revision funcional la hara Hernan sobre:

1. el circuito completo de login y restauracion de sesion;
2. los nombres y responsabilidades de las clases nuevas;
3. el contrato que deberan consumir las primeras pantallas de consulta RRHH.

## Modelo acordado e implementado

El dominio nuevo tiene cinco conceptos principales:

- `Cuenta`: identidad autenticable del Hub, con `username` y `alias` separados;
- `Empleado`: identidad laboral unica y sus relaciones;
- `Sesion`: cuenta, empleado, cliente y Zweb opcionales y permisos efectivos;
- `PermisoAcceso`: habilitación, nivel y permisos de una aplicación;
- `PermisoApp`: destino o acción interna de esa aplicación.

Los objetos de valor con contrato propio son `RelacionLaboral`, `UsuarioZweb` y
`Cliente`. No se crearon clases para empresa, nodo, grupo, tipo
de cuenta ni factories sin un consumidor real. `criterioCliente` era un
artefacto de migracion y no forma parte del Core nuevo.

Las clases se organizan por función, no por la base que originó cada dato:
`GlobalApps\Core\Identidad` reúne cuenta, empleado, cliente, Zweb, sesión y
autenticación; `GlobalApps\Core\Acceso` contiene `PermisoAcceso`, `PermisoApp`,
`PermisoAccesoRepository` y la política de acceso. La procedencia Hub o RRHH
queda en la persistencia, no en el contrato que consume la aplicación.

`Cliente` conserva documento, codigo de entidad, nombre, apellido y entidad
agrupadora. La cuenta corriente mantiene el codigo crudo: usa como regla
general la fila de `GRUPO GLOBAL` (`nodo = 2006202`) y permite excepciones por
nodo. Los significados vigentes son `0 = nunca`, `1 = siempre` y
`2 = preguntar`.

Hoy `rrhh_login.usuario` alimenta tanto `Cuenta::username()` como
`Cuenta::alias()`. Son conceptos separados en el contrato, aunque la
persistencia todavia no tenga una columna de alias independiente.

El tipo de cuenta (`empleado`, `operativa`, `tecnica`) sigue pendiente hasta
poder persistirlo explicitamente. Nunca debe inferirse solamente porque falte
un empleado.

## Login y sesion

- `Autenticador` es el unico lector del hash de password.
- La password se usa durante el POST inicial y no se guarda en sesion, cookies
  ni objetos del Core.
- `GLOBAL_APPS_AUTH` conserva una autenticacion comun para todas las apps del
  nuevo Core; cada una vuelve a evaluar sus propios permisos al abrirse.
- La sesion conserva el id de `Cuenta` y reconstruye el resto sin password.
- `login_max_age` vence el login de forma absoluta a las doce horas.
- `auth_cache_ttl` refresca empleado, cliente, Zweb y permisos cada cinco minutos.
- Refrescar permisos no extiende las doce horas ni obliga a volver a loguear.
- La cookie PHP puede ser persistente, pero el timestamp `vence_at` es la
  autoridad que obliga al nuevo login.
- Las apps comparten `SESSION_NAME`, `AUTH_SESSION_KEY` y `session_save_path`;
  sus caches de identidad permanecen separados por carpeta.
- Las sesiones anteriores a este modelo se invalidan deliberadamente.

`PoliticaAcceso` recibe `libre`, `requiere_empleado`, `requiere_cliente` y
`requiere_zweb`. La primera opción omite sólo el permiso de acceso; los tres
requisitos de identidad se siguen evaluando.

El contexto canónico del navegador tiene tres grupos: `sesion`, `app` y `data`.
`sesion` contiene `user`, `empleado` y `nivel_acceso`; `user.permisos` publica
por defecto solamente la app actual y `empleado` anida sus relaciones,
`cliente` y `usuario_zweb`. `app` describe la aplicación y la página actual;
`data` es siempre el objeto de datos de negocio iniciales devuelto por
`app/php/carga_inicial.php`; las listas viven dentro de sus propiedades. La
proyección excluye passwords, hashes y tokens.

El router arma esa proyección. `_shared/html/contexto-app.php` la publica como
JSON no ejecutable y `_shared/js/contexto-app.js` la carga antes del header. El
contexto canónico queda disponible en `window.contextoApp`; `vars` recibe la
salida del adaptador propio de la aplicación.

El orden de cada carga es: resolver o autenticar la sesión, autorizarla,
resolver y autorizar el destino, cargar `app/php/carga_inicial.php`, armar el
contexto, incluir la compatibilidad PHP, publicar el JSON e incluir header,
página y footer. Los destinos inválidos o restringidos no cargan datos de
negocio.

## Compatibilidad legacy

`src/Model/Legacy` conserva namespaces, constructores y propiedades publicas
historicas, y aisla las reglas que no deben contaminar el Core, incluido el
acceso forzado. Los namespaces antiguos permanecen estables aunque su ubicación
física haya cambiado.

Las aplicaciones antiguas que consumen `$user`, `vars.user`,
`GRUPOS_CON_ACCESO` o el contrato anterior de autocomplete necesitan conservar
su adaptador mientras se migra cada consumidor. Las excepciones deben terminar
traducidas a permisos `global_prod.hub_*`; `FREE_FOR_ALL` sigue siendo la unica
politica global valida.

La capa propia vive en `app/compatibilidad`. `contexto.php` se incluye libremente
en el mismo scope del header y la página, por lo que puede reconstruir `$user`,
`$nivel_acceso` u otras variables PHP históricas y ajustar `$contextoApp` sin
romper el sobre `sesion/app/data`. `contexto.js` transforma después una copia de
ese payload al `vars` exacto de la app. El archivo JavaScript de la plantilla es
una función identidad. Una aplicación puede agregar datos transitorios a su
copia de `vars.data` cuando los necesite, sin incorporarlos a
`window.contextoApp`. Así una app legacy puede adoptar el login y el Core
nuevos conservando su transformación explícita.

La plantilla no genera aliases planos: consume `vars.sesion`, `vars.app` y
`vars.data`. El estado técnico de hQuery queda aislado en `vars.hquery`.

## Otros cambios ya realizados

- header y diagnostico consumen `Sesion`;
- autocomplete requiere una sesion valida, un nombre registrado en
  `autocomplete_consultas` y ejecuta SQL dentro de una transaccion read-only;
- se retiro el experimento sin consumidores que persistia nombre, apellido y
  DNI del dispositivo durante diez anos;
- el cliente de APIs v2 obtiene el requisito de token desde metadata y ya no
  permite forzarlo manualmente;
- el User-Agent HTTP ahora identifica al Core compartido;
- el almacenamiento PHP de autenticacion es comun y los caches quedan aislados por app;
- se corrigieron errores mecanicos de HTML, JavaScript y CSS;
- la prueba de superficie confirma que configuraciones, SQL, `src`, `vendor`,
  `.git` y archivos internos responden 403.

## SQL preparado pero no desplegado

`_shared/sql/migraciones/003_hub_permisos_zweb_por_empleado.sql` reemplaza la
coincidencia de alias por la relacion explicita
`rrhh_empleados.usuario_zweb`. El preflight actual mostro cero rutas por grupo
Zweb tanto antes como despues, por lo que el delta vigente es cero.

No desplegar esa migracion sin repetir primero el preflight sobre la base viva.

## Verificaciones del corte

- PHP 7.4.30: lint correcto en todos los PHP modificados;
- prueba de entidades Core: correcta;
- caracterizacion de contratos legacy: correcta;
- JavaScript: sintaxis correcta en los nueve archivos compartidos;
- PostgreSQL 9.6: consultas nuevas validadas en modo read-only;
- superficie HTTP: 30 casos correctos;
- login invalido, escape de markup, colores y endpoints anonimos: respuestas
  controladas, sin notices ni fatals visibles;
- `git diff --check`: correcto.

El login con una cuenta valida y la carga de home fueron confirmados
manualmente sobre la nueva `Sesion`.

## Pendientes cercanos

1. Observar logout y vencimiento durante una jornada real.
2. Persistir el tipo de cuenta de forma explicita.
3. Resolver el warning conocido de `hquery_actual.js` antes de produccion.
4. Construir la primera pantalla de consulta RRHH con las APIs de
   liquidaciones.
5. Replicar este Core sobre aplicaciones antiguas solo despues de terminar la
   primera aplicacion y validar sus contratos reales.

## Continuidad de repositorios

El repositorio `nueva_app` queda como plantilla. La aplicacion de consulta RRHH
continua en el repositorio independiente `rrhh_consulta`, creado desde esta
base. Los cambios
reutilizables descubiertos durante su desarrollo podran volver a `nueva_app`
mediante commits deliberados; no se compartiran ambas evoluciones como ramas de
un mismo producto.

La carpeta local `skills/` esta ignorada y no forma parte del proyecto. Las
skills portables se publican por separado en `soporte-global/codex-context`.
