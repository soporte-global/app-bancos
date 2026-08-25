# Referencias adoptadas: `nueva_app` y `hQuery`

Fecha: 2026-08-20.

## nueva_app: login y permisos

`nueva_app` es la plantilla y fuente canónica para la futura estructura de la aplicación. Se adopta como referencia para identidad, sesión, permisos, router y organización de código; SGUA queda descartada para ese rol.

Su contrato separa `_shared` (recursos reutilizables), `app` (código específico y reemplazable), `src/Model/Core` (Identidad, Acceso, Infraestructura y Diagnóstico) y `src/Model/Legacy` (adaptadores históricos). Una aplicación nueva debe definir su `id_aplicacion` en el Hub, reutilizar la cookie de sesión PHP `GLOBAL_APPS_AUTH` y aplicar `PoliticaAcceso` antes de cargar cualquier destino o bootstrap de negocio.

El Core crea una `Sesion` desde una `Cuenta` autenticada, empleado/cliente/Zweb opcionales y `PermisoAcceso` efectivos. Los permisos proceden de `ftweb.global_prod.hub_permisos_efectivos_usuario`; la autorización se evalúa tanto por aplicación como por permiso interno/destino. El navegador recibe sólo el contexto seguro `sesion`, `app` y `data`, sin hashes, contraseñas ni tokens.

Configuración relevante para la futura app: `id_aplicacion`, requisitos de empleado/cliente/Zweb, `free_for_all`, duración absoluta de login de 12 horas, cache de identidad/permisos de 5 minutos y configuración local no versionada para secretos. La plantilla publica una superficie web restringida y tiene pruebas de sesión y de superficie HTTP.

## hQuery: funciones alojadas

`hQuery` es una librería JavaScript común de utilidades UI. Se usará desde una versión publicada, no desde `hquery_actual.js`: ese archivo es de trabajo y advierte explícitamente que no debe usarse en producción. Al 2026-08-20 existe una versión fechada `hquery_v2026-08-19.js`; la versión a incorporar deberá elegirse, fijarse y probarse al iniciar la implementación.

Funciones/capacidades identificadas: validación de formularios, selector de nodo, overlays y modales, solicitudes AJAX controladas (`consultar_bbdd`/promesas), manejo de carga/error, tablas/búsqueda/autocomplete, carga de fragmentos y utilidades de formato/objetos. Requiere jQuery y, según la función utilizada, componentes como SweetAlert/jQuery UI.

El contrato actual de la rama de trabajo encapsula el estado técnico en `vars.hquery` (metadatos, configuración, diagnóstico y estados de solicitudes/emergentes/animación) y preserva los datos de aplicación ya presentes en `vars`. Las funciones de negocio no deben alojarse en hQuery ni mutar su estado técnico. Incluye una prueba Node de este contrato (`tests/hquery-vars.test.js`).

## Límites de adopción

- Reutilizar la estructura y Core de `nueva_app`, no copiar SGUA.
- Consumir hQuery como dependencia fija; no acoplar reglas bancarias a la librería ni editar su copia de trabajo desde esta aplicación.
- Adaptar, cuando haga falta, el contexto canónico de `nueva_app` al contrato `vars` de hQuery mediante `app/compatibilidad/contexto.js`, manteniendo `window.contextoApp` como fuente del código compartido.
