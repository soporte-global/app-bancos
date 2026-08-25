<?php

use GlobalApps\Core\Acceso\AccesoDenegadoException;
use GlobalApps\Core\Acceso\PermisoAcceso;
use GlobalApps\Core\Acceso\PermisoApp;
use GlobalApps\Core\Acceso\PoliticaAcceso;
use GlobalApps\Core\Identidad\Cliente;
use GlobalApps\Core\Identidad\Cuenta;
use GlobalApps\Core\Identidad\Empleado;
use GlobalApps\Core\Identidad\RelacionLaboral;
use GlobalApps\Core\Identidad\Sesion;
use GlobalApps\Core\Identidad\SesionCache;
use GlobalApps\Core\Identidad\SesionWeb;
use GlobalApps\Core\Identidad\UsuarioZweb;

if (!defined('RUTA')) {
    define('RUTA', dirname(__DIR__, 2));
}
require_once RUTA . '/src/autoload.php';

function comprobar($condicion, $mensaje)
{
    if (!$condicion) {
        throw new RuntimeException($mensaje);
    }
}

function comprobarExcepcion(callable $accion, $mensaje)
{
    try {
        $accion();
    } catch (Throwable $error) {
        return;
    }
    throw new RuntimeException($mensaje);
}

$cuenta = new Cuenta(10, 'hvega', 'hernan');
$relacion = new RelacionLaboral(2, 'Global Business', '30123456789', 14, true, '88', '2020-01-02');
$empleado = new Empleado(
    20,
    '88',
    'Hernan',
    'Vega',
    '20123456789',
    '12345678',
    true,
    30,
    40,
    [$relacion]
);
$zweb = new UsuarioZweb(30, 'hvega', 'Hernan Vega', null, ['14' => 'Casa Central'], []);
$cliente = new Cliente(
    40,
    '12345678',
    'C-88',
    'Hernan',
    'Vega',
    60,
    'Personal Global',
    2,
    [14 => 0]
);
$permisoApp = new PermisoApp(50, 11, 2, 1, 'Diagnostico', 'diagnostico', 'stethoscope');
$permisoAcceso = new PermisoAcceso(11, 'Nueva app', '/nueva-app', null, 1, 'Administrador', [$permisoApp]);
$otroPermisoApp = new PermisoApp(51, 12, 2, 2, 'Consulta', 'consulta', 'search');
$otroPermisoAcceso = new PermisoAcceso(12, 'Otra app', '/otra-app', null, 2, 'General', [$otroPermisoApp]);
$sesion = new Sesion($cuenta, $empleado, $zweb, $cliente, [$permisoAcceso, $otroPermisoAcceso]);

comprobar($sesion->cuenta() === $cuenta, 'La sesion no conserva la cuenta.');
comprobar($cuenta->username() === 'hvega', 'La cuenta no conserva el username.');
comprobar($cuenta->alias() === 'hernan', 'La cuenta no conserva el alias.');
comprobar($sesion->empleado() === $empleado, 'La sesion no conserva el empleado.');
comprobar($sesion->usuarioZweb() === $zweb, 'La sesion no conserva el usuario Zweb.');
comprobar($sesion->cliente() === $cliente, 'La sesion no conserva el cliente.');
comprobar($sesion->permisoAccesoDe(11) === $permisoAcceso, 'No se encontro el permiso de acceso esperado.');
comprobar($sesion->permisoAccesoDe(12) === $otroPermisoAcceso, 'No se encontro el segundo permiso de acceso.');
comprobar($sesion->permisoAccesoDe(13) === null, 'Se encontro un permiso de acceso inexistente.');
comprobar($sesion->permisosAppDe(11) === [$permisoApp], 'Los permisos de la app no coinciden.');
comprobar($sesion->permisosAppDe(12) === [$otroPermisoApp], 'Los permisos de la segunda app no coinciden.');
comprobar($sesion->permisosAppDe(13) === [], 'Una app sin acceso devolvio permisos.');
comprobar($sesion->nombreMostrado() === 'Hernan Vega', 'El nombre mostrado no prioriza al empleado.');
comprobar(count($empleado->relacionesLaborales()) === 1, 'Se perdio la relacion laboral.');
comprobar($cliente->cuentaCorriente(14) === 0, 'No se aplico la excepcion de cuenta corriente.');
comprobar($cliente->cuentaCorriente(15) === 2, 'No se aplico la regla general de cuenta corriente.');
comprobar($cliente->modoCuentaCorriente(14) === 'nunca', 'No se interpreto la excepcion de cuenta corriente.');
comprobar($cliente->modoCuentaCorriente(15) === 'preguntar', 'No se interpreto la regla general de cuenta corriente.');

$sesionWeb = SesionWeb::crear($sesion, 11);
comprobar(array_keys($sesionWeb) === ['user', 'empleado', 'nivel_acceso'], 'La raiz de la sesion web no respeta el contrato.');
comprobar($sesionWeb['user']['username'] === 'hvega', 'La sesion web no contiene el username.');
comprobar(count($sesionWeb['user']['permisos']) === 1, 'La sesion web publico permisos de otras aplicaciones.');
comprobar($sesionWeb['user']['permisos'][0]['aplicacion_id'] === 11, 'La sesion web no priorizo la aplicacion actual.');
comprobar($sesionWeb['empleado']['dni'] === '12345678', 'La sesion web no contiene el empleado completo.');
comprobar(!array_key_exists('cliente_id', $sesionWeb['empleado']), 'La sesion web duplico el id del cliente.');
comprobar(!array_key_exists('usuario_zweb_id', $sesionWeb['empleado']), 'La sesion web duplico el id del usuario Zweb.');
comprobar($sesionWeb['empleado']['relaciones_laborales'][0]['empresa_id'] === 2, 'La sesion web perdio la empresa.');
comprobar($sesionWeb['empleado']['cliente']['codigo_entidad'] === 'C-88', 'La sesion web no contiene el cliente.');
comprobar($sesionWeb['empleado']['cliente']['cuenta_corriente']['general'] === 2, 'La sesion web perdio la regla general.');
comprobar($sesionWeb['empleado']['cliente']['cuenta_corriente']['modos'][1] === 'siempre', 'La sesion web perdio los modos de cuenta corriente.');
comprobar($sesionWeb['empleado']['cliente']['cuenta_corriente']['excepciones']->{'14'} === 0, 'La sesion web perdio las excepciones por nodo.');
comprobar($sesionWeb['empleado']['usuario_zweb']['nodos']->{'14'} === 'Casa Central', 'La sesion web no contiene los nodos Zweb.');
comprobar($sesionWeb['user']['permisos'][0]['permisos_internos'][0]['nombre_interno'] === 'diagnostico', 'La sesion web perdio los permisos internos.');
comprobar($sesionWeb['nivel_acceso'] === 1, 'La sesion web perdio el nivel de acceso actual.');

$sesionWebCompleta = SesionWeb::crear($sesion, 11, true);
comprobar(count($sesionWebCompleta['user']['permisos']) === 2, 'La sesion web no publico todas las aplicaciones solicitadas.');

$politica = new PoliticaAcceso(11, [
    'libre' => false,
    'requiere_empleado' => true,
    'requiere_zweb' => true,
    'requiere_cliente' => true,
]);
$politica->validar($sesion);
comprobar($politica->permisoAcceso($sesion) === $permisoAcceso, 'La politica no encontro el permiso de acceso.');
comprobar($politica->permisosApp($sesion) === [$permisoApp], 'La politica no encontro los permisos de la app.');

$empleadoSinZweb = new Empleado(
    21,
    '89',
    'Ana',
    'Perez',
    null,
    null,
    false,
    null,
    null,
    []
);
$sesionSinZweb = new Sesion($cuenta, $empleadoSinZweb, null, null, [$permisoAcceso]);
$politicaSinZweb = new PoliticaAcceso(11, [
    'libre' => false,
    'requiere_empleado' => true,
]);
$politicaSinZweb->validar($sesionSinZweb);
comprobar($sesionSinZweb->usuarioZweb() === null, 'La ausencia de Zweb no se conserva.');

comprobarExcepcion(function () use ($sesionSinZweb) {
    (new PoliticaAcceso(11, [
        'requiere_empleado' => true,
        'requiere_zweb' => true,
    ]))->validar($sesionSinZweb);
}, 'La politica permitio una sesion sin el usuario Zweb requerido.');

comprobarExcepcion(function () use ($sesionSinZweb) {
    (new PoliticaAcceso(11, [
        'requiere_empleado' => true,
        'requiere_cliente' => true,
    ]))->validar($sesionSinZweb);
}, 'La politica permitio una sesion sin el cliente requerido.');

$sesionOperativa = new Sesion($cuenta);
comprobar($sesionOperativa->nombreMostrado() === 'hernan', 'La cuenta sin empleado no usa su alias.');

$politicaLibre = new PoliticaAcceso(12, ['libre' => true]);
$politicaLibre->validar($sesionOperativa);

$accesoDenegado = false;
try {
    (new PoliticaAcceso(99, []))->validar($sesionOperativa);
} catch (AccesoDenegadoException $error) {
    $accesoDenegado = true;
}
comprobar($accesoDenegado, 'La politica no diferencia una autorizacion denegada.');

comprobarExcepcion(function () use ($sesionOperativa) {
    (new PoliticaAcceso(12, [
        'libre' => true,
        'requiere_empleado' => true,
    ]))->validar($sesionOperativa);
}, 'La politica permitio una cuenta sin el empleado requerido.');

$_SESSION = [];
$cache = new SesionCache('prueba', 30);
$cache->guardar($sesion);
comprobar($cache->obtener() === $sesion, 'El cache no devolvio la sesion guardada.');
$cacheGuardada = $_SESSION['prueba']['sesion_cache'];
$cacheGuardada['version'] = 2;
$_SESSION['prueba']['sesion_cache'] = $cacheGuardada;
comprobar($cache->obtener() === null, 'El cache con un modelo anterior no fue descartado.');
$cache->guardar($sesion);
$cache->limpiar();
comprobar($cache->obtener() === null, 'El cache no elimino la sesion.');

echo "Entidades Core OK\n";
