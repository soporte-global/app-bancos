<?php 
namespace HClasses\Zweb\Procesos;
use Exception;
use PDO;

abstract class ProcesoFactory{
	public $id;     
    public $nombre;           
	public $ruta;                
    public $extension;        
    public $texto_ok;        
    public $texto_error;        
    public $ruta_log;   
	public $usuario;  
	public $ssh;   
    public $ssh_server;  
    public $tipo_proceso;  
    public $ultima_ejecucion;         
    public $error;                  
	public function desde_respuesta_query( $linea_respuesta_query ){
        try{
			$this->id = $linea_respuesta_query['id'];           
			$this->nombre = $linea_respuesta_query['nombre_proceso'];
			$this->ruta = $linea_respuesta_query['ruta_proceso'];       
			$this->extension = $linea_respuesta_query['extension'];       
			$this->texto_ok = $linea_respuesta_query['texto_ok'];       
			$this->texto_error = $linea_respuesta_query['texto_error'];       
			$this->ruta_log = $linea_respuesta_query['ruta_log'];       
			$this->usuario = $linea_respuesta_query['usuario'];       
			$this->ssh = $linea_respuesta_query['ssh'];      
			$this->ssh_server = $linea_respuesta_query['ssh_server']; 
			$this->tipo_proceso = $linea_respuesta_query['tipo_proceso']; 
			$this->ultima_ejecucion = $this->calculaUltimaEjecucion( $linea_respuesta_query );                  
			$this->error = false;
        }
		catch(Exception $e){
			$this->id = false;  
			$this->nombre = false; 
			$this->ruta = false;    
			$this->extension = false;      
			$this->texto_ok = false;  
			$this->texto_error = false;       
			$this->ruta_log = false;     
			$this->usuario = false;  
			$this->ssh = false; 
			$this->ssh_server = false; 
            $this->tipo_proceso = false; 
			$this->ultima_ejecucion = $this->calculaUltimaEjecucion( false );
			$this->error = $e;
		}
        return $this;
	}
	public function desde_id( $id_proc ){
		global $conZweb;     
		// usa la funcion de postgres ccg_info()       
		$prepare = $conZweb->dbase->pdo->prepare(file_get_contents(RUTA . '/src/Model/Legacy/Zweb/Procesos-Proceso.sql'));
		$prepare->execute(array(
			':idProc' => $id_proc  
		));
		$res = $prepare->fetchAll(PDO::FETCH_ASSOC);
		$this->desde_respuesta_query( $res[0] );
	}
    public function calculaUltimaEjecucion ( $linea_respuesta_query ){
        if(!$linea_respuesta_query){
            return (object) array(
                'id' => false,
                'inicio' => false,
                'fin' => false,
                'estado' => false
            );
        }
        return (object) array(
            'id' => $linea_respuesta_query['id_ultimo_proceso'],
            'inicio' => $linea_respuesta_query['inicio_ultimo_proceso'],
            'fin' => $linea_respuesta_query['fin_ultimo_proceso'],
            'estado' => $linea_respuesta_query['estado_ultimo_proceso']
        );
    }
}

class Proceso extends ProcesoFactory {
	public function __construct( $dato_proceso = 'all' ){
		if( gettype($dato_proceso) == 'integer' || gettype($dato_proceso) == 'string' ){
			return $this->desde_id( $dato_proceso );
		}
		// usa resultado de la funcion de postgres global_producto()
		return $this->desde_respuesta_query( $dato_proceso );
    }
}

class ListadoProcesos{
    public $array = array();
	public function __construct( ){
        global $conZweb;
        $res = array();
        $prepare = $conZweb->dbase->pdo->prepare(file_get_contents(RUTA . '/src/Model/Legacy/Zweb/Procesos-Proceso.sql'));
        $prepare->execute(array(
            ':idProc' => 0  // trae todo
        ));
        while($row = $prepare->fetch(PDO::FETCH_ASSOC)){
            $this->array[] = new Proceso($row);
        }
        // usa resultado de la funcion de postgres global_producto()
		return $this->array;
    }
}

?>
