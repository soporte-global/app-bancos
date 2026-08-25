<?php
namespace GlobalApps\Legacy\Hub;

use PDO;

/**
 * conserva el acceso forzado mientras las aplicaciones antiguas migran sus reglas al hub.
 */
final class AccesoRepository
{
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function listarPorUsuario($usuarioId, $forzarAplicacion = null)
    {
        $prepare = $this->pdo->prepare(
            file_get_contents(__DIR__ . '/sql/accesos-usuario.sql')
        );
        $prepare->execute([
            ':usuario' => (int) $usuarioId,
            ':forzar_habilitado' => $forzarAplicacion === null ? 0 : 1,
            ':forzar_aplicacion' => $forzarAplicacion === null ? -1 : (int) $forzarAplicacion,
        ]);

        $aplicaciones = [];
        while ($fila = $prepare->fetch(PDO::FETCH_ASSOC)) {
            $id = (int) $fila['id'];
            if (!isset($aplicaciones[$id])) {
                $aplicaciones[$id] = [
                    'id' => $id,
                    'nombre' => $fila['nombre'],
                    'url' => $fila['url'],
                    'imagen' => $fila['imagen'],
                    'nivel' => (int) $fila['nivel'],
                    'nombre_nivel' => $fila['nombre_nivel'],
                    'permisos_internos' => [],
                ];
            }
            if ($fila['id_permiso'] !== null) {
                $aplicaciones[$id]['permisos_internos'][] = [
                    'id_permiso' => (int) $fila['id_permiso'],
                    'id_aplicacion' => $id,
                    'tipo_permiso' => (int) $fila['tipo_permiso'],
                    'nivel' => (int) $fila['nivel_permiso'],
                    'descripcion' => $fila['descripcion_permiso'],
                    'nombre_interno' => $fila['nombre_interno'],
                    'icon' => $fila['icon'],
                ];
            }
        }
        return array_values($aplicaciones);
    }
}
