<?php
namespace GlobalApps\Core\Acceso;

use PDO;

final class PermisoAccesoRepository
{
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function listarPorCuenta($cuentaId)
    {
        $prepare = $this->pdo->prepare(
            file_get_contents(__DIR__ . '/sql/permisos-acceso-cuenta.sql')
        );
        $prepare->execute([':cuenta' => (int) $cuentaId]);

        $permisosAcceso = [];
        while ($fila = $prepare->fetch(PDO::FETCH_ASSOC)) {
            $aplicacionId = (int) $fila['aplicacion_id'];
            if (!isset($permisosAcceso[$aplicacionId])) {
                $permisosAcceso[$aplicacionId] = [
                    'aplicacion_id' => $aplicacionId,
                    'nombre' => $fila['nombre'],
                    'url' => $fila['url'],
                    'imagen' => $fila['imagen'],
                    'nivel' => (int) $fila['nivel'],
                    'nombre_nivel' => $fila['nombre_nivel'],
                    'permisos_app' => [],
                ];
            }
            if ($fila['permiso_id'] !== null) {
                $permisosAcceso[$aplicacionId]['permisos_app'][] = new PermisoApp(
                    $fila['permiso_id'],
                    $aplicacionId,
                    $fila['permiso_tipo'],
                    $fila['permiso_nivel'],
                    $fila['permiso_descripcion'],
                    $fila['permiso_nombre_interno'],
                    $fila['permiso_icono']
                );
            }
        }

        return array_map(function (array $datos) {
            return new PermisoAcceso(
                $datos['aplicacion_id'],
                $datos['nombre'],
                $datos['url'],
                $datos['imagen'],
                $datos['nivel'],
                $datos['nombre_nivel'],
                $datos['permisos_app']
            );
        }, array_values($permisosAcceso));
    }
}
