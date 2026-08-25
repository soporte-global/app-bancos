<?php

use HClasses\RRHH\Aplicaciones\App;
use HClasses\RRHH\Legajos\Legajo;
use HClasses\RRHH\Permisos\Permiso;

define('RUTA', dirname(__DIR__));
require_once RUTA . '/src/autoload.php';

final class ConexionCaracterizacion
{
    public $pdo;

    public function __construct()
    {
        $this->pdo = new PdoCaracterizacion();
    }
}

final class PdoCaracterizacion extends PDO
{
    public function __construct()
    {
    }

    public function getAttribute($atributo)
    {
        return $atributo === PDO::ATTR_DRIVER_NAME ? 'pgsql' : null;
    }
}

function comprobar($condicion, $mensaje)
{
    if (!$condicion) {
        throw new RuntimeException($mensaje);
    }
}

$conexion = new ConexionCaracterizacion();

$hubUser = new ReflectionClass(HClasses\Hub\Usuarios\User::class);
$propiedadesHub = array_map(function (ReflectionProperty $propiedad) {
    return $propiedad->getName();
}, $hubUser->getProperties(ReflectionProperty::IS_PUBLIC));
foreach (['id', 'alias', 'pass_verified', 'legajos', 'info_zweb', 'permisos', 'error'] as $propiedad) {
    comprobar(in_array($propiedad, $propiedadesHub, true), 'Hub User perdio la propiedad ' . $propiedad . '.');
}
comprobar(
    $hubUser->getConstructor()->getNumberOfParameters() === 3,
    'Hub User debe conservar su constructor de tres parametros.'
);

$rrhhUser = new ReflectionClass(HClasses\RRHH\Usuarios\User::class);
comprobar(
    $rrhhUser->getConstructor()->getNumberOfParameters() === 4,
    'RRHH User debe conservar su constructor de cuatro parametros.'
);

$permiso = new Permiso([
    'id_permiso' => 20,
    'id_aplicacion' => 10,
    'tipo_permiso' => 2,
    'nivel' => 1,
    'descripcion' => 'Home',
    'nombre_interno' => 'home',
    'icon' => 'fas fa-home',
], $conexion);

comprobar($permiso->id_permiso === 20, 'Permiso debe conservar id_permiso.');
comprobar($permiso->nombre_interno === 'home', 'Permiso debe conservar nombre_interno.');
comprobar($permiso->error === false, 'Permiso valido no debe informar error.');

$app = new App([
    'id' => 10,
    'nombre' => 'Nueva app',
    'url' => '/nueva_app',
    'imagen' => null,
    'nivel' => 1,
    'nombre_nivel' => 'Administrador',
    'permisos_internos' => [[
        'id_permiso' => 20,
        'id_aplicacion' => 10,
        'tipo_permiso' => 2,
        'nivel' => 1,
        'descripcion' => 'Home',
        'nombre_interno' => 'home',
        'icon' => 'fas fa-home',
    ]],
], 1, false, $conexion);

comprobar($app->id === 10, 'App debe conservar id.');
comprobar(count($app->permisos_internos) === 1, 'App debe hidratar permisos internos.');
comprobar($app->permisos_internos[0] instanceof Permiso, 'App debe exponer objetos Permiso.');

$legajo = new Legajo([
    'id_legajo' => 42,
    'legajo' => '0042',
    'nombre' => 'Ada',
    'apellido' => 'Lovelace',
    'mail' => 'ada@example.test',
    'direccion' => null,
    'cuil' => '27-00000000-0',
    'dni' => '00000000',
    'alias' => 'adal',
    'estado_zweb' => 'Activo',
    'cliente_zweb' => 123,
    'ultimo_saldo' => null,
    'fecha_ultimo_saldo' => null,
    'empleado_farmacia' => true,
    'usuario_zweb' => 99,
    'usuario' => 1,
    'estado' => 1,
    'criterio_cliente' => 'manual',
    'empresas' => json_encode([[
        'id' => 7,
        'nombre' => 'Empresa',
        'cuit' => '30-00000000-0',
        'nodo' => 8,
        'liquida' => true,
        'numero_legajo_origen' => '0042',
        'fecha_ingreso' => '2020-01-01',
    ]]),
], $conexion);

comprobar($legajo->id === 42, 'Legajo debe conservar id.');
comprobar(count($legajo->empresas) === 1, 'Legajo debe hidratar empresas.');
comprobar($legajo->empresa->id === 7, 'Legajo debe seleccionar la empresa que liquida.');

echo "OK: contratos legacy caracterizados.\n";
