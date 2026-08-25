<?php
namespace HClasses\Hub\Usuarios;

use Exception;
use GlobalApps\Legacy\Hub\AccesoRepository;
use HClasses\RRHH\Aplicaciones\App;
use HClasses\RRHH\Legajos\Legajo;
use HClasses\Zweb\Usuarios\UserInfo;
use PDO;

/**
 * representa al usuario autenticado del hub y conserva el contrato del login.
 */
class User
{
    public $id;
    public $alias;
    public $pass_verified = false;
    public $admin_rrhh = false;
    public $id_grupo_usuario;
    public $grupo_usuario;
    public $zweb_predeterminado;
    public $legajos = [];
    public $nombre;
    public $apellido;
    public $mail;
    public $info_zweb;
    public $permisos = [];
    public $error;

    public function __construct($datoUsuario, $conexion, $verPermisos = false)
    {
        try {
            $this->validarConexion($conexion);
            list($alias, $password) = $this->separarCredenciales($datoUsuario);
            $this->autenticar($alias, $password, $conexion, (bool) $verPermisos);
        } catch (Exception $error) {
            // el login histórico informa el problema en la propiedad error
            $this->pass_verified = false;
            $this->error = $error;
        }
    }

    private function autenticar($alias, $password, $conexion, $verPermisos)
    {
        $fila = $this->buscarLogin($alias, $conexion);
        $this->pass_verified = password_verify($password, $fila['hash']);

        if (!$this->pass_verified) {
            throw new Exception('El password ingresado no coincide');
        }

        // recién después de validar la contraseña exponemos los datos personales
        $this->id = (int) $fila['id_user'];
        $this->alias = $fila['alias'];
        $this->admin_rrhh = $this->valorBooleano($fila['admin_rrhh']);
        $this->id_grupo_usuario = (int) $fila['id_grupo_usuario'];
        $this->grupo_usuario = $fila['grupo_usuario'];
        $this->zweb_predeterminado = $fila['zweb_predeterminado'];
        $this->nombre = $fila['nombre'];
        $this->apellido = $fila['apellido'];
        $this->mail = $fila['mail'];
        $this->legajos = $this->traerLegajos($conexion);
        $this->info_zweb = new UserInfo(
            $this->alias,
            $conexion,
            $this->zweb_predeterminado
        );

        if ($this->info_zweb->error instanceof Exception) {
            throw $this->info_zweb->error;
        }
        if (REQUIERE_ZWEB_USER && !$this->info_zweb->encontrado()) {
            throw new Exception('El acceso a esta aplicación requiere un usuario de Zweb');
        }

        $this->permisos = $verPermisos ? $this->permisosAcceso($conexion) : [];
        $this->error = false;
        return $this;
    }

    private function buscarLogin($alias, $conexion)
    {
        $prepare = $conexion->pdo->prepare(
            file_get_contents(RUTA . '/src/Model/Legacy/Hub/User-login.sql')
        );
        $prepare->execute([':alias' => $alias]);
        $filas = $prepare->fetchAll(PDO::FETCH_ASSOC);

        if (count($filas) === 0) {
            throw new Exception('El alias ingresado no existe');
        }
        if (count($filas) > 1) {
            throw new Exception('Se encontraron múltiples resultados para el alias ingresado.');
        }
        return $filas[0];
    }

    private function traerLegajos($conexion)
    {
        $prepare = $conexion->pdo->prepare(
            file_get_contents(RUTA . '/src/Model/Legacy/Hub/User-legajos.sql')
        );
        $prepare->execute([':usuario' => $this->id]);

        $legajos = [];
        while ($fila = $prepare->fetch(PDO::FETCH_ASSOC)) {
            $legajo = new Legajo($fila, $conexion);
            if ($legajo->error instanceof Exception) {
                throw $legajo->error;
            }
            $legajos[] = $legajo;
        }
        return $legajos;
    }

    private function permisosAcceso($conexion)
    {
        // un grupo autorizado puede forzar sólo la app actual, no todo el hub
        $forzarAcceso = $this->tieneGrupoConAcceso() ? ID_APLICACION : -1;
        $repositorio = new AccesoRepository($conexion->pdo);
        $aplicaciones = $repositorio->listarPorUsuario(
            $this->id,
            $forzarAcceso > 0 ? $forzarAcceso : null
        );

        $permisos = [];
        $forzarPermisos = $forzarAcceso > 0;
        foreach ($aplicaciones as $fila) {
            $app = new App($fila, $this->id, $forzarPermisos, $conexion);
            if ($app->error instanceof Exception) {
                throw $app->error;
            }
            $permisos[] = $app;
        }
        return $permisos;
    }

    private function tieneGrupoConAcceso()
    {
        if (!$this->info_zweb || !is_array($this->info_zweb->grupos_usuario)) {
            return false;
        }
        $gruposUsuario = array_column($this->info_zweb->grupos_usuario, 'id');
        return !empty(array_intersect($gruposUsuario, GRUPOS_CON_ACCESO));
    }

    private function separarCredenciales($datoUsuario)
    {
        if (!is_string($datoUsuario)) {
            throw new Exception('No se puede crear el usuario con la información proporcionada.');
        }
        $partes = explode(':', $datoUsuario, 2);
        if (count($partes) !== 2 || trim($partes[0]) === '') {
            throw new Exception('El formato de login no es válido.');
        }
        return [trim($partes[0]), $partes[1]];
    }

    private function validarConexion($conexion)
    {
        if (!is_object($conexion)
            || !property_exists($conexion, 'pdo')
            || !($conexion->pdo instanceof PDO)
        ) {
            throw new Exception('No se ha pasado una conexión válida a PostgreSQL.');
        }
    }

    private function valorBooleano($valor)
    {
        return $valor === true || $valor === 1 || $valor === '1' || $valor === 't';
    }
}
