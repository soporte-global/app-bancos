# Arquitectura objetivo: estado de descubrimiento

El producto a consolidar tiene dos dominios: conciliación de cheques (BANCOS) y gestión mensual de extractos, asignaciones y cierre (BANCOS_MENSUAL).

`nueva_app` es la referencia adoptada para login y permisos, no SGUA. Su Core separa Identidad, Acceso e Infraestructura; autentica una vez, mantiene una sesión PHP opaca compartible entre aplicaciones y vuelve a autorizar por aplicación y destino interno mediante `PoliticaAcceso`. Los permisos efectivos se obtienen desde `ftweb.global_prod.hub_*`. Las nuevas tablas propias de la aplicación se ubicarán en `global_prod`, con prefijo `bancos_` y nombres en español.

La arquitectura nueva deberá separar autenticación/autorización de servidor, casos de uso, repositorios ERP/tablas propias, transacciones contables e interfaz HTTP. `hQuery` será la fuente de funciones JavaScript compartidas; su estado técnico debe permanecer aislado bajo `vars.hquery` y el contexto de aplicación bajo `sesion`, `app`, `data` y `estado`. No se decide todavía si los dominios serán módulos de un monolito o aplicaciones separadas: depende de confirmar límites operativos y de acceso.
