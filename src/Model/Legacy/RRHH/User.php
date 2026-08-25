<?php
namespace HClasses\RRHH\Usuarios;
use HClasses\RRHH\Legajos\Legajo;
use HClasses\RRHH\Aplicaciones\App;
use HClasses\Zweb\Usuarios\UserInfo;
use Exception;
use PDO;

abstract class UserFactory
{
    public $id;
    public $alias;
    public $pass_verified;
    public $admin_rrhh;
    public $id_grupo_usuario;
    public $grupo_usuario;
    public $zweb_predeterminado;
    public $legajos;
    public $nombre;
    public $apellido;
    public $mail;
    public $info_zweb;
    public $permisos;
    public $error;
    // Función para obtener los datos del usuario desde el alias y contraseña
    public function login($alias, $password, $con, $con_zweb, $ver_permisos)
    {
        try {
            $prepare = $con->pdo->prepare(file_get_contents(RUTA . '/src/Model/Legacy/RRHH/User-userDesdeAlias.sql'));
            $prepare->execute(array(
                ':alias' => $alias
            ));
            if ($prepare->rowCount() === 1) {
                $row = $prepare->fetch(PDO::FETCH_ASSOC);
                $this->pass_verified = $this->pass_check($password, $row['hash']);
                if (!$this->pass_verified) {
                    throw new Exception('El password ingresado no coincide');
                }
                $this->error = false;
                $datos_completos = $this->completarDatos($row, $con, $con_zweb, $ver_permisos);
                if(REQUIERE_ZWEB_USER && !$datos_completos->info_zweb->id_zweb){
                    throw new Exception('El acceso a esta aplicación requiere un usuario de zweb');
                }
                return $datos_completos;
            } 
            
            if ($prepare->rowCount() === 0) {
                throw new Exception('El alias ingresado no existe');
            } else {
                throw new Exception('Error: Se encontraron múltiples resultados para el alias ingresado.');
            }
        }
		catch (Exception $e) {
            $this->alias = $alias;
            $this->error = $e;
        }
        return $this;
    }
    // Función para completar los datos del usuario directamente
    public function completarDatos($datos, $con, $con_zweb, $ver_permisos)
    {
        try {
            if (!is_array($datos) && !is_object($datos)) {
                throw new Exception('Los datos proporcionados no son válidos.');
            }
            if (is_object($datos)) {
                $datos = get_object_vars($datos);
            }
            // Asegurarse de que los campos necesarios estén presentes en los datos
            $requiredFields = ['id_user', 'alias', 'admin_rrhh', 'nombre', 'id_grupo_usuario', 'grupo_usuario', 'apellido', 'mail'];
            foreach ($requiredFields as $clave) {
                if (!array_key_exists($clave, $datos)) {
                    throw new Exception("Falta el campo requerido: $clave.");
                }
            }
            // Completar los datos del usuario
            $this->id = $datos['id_user'];
            $this->alias = $datos['alias'];
            $this->pass_verified = is_null($this->pass_verified) ? false : $this->pass_verified;
            $this->admin_rrhh = $datos['admin_rrhh'];
            $this->id_grupo_usuario = $datos['id_grupo_usuario'];
            $this->grupo_usuario = $datos['grupo_usuario'];
            $this->zweb_predeterminado = $datos['zweb_predeterminado'];
            $this->legajos = $this->traer_listado_legajos($this->alias, $con, $con_zweb);
            $this->nombre = $datos['nombre'];
            $this->apellido = $datos['apellido'];
            $this->mail = $datos['mail'];
            $this->info_zweb = $con_zweb ? new UserInfo($this->alias, $con_zweb, $this->zweb_predeterminado) : null;
            $this->permisos = $ver_permisos ? $this->permisos_acceso($this->id, $this->info_zweb, $con) : null;
            $this->error = false;
        } catch (Exception $e) {
            $this->error = $e;
        }
        return $this;
    }
    // Función para comparar contraseña con hash guardado, esta otra base sí usa Bcrypt
    protected function pass_check($password, $hash) {
        return password_verify($password, $hash);
    }
    protected function traer_listado_legajos($alias, $con, $conZweb) {
        $array = array();
        $prepare = $con->pdo->prepare(file_get_contents(RUTA . '/src/Model/Legacy/RRHH/User-listadoLegajosUser.sql'));
        $prepare->execute(array(
            ':alias' => $alias
        ));
        while($row = $prepare->fetch(PDO::FETCH_ASSOC)){
            if(!is_null($row['id'])){
                $array[] = new Legajo($row['id'], $con, $conZweb);
            }
        }     
        return $array;
    }
    protected function permisos_acceso($id, $info_zweb, $con){
        $array = array();
        $forzar_acceso = -1;
        if(is_array($info_zweb->grupos_usuario)){
            $grupos_usuario = array_column($info_zweb->grupos_usuario, 'id');
            if( !empty(array_intersect($grupos_usuario, GRUPOS_CON_ACCESO) || $id == '341' ) ){
                $forzar_acceso = ID_APLICACION;
            }
        }
        $prepare = $con->pdo->prepare(file_get_contents(RUTA . '/src/Model/Legacy/RRHH/User-listaAccesosApps.sql'));
        $prepare->execute(array(
            ':user' => $id,
            ':forzar_acceso' => $forzar_acceso
        ));
        $forzar_acceso = $forzar_acceso > 0 ? true : false;
        while($row = $prepare->fetch(PDO::FETCH_ASSOC)){
            // dump($row); session_destroy();exit;
            $array[] = new App($row['id'],$id, $forzar_acceso, $con);
        }    
        return $array;
    }
}

// primer parametro: alias:contraseña segun rrhh_new.usuarios (string) u objeto/array con datos
// segundo parametro: conexion a la base de rrhh
// tercer parametro: [opcional] conexion a la base de zweb
// cuarto parametro: [opcional] bool (traer permisos de acceso a aplicaciones o no), default no
class User extends UserFactory
{
    public function __construct($dato_usuario, $con, $con_zweb = false, $ver_permisos = false){
        try {
            //determina que el objeto de conexion sea válido
            if(!$con){
                throw new Exception("No se ha pasado un argumento de conexión a RRHH.");
            }
            if (!is_object($con)) {
                throw new Exception("No se ha pasado una conexión válida como parámetro.");
            }
            if (!property_exists($con, 'pdo')) {
                throw new Exception("PDO no encontrado en la primera capa de la conexión.");
            }
            // --------
            // Si se proporciona un array u objeto con los datos del usuario, completamos los datos
            if (is_array($dato_usuario) || is_object($dato_usuario)) {
                $this->completarDatos($dato_usuario, $con, $con_zweb, $ver_permisos);
            }
            //si es un string, busco separarlo para determinar usuario de contraseña y lo envio para verificacion
            else if (is_string($dato_usuario)) {
                $this->login(explode(':', $dato_usuario)[0], explode(':', $dato_usuario)[1], $con, $con_zweb, $ver_permisos);
            }
            else{
                throw new Exception("No se puede crear objeto User con la información proporcionada");
            }
        } catch (Exception $e) {
            $this->error = $e;
        }
    }
}

?>
