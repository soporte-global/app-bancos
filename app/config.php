<?php
return [
    'nombre' => 'NUEVA APP',
    'carpeta' => 'nueva_app', // debe coincidir con el directorio publicado, no con el nombre del repositorio
    'entorno' => 'prod', // tunel | testing | prod
    'id_aplicacion' => 0, // sin aplicación asignada: la plantilla abre sin login hasta definir un id
    'permisos_todas_las_apps' => false, // true publica el alcance completo, como necesitaría un hub

    'requiere_empleado' => true, // false permite cuentas operativas sin identidad laboral
    'requiere_zweb_user' => false,
    'requiere_cliente' => false,
    'grupos_con_acceso' => [],
    'free_for_all' => false,
    'usa_cookies' => true, // mantiene la sesion; nunca guarda usuario ni contrasena en una cookie propia
    'mostrar_home_automatico' => true, // false deja home exclusivamente en manos de los permisos
    'diagnostico_habilitado' => false, // false oculta y bloquea la pagina compartida
    'login_max_age' => 12 * 60 * 60, // exige un nuevo login despues de doce horas
    'auth_cache_ttl' => 300, // relee identidad y permisos cada cinco minutos
    'protocolo' => 'http',
    'session_name' => 'GLOBAL_APPS_AUTH', // debe coincidir entre las apps que comparten autenticacion
    'session_save_path' => '', // vacio usa el almacenamiento comun del nuevo Core

    'ftweb_database' => 'ftweb',
    'ftweb_user' => 'postgres',
    'ftweb_password' => '',
    'ftweb_persistent' => true,
    // En true, BANCOS escribe en global_temp; las consultas ERP siguen en public.
    // Nunca habilitarlo en producción.
    'bancos_debug' => false,
    'rrhh_database' => 'rrhh_new',
    'rrhh_user' => 'root',
    'rrhh_password' => '',

    // credenciales OAuth de las API Zetti; completar en la configuración local
    'zetti_token_url' => '',
    'zetti_token_user' => '',
    'zetti_token_password' => '',
    'zetti_token_secret' => '',

    // las credenciales de apis-global v2 se completan en la configuracion local
    'apis_v2_base_url' => 'https://apis.farmaciasglobal.com.ar:5002/',
    'apis_v2_ca_bundle' => dirname(__DIR__) . '/_shared/certs/isrg-root-x1.pem',
    'apis_v2_user' => '',
    'apis_v2_password' => '',
    'apis_v2_diagnostic_api' => 'comisiones',

    // cada autocomplete se publica con un nombre fijo y un sql dentro de app/sql
    'autocomplete_consultas' => [],
];
