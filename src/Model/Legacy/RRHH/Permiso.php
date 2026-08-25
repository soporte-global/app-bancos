<?php
namespace HClasses\RRHH\Permisos;

use Exception;
use PDO;

abstract class PermisoFactory
{
    public $id_permiso;
    public $id_aplicacion;
    public $tipo_permiso;
    public $nivel;
    public $descripcion;
    public $nombre_interno;
    public $icon;
    public $error;

    public function desdeId($id_permiso, $con)
    {
        try {
            $this->validarConexion($con);
            $this->id_permiso = $id_permiso;

            $prepare = $con->pdo->prepare(
                file_get_contents(RUTA . '/src/Model/Legacy/RRHH/Permiso-permisoDesdeID.sql')
            );
            $prepare->execute([':permiso' => $id_permiso]);
            $filas = $prepare->fetchAll(PDO::FETCH_ASSOC);

            if (count($filas) === 0) {
                throw new Exception('No se ha encontrado permiso con el ID especificado.');
            }
            if (count($filas) > 1) {
                throw new Exception('Se encontraron múltiples permisos con el mismo ID.');
            }

            return $this->completarDatos($filas[0], $con);
        } catch (Exception $error) {
            $this->id_permiso = $id_permiso;
            $this->error = $error;
            return $this;
        }
    }

    public function completarDatos($datos, $con)
    {
        try {
            $this->validarConexion($con);
            $datos = $this->normalizarDatos($datos);
            $requeridos = [
                'id_permiso',
                'id_aplicacion',
                'tipo_permiso',
                'nivel',
                'descripcion',
                'nombre_interno',
                'icon',
            ];
            foreach ($requeridos as $campo) {
                if (!array_key_exists($campo, $datos)) {
                    throw new Exception('Falta el campo requerido: ' . $campo . '.');
                }
            }

            $this->id_permiso = (int) $datos['id_permiso'];
            $this->id_aplicacion = (int) $datos['id_aplicacion'];
            $this->tipo_permiso = (int) $datos['tipo_permiso'];
            $this->nivel = (int) $datos['nivel'];
            $this->descripcion = $datos['descripcion'];
            $this->nombre_interno = $datos['nombre_interno'];
            $this->icon = $datos['icon'];
            $this->error = false;
        } catch (Exception $error) {
            $this->error = $error;
        }
        return $this;
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
 * permiso atómico del hub, ya sea cargado por id o desde una fila conocida.
 */
class Permiso extends PermisoFactory
{
    public function __construct($dato_permiso, $con)
    {
        try {
            $this->validarConexion($con);
            if (is_array($dato_permiso) || is_object($dato_permiso)) {
                $this->completarDatos($dato_permiso, $con);
                return;
            }
            $this->desdeId($dato_permiso, $con);
        } catch (Exception $error) {
            $this->error = $error;
        }
    }
}
