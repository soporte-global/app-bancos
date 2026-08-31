<?php

// el core nuevo respeta psr-4 y se carga sólo cuando una clase lo necesita
spl_autoload_register(function ($clase) {
    $prefijo = 'GlobalApps\\Core\\';
    if (strncmp($clase, $prefijo, strlen($prefijo)) !== 0) {
        return;
    }

    $relativa = substr($clase, strlen($prefijo));
    $archivo = RUTA . '/src/Model/Core/' . str_replace('\\', '/', $relativa) . '.php';
    if (is_file($archivo)) {
        require_once $archivo;
    }
});

// El código específico de BANCOS se mantiene fuera del Core compartido.
spl_autoload_register(function ($clase) {
    $prefijo = 'AppBancos\\';
    if (strncmp($clase, $prefijo, strlen($prefijo)) !== 0) {
        return;
    }

    $relativa = substr($clase, strlen($prefijo));
    $archivo = RUTA . '/app/' . str_replace('\\', '/', $relativa) . '.php';
    if (is_file($archivo)) {
        require_once $archivo;
    }
});

// la capa de compatibilidad también se carga bajo demanda y queda fuera del core
spl_autoload_register(function ($clase) {
    $prefijo = 'GlobalApps\\Legacy\\';
    if (strncmp($clase, $prefijo, strlen($prefijo)) !== 0) {
        return;
    }

    $relativa = substr($clase, strlen($prefijo));
    $archivo = RUTA . '/src/Model/Legacy/' . str_replace('\\', '/', $relativa) . '.php';
    if (is_file($archivo)) {
        require_once $archivo;
    }
});

// mientras dure la migración, este mapa conserva los namespaces y archivos históricos
$legacy_class_map = [
    'HClasses\\Conexiones\\ConexionZetti' => '/src/Model/Legacy/Conexiones.php',
    'HClasses\\Conexiones\\DataBase' => '/src/Model/Legacy/Conexiones.php',
    'HClasses\\Conexiones\\Token' => '/src/Model/Legacy/Conexiones.php',
    'HClasses\\Conexiones\\TokenZetti' => '/src/Model/Legacy/Conexiones.php',
    'HClasses\\Hub\\Usuarios\\User' => '/src/Model/Legacy/Hub/User.php',
    'HClasses\\RRHH\\Aplicaciones\\AppFactory' => '/src/Model/Legacy/RRHH/App.php',
    'HClasses\\RRHH\\Aplicaciones\\App' => '/src/Model/Legacy/RRHH/App.php',
    'HClasses\\RRHH\\Legajos\\LegajoFactory' => '/src/Model/Legacy/RRHH/Legajo.php',
    'HClasses\\RRHH\\Legajos\\Legajo' => '/src/Model/Legacy/RRHH/Legajo.php',
    'HClasses\\RRHH\\Permisos\\PermisoFactory' => '/src/Model/Legacy/RRHH/Permiso.php',
    'HClasses\\RRHH\\Permisos\\Permiso' => '/src/Model/Legacy/RRHH/Permiso.php',
    'HClasses\\RRHH\\Usuarios\\UserFactory' => '/src/Model/Legacy/RRHH/User.php',
    'HClasses\\RRHH\\Usuarios\\User' => '/src/Model/Legacy/RRHH/User.php',
    'HClasses\\Zweb\\Nodos\\Nodo' => '/src/Model/Legacy/Zweb/Nodos.php',
    'HClasses\\Zweb\\Procesos\\ProcesoFactory' => '/src/Model/Legacy/Zweb/Procesos.php',
    'HClasses\\Zweb\\Procesos\\Proceso' => '/src/Model/Legacy/Zweb/Procesos.php',
    'HClasses\\Zweb\\Procesos\\ListadoProcesos' => '/src/Model/Legacy/Zweb/Procesos.php',
    'HClasses\\Zweb\\Productos\\ProductoFactory' => '/src/Model/Legacy/Zweb/Productos.php',
    'HClasses\\Zweb\\Productos\\Producto' => '/src/Model/Legacy/Zweb/Productos.php',
    'HClasses\\Zweb\\Productos\\CCGinfoProdFactory' => '/src/Model/Legacy/Zweb/Productos.php',
    'HClasses\\Zweb\\Productos\\CCGinfoProd' => '/src/Model/Legacy/Zweb/Productos.php',
    'HClasses\\Zweb\\Usuarios\\UserInfoFactory' => '/src/Model/Legacy/Zweb/UserInfo.php',
    'HClasses\\Zweb\\Usuarios\\UserInfo' => '/src/Model/Legacy/Zweb/UserInfo.php',
    'HClasses\\Zweb\\Usuarios\\Comisiones' => '/src/Model/Legacy/Zweb/Usuarios.php',
    'HClasses\\Zweb\\Usuarios\\CuentaCorriente' => '/src/Model/Legacy/Zweb/Usuarios.php',
];

spl_autoload_register(function ($clase) use ($legacy_class_map) {
    if (isset($legacy_class_map[$clase])) {
        require_once RUTA . $legacy_class_map[$clase];
    }
});
