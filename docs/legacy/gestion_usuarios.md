# Relevamiento legado: `gestion_usuarios`

Fecha de relevamiento: 2026-08-20. Alcance: lectura estática, sin ejecutar operaciones contra la base de datos. Estado: referencia histórica; **no** se utilizará como integración futura de identidad ni permisos.

## Propósito y arquitectura observada

Aplicación PHP monolítica de gestión de usuarios y aplicaciones (SGUA). Implementa login local, administración ABM de usuarios y de aplicaciones, y autorización por relación usuario-aplicación. Usa PostgreSQL `ftweb` a través de PDO y navegación por formularios/redirects. El repositorio actualizado también contiene BANCOS_MENSUAL como aplicaciones registrables `bancos_mensual_admin` y `bancos_mensual_user`; dicha ubicación no convierte a SGUA en dependencia de la nueva solución.

El archivo compartido de conexión, además de construir la conexión, ejecuta limpieza de datos incompletos en cada inclusión. El inicio de sesión crea o repara el esquema y la cuenta administrativa predeterminada.

## Módulos y endpoints

| Área | Rutas principales | Función |
| --- | --- | --- |
| acceso | `index.php`, `recursos/login.php`, `redirect.php`, `matar_cookie.php` | login, creación de cookie y logout |
| inicio | `recursos/home.php`, `listar_aplicaciones.php` | muestra aplicaciones autorizadas |
| usuarios | `nuevo_usuario.php`, `crear_user.php`, `abm_users.php`, `actualizar_user.php`, `eliminar_usuario.php`, `listar_usuarios_panel.php` | ABM y asignación de permisos por aplicación |
| aplicaciones | `alta_aplicaciones.php`, `crear_aplic.php`, `abm_aplic.php`, `actualizar_aplic.php`, `eliminar_aplic.php`, `listar_aplic_panel.php` | ABM y asignación de usuarios autorizados |
| mantenimiento | `option_panel.php`, `activar_option_panel.php`, `eliminar_todo_check.php`, `borrar_todo.php` | panel y eliminación total |

## Base de datos e integración

Tablas propias:

- `login_users(IDu, nombre, alias UNIQUE, pass, admin)`;
- `aplicaciones(IDa, descripcion, ruta, tipo)`;
- `user_aplic(IDu, IDa, permiso)`;
- `tipo_aplic(numero, tipo)`, creada pero no utilizada por los flujos hallados.

No se definen claves foráneas, índices adicionales ni restricciones de unicidad para la relación usuario-aplicación. La aplicación registrada se abre mediante un POST a `URL + aplicaciones/{ruta}/index.php`, pasando sólo `idu` como campo oculto. Es el punto de integración con las herramientas registradas. BANCOS_MENSUAL consume este identificador y su variante de usuario consulta el permiso de `bancos_mensual_admin` antes de habilitar capacidades administrativas.

No hay procesos batch ni integraciones externas identificables.

## Autenticación y autorización observadas

- El login busca el alias sin distinguir mayúsculas/minúsculas y valida la contraseña con `password_verify` contra un hash bcrypt de coste 10.
- La sesión es la cookie `idu`, válida por 24 horas, sin atributos `HttpOnly`, `Secure` ni `SameSite` y sin firma/servidor de sesión.
- Cualquier ruta protege únicamente la presencia de la cookie, incluso los ABM. La UI oculta el panel al usuario no administrador, pero los endpoints no verifican el rol `admin`.
- La cuenta de sistema `IDu=13`, alias `ADMIN`, se crea/repara en cada visita al login y su contraseña inicial es `admin` si se hubiera eliminado. La interfaz la trata como administrador.
- La eliminación total, en cambio, exige `IDu=1`; contradice el flujo del panel, que se muestra para `admin` y cuya cuenta especial es `13`. El flujo parece inalcanzable en condiciones normales.

## Reglas de negocio inferidas

- El alias es único sin distinción de mayúsculas/minúsculas.
- Al crear un usuario se crea una autorización denegada para cada aplicación existente y se habilitan las seleccionadas. Al crear una aplicación se hace el proceso simétrico para los usuarios existentes.
- Al actualizar usuario o aplicación, se niegan todos los permisos de esa entidad y luego se habilitan los seleccionados.
- Un usuario normal sólo visualiza/lanza aplicaciones cuya relación `user_aplic.permiso` sea verdadera; `ADMIN` no recibe aplicaciones por defecto y usa el panel de administración.
- Los nombres, alias y contraseñas tienen validaciones HTML de longitud y caracteres, pero la validación de servidor equivalente no está implementada.

## Riesgos y ambigüedades relevantes

- Una cookie editable permite suplantar cualquier `IDu`; la autorización no se verifica en el servidor para acciones administrativas.
- Los IDs y varios campos se interpolan en SQL; la aplicación depende de validación de interfaz y no usa sentencias parametrizadas de manera consistente.
- Las altas/actualizaciones que afectan permiso usan múltiples sentencias sin transacción, por lo que pueden quedar permisos a medio actualizar.
- La ruta de una aplicación se almacena y se concatena para construir un destino; no hay una política explícita de validación de ruta ni SSO sólido: `idu` llega desde el cliente y la app destino vuelve a consultar la base.
- El archivo de conexión borra registros considerados inválidos en cada request, una mutación oculta y no auditable.
- El flujo de borrado masivo contiene una regla de identidad contradictoria (`1` versus `13`) y realiza `DROP TABLE` sin estrategia de respaldo o migración.
