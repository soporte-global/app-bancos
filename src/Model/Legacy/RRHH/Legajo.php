<?php
namespace HClasses\RRHH\Legajos;

use Exception;
use PDO;

class Legajo extends LegajoFactory
{
    public function __construct($dato_usuario, $conRRHH, $conZweb = false)
    {
        try {
            // si todavía llega el par rrhh/zweb, se prefiere zweb para leer el modelo nuevo
            $this->seleccionarConexion($conRRHH, $conZweb);
            if (is_array($dato_usuario) || is_object($dato_usuario)) {
                $this->completarDatos($dato_usuario, $conRRHH, $conZweb);
                return;
            }
            if (is_string($dato_usuario) || is_numeric($dato_usuario)) {
                $this->infoLegajo($dato_usuario, $conRRHH, $conZweb);
                return;
            }
            throw new Exception('No se puede crear el legajo con la información proporcionada.');
        } catch (Exception $error) {
            $this->error = $error;
        }
    }
}

abstract class LegajoFactory
{
    // estas propiedades son el contrato histórico y no se retiran
    public $id;
    public $legajo;
    public $nombre;
    public $apellido;
    public $mail;
    public $direccion;
    public $cuil;
    public $dni;
    public $empresa;
    public $empresa_ultima_liquidacion;
    public $alias;
    public $estado_zweb;
    public $cliente_zweb;
    public $ultimo_saldo;
    public $fecha_ultimo_saldo;
    public $error;

    // estos campos exponen sin pérdida la información del modelo canónico
    public $empleado_farmacia;
    public $usuario_zweb;
    public $usuario;
    public $estado;
    public $criterio_cliente;
    public $empresas = [];

    public function infoLegajo($legajo, $conRRHH, $conZweb)
    {
        try {
            $conexion = $this->seleccionarConexion($conRRHH, $conZweb);
            $driver = $conexion->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            $archivo = $driver === 'pgsql'
                ? '/src/Model/Legacy/RRHH/Legajo-infoLegajo-global.sql'
                : '/src/Model/Legacy/RRHH/Legajo-infoLegajo.sql';

            $prepare = $conexion->pdo->prepare(file_get_contents(RUTA . $archivo));
            $prepare->execute([':numLegajo' => (string) $legajo]);
            $filas = $prepare->fetchAll(PDO::FETCH_ASSOC);

            if (count($filas) === 0) {
                throw new Exception('El legajo buscado no existe');
            }
            if (count($filas) > 1) {
                throw new Exception('Se encontraron múltiples resultados para el legajo indicado.');
            }

            return $this->completarDatos($filas[0], $conRRHH, $conZweb);
        } catch (Exception $error) {
            $this->legajo = (string) $legajo;
            $this->error = $error;
            return $this;
        }
    }

    public function completarDatos($datos, $conRRHH, $conZweb)
    {
        try {
            $this->seleccionarConexion($conRRHH, $conZweb);
            $datos = $this->normalizarDatos($datos);
            foreach (['id_legajo', 'legajo', 'nombre', 'apellido', 'cuil', 'dni'] as $campo) {
                if (!array_key_exists($campo, $datos)) {
                    throw new Exception('Falta el campo requerido: ' . $campo . '.');
                }
            }

            $this->id = (int) $datos['id_legajo'];
            $this->legajo = (string) $datos['legajo'];
            $this->nombre = $datos['nombre'];
            $this->apellido = $datos['apellido'];
            $this->mail = $datos['mail'] ?? null;
            $this->direccion = $datos['direccion'] ?? null;
            $this->cuil = $datos['cuil'];
            $this->dni = (string) $datos['dni'];
            $this->alias = $datos['alias'] ?? null;
            $this->estado_zweb = $datos['estado_zweb'] ?? null;
            $this->cliente_zweb = $datos['cliente_zweb'] ?? ($datos['cliente'] ?? null);
            $this->ultimo_saldo = $datos['ultimo_saldo'] ?? null;
            $this->fecha_ultimo_saldo = $datos['fecha_ultimo_saldo'] ?? null;

            $this->empleado_farmacia = $this->valorBooleano($datos['empleado_farmacia'] ?? false);
            $this->usuario_zweb = isset($datos['usuario_zweb']) ? (int) $datos['usuario_zweb'] : null;
            $this->usuario = isset($datos['usuario']) ? (int) $datos['usuario'] : null;
            $this->estado = isset($datos['estado']) ? (int) $datos['estado'] : null;
            $this->criterio_cliente = $datos['criterio_cliente'] ?? null;

            $this->empresas = $this->decodificarEmpresas($datos['empresas'] ?? []);
            $this->empresa = $this->empresaPrincipal($datos);
            $this->empresa_ultima_liquidacion = $this->empresaUltimaLiquidacion($datos);
            $this->error = false;
        } catch (Exception $error) {
            $this->error = $error;
        }
        return $this;
    }

    protected function pass_check($password, $hash)
    {
        return password_verify($password, $hash);
    }

    protected function traer_listado_legajos($alias)
    {
        return [];
    }

    protected function seleccionarConexion($conRRHH, $conZweb = false)
    {
        $candidatas = [$conZweb, $conRRHH];
        $mysql = null;

        foreach ($candidatas as $candidata) {
            $conexion = $this->normalizarConexion($candidata);
            if (!$conexion) {
                continue;
            }
            $driver = $conexion->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            if ($driver === 'pgsql') {
                return $conexion;
            }
            if ($driver === 'mysql' && $mysql === null) {
                $mysql = $conexion;
            }
        }

        if ($mysql !== null) {
            return $mysql;
        }
        throw new Exception('No se ha pasado una conexión válida a RRHH o PostgreSQL.');
    }

    private function normalizarConexion($conexion)
    {
        if (!is_object($conexion)) {
            return null;
        }
        if (property_exists($conexion, 'pdo') && $conexion->pdo instanceof PDO) {
            return $conexion;
        }
        if (property_exists($conexion, 'dbase')
            && is_object($conexion->dbase)
            && property_exists($conexion->dbase, 'pdo')
            && $conexion->dbase->pdo instanceof PDO
        ) {
            return $conexion->dbase;
        }
        return null;
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

    private function decodificarEmpresas($empresas)
    {
        if (is_string($empresas)) {
            $empresas = json_decode($empresas, true);
        }
        if ($empresas === null) {
            return [];
        }
        if (!is_array($empresas)) {
            throw new Exception('La relación de empresas del legajo no es válida.');
        }
        return array_map(function ($empresa) {
            return is_object($empresa) ? $empresa : (object) $empresa;
        }, $empresas);
    }

    private function empresaPrincipal($datos)
    {
        foreach ($this->empresas as $empresa) {
            if (!empty($empresa->liquida)) {
                return $empresa;
            }
        }
        if (!empty($this->empresas)) {
            return $this->empresas[0];
        }
        if (isset($datos['id_empresa'])) {
            return (object) [
                'id' => (int) $datos['id_empresa'],
                'nombre' => $datos['nombre_empresa'] ?? null,
            ];
        }
        return null;
    }

    private function empresaUltimaLiquidacion($datos)
    {
        if (isset($datos['id_empresa_ultima_liquidacion'])) {
            $id = (int) $datos['id_empresa_ultima_liquidacion'];
            foreach ($this->empresas as $empresa) {
                if ((int) $empresa->id === $id) {
                    return $empresa;
                }
            }
            return (object) ['id' => $id, 'nombre' => null];
        }

        // global_prod aún no guarda la última liquidación en el maestro
        return $this->empresa;
    }

    private function valorBooleano($valor)
    {
        return $valor === true || $valor === 1 || $valor === '1' || $valor === 't';
    }
}
