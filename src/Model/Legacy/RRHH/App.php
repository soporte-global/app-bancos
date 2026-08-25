<?php
namespace HClasses\RRHH\Aplicaciones;

use Exception;
use HClasses\RRHH\Permisos\Permiso;
use PDO;

abstract class AppFactory
{
    public $id;
    public $nombre;
    public $url;
    public $imagen;
    public $nivel;
    public $nombre_nivel;
    public $permisos_internos = [];
    public $error;

    public function infoApp($id_app, $id_user, $forzar_permisos, $con)
    {
        try {
            $this->validarConexion($con);
            $this->id = (int) $id_app;

            $prepare = $con->pdo->prepare(
                file_get_contents(RUTA . '/src/Model/Legacy/RRHH/App-appDesdeId.sql')
            );
            $prepare->execute([
                ':app' => $id_app,
                ':user' => $id_user,
                ':forzar_acceso' => $forzar_permisos ? 1 : 0,
            ]);
            $filas = $prepare->fetchAll(PDO::FETCH_ASSOC);

            if (count($filas) === 0) {
                throw new Exception('No se ha encontrado permiso de acceso a la aplicación.');
            }
            if (count($filas) > 1) {
                throw new Exception('La aplicación devolvió más de un nivel de acceso efectivo.');
            }

            return $this->completarDatos($filas[0], $id_user, $forzar_permisos, $con);
        } catch (Exception $error) {
            $this->id = (int) $id_app;
            $this->error = $error;
            return $this;
        }
    }

    public function completarDatos($datos, $id_user, $forzar_permisos, $con)
    {
        try {
            $this->validarConexion($con);
            $datos = $this->normalizarDatos($datos);
            foreach (['id', 'nombre', 'url', 'imagen', 'nivel', 'nombre_nivel'] as $campo) {
                if (!array_key_exists($campo, $datos)) {
                    throw new Exception('Falta el campo requerido: ' . $campo . '.');
                }
            }

            $this->id = (int) $datos['id'];
            $this->nombre = $datos['nombre'];
            $this->url = $datos['url'];
            $this->imagen = $datos['imagen'];
            $this->nivel = (int) $datos['nivel'];
            $this->nombre_nivel = $datos['nombre_nivel'];
            // si el repositorio ya trajo los permisos, se hidratan sin otra consulta
            if (array_key_exists('permisos_internos', $datos)) {
                $this->permisos_internos = [];
                foreach ($datos['permisos_internos'] as $permiso) {
                    $this->permisos_internos[] = new Permiso($permiso, $con);
                }
            } else {
                $this->permisos_internos = $this->permisos_internos(
                    $this->id,
                    $id_user,
                    $forzar_permisos,
                    $con
                );
            }
            $this->error = false;
        } catch (Exception $error) {
            $this->error = $error;
        }
        return $this;
    }

    protected function permisos_internos($id_app, $id_user, $forzar_permisos, $con)
    {
        $prepare = $con->pdo->prepare(
            file_get_contents(RUTA . '/src/Model/Legacy/RRHH/App-listaPermisosUserApp.sql')
        );
        $prepare->execute([
            ':app' => $id_app,
            ':user' => $id_user,
            ':forzar_permisos' => $forzar_permisos ? 1 : 0,
        ]);

        $permisos = [];
        while ($fila = $prepare->fetch(PDO::FETCH_ASSOC)) {
            $permisos[] = new Permiso($fila, $con);
        }
        return $permisos;
    }

    private function normalizarDatos($datos)
    {
        if (is_object($datos)) {
            return get_object_vars($datos);
        }
        if (is_array($datos)) {
            return $datos;
        }
        throw new Exception('Los datos proporcionados no son válidos.');
    }

    protected function validarConexion($con)
    {
        if (!is_object($con) || !property_exists($con, 'pdo') || !($con->pdo instanceof PDO)) {
            throw new Exception('No se ha pasado una conexión válida a PostgreSQL.');
        }
    }
}

/**
 * aplicación visible para un usuario, junto con su nivel y permisos internos.
 */
class App extends AppFactory
{
    public function __construct($dato_app, $id_user, $forzar_permisos, $con)
    {
        try {
            $this->validarConexion($con);
            if (is_array($dato_app) || is_object($dato_app)) {
                $this->completarDatos($dato_app, $id_user, $forzar_permisos, $con);
                return;
            }
            $this->infoApp($dato_app, $id_user, $forzar_permisos, $con);
        } catch (Exception $error) {
            $this->error = $error;
        }
    }
}
