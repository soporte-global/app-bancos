<?php 
namespace HClasses\Zweb\Productos;
use HClasses\Conexiones\ConexionZetti;
use Exception;
use PDO;

abstract class ProductoFactory{
	public $id;                
	public $nombre;                
    public $codigo_troquel;        
    public $codigo_externo;        
    public $codigo_barra;        
    public $habilitado;   
	public $infoCompras;  
	public $listaPrecio_infoCompras;   
    public $cod_secundarios;  
	public $lista_secundarios;
    public $error;                  
	
	public function desde_respuesta_query($conZweb, $linea_respuesta_query, $id_lista, $mostrar_secundarios ){
        try{
			$this->id = $linea_respuesta_query['id'];           
			$this->nombre = $linea_respuesta_query['nombre'];
			$this->codigo_troquel = $linea_respuesta_query['codigo_troquel'];       
			$this->codigo_externo = $linea_respuesta_query['codigo_externo'];      
			$this->codigo_barra = $linea_respuesta_query['codigo_barra']; 
			$this->cod_secundarios = $linea_respuesta_query['cod_secundarios'];      
			$this->habilitado = $linea_respuesta_query['habilitado'];
			$this->listaPrecio_infoCompras = $id_lista;
			if( $id_lista ){
				$this->infoCompras = new CCGinfoProd( $conZweb, $linea_respuesta_query['id'], $id_lista);     
			}else{
				$this->infoCompras = new CCGinfoProd($conZweb, false, false);     
			}
			if( $mostrar_secundarios ){
				$this->lista_secundarios = $this->getCodigosSecundarios($linea_respuesta_query['id'],$linea_respuesta_query['codigo_barra'], $conZweb);
			}
			$this->error = false;
        }
		catch(Exception $e){
			$this->id = false;           
			$this->nombre = false;    
			$this->codigo_troquel = false;    
			$this->codigo_externo = false;    
			$this->codigo_barra = false;    
			$this->cod_secundarios = false;    
			$this->habilitado = false;    
			$this->listaPrecio_infoCompras = false;
			$this->infoCompras = new CCGinfoProd($conZweb, false, false);  
			$this->lista_secundarios = array();
			$this->error = $e;
		}
        return $this;
	}
	public function desde_id($id_prod, $id_lista, $mostrar_secundarios, $conZweb){
		//global $conZweb;     
		// usa la funcion de postgres ccg_info()       
		$prepare = $conZweb->dbase->pdo->prepare(file_get_contents('src/Model/Legacy/Zweb/Productos-Producto.sql'));
		$prepare->execute(array(
			':idProd' => $id_prod  
		));
		$res = $prepare->fetchAll(PDO::FETCH_ASSOC);
		$this->desde_respuesta_query( $conZweb, $res[0], $id_lista, $mostrar_secundarios);
	}
	private function getCodigosSecundarios($id_prod, $cod_principal, $conZweb ){
		//global $conZweb;     
		//trae listado de codigos secundarios bajo la columna "codigo" que no sean el principal
		$prepare = $conZweb->dbase->pdo->prepare(file_get_contents('src/Model/Legacy/Zweb/Productos-Producto-cod_sec.sql'));
		$prepare->execute(array(
			':idProd' => $id_prod, 
			':codPrincipal' => $cod_principal
		));
		$respuesta = array();
		while($row = $prepare->fetch(PDO::FETCH_ASSOC)){
            $respuesta[] = $row['codigo'];
        }
		return $respuesta;
	}
}

//$dato_producto -------> o un ID de producto de zweb o una respuesta completa de la query de global_prod()
//$id_lista ------------> o un ID de lista de precios de zweb o false si no es relevante el dato (por defecto false)
//mostrar secundarios --> bool (por defecto false)
class Producto extends ProductoFactory {
	public function __construct( $conZweb, $dato_producto, $id_lista = false, $mostrar_secundarios = false ){
		if( gettype($dato_producto) == 'integer' || gettype($dato_producto) == 'string' ){
			return $this->desde_id( $dato_producto, $id_lista, $mostrar_secundarios, $conZweb );
		}
		// usa resultado de la funcion de postgres global_producto()
		return $this->desde_respuesta_query( $conZweb, $dato_producto, $id_lista, $mostrar_secundarios );
    }
}


// resultado de la funcion de postgres ccg_info()
abstract class CCGinfoProdFactory {
	public $lista_precio; 
	public $id_estado ; 
	public $estado ; 
	public $codigo_proveedor ; 
	public $multiplo ; 
	public $minimo ; 
	public $pallet ; 
	public $modulo ; 
	public $unidades_modulo ; 
	public $descuento_modulo ; 
	public $condicion ; 
	public $multiplo_condicion ; 
	public $descuento_condicion ;
	public $descuento1 ; 
	public $descuento2 ; 
	public $descuento3 ; 
	public $descuento4 ; 
	public $precio_costo; 
	public $precio_sugerido; 
	public $fecha_actualizacion_precios ; 
	public $fecha_actualizacion_datos ; 
	public $error ;
	//------------------
	public function desde_respuesta_query($respuesta_query, $lista_precio){
        try{
			if($respuesta_query == false || $lista_precio == false){
				throw new Exception('Información insuficiente');
			}
			$this->lista_precio = $lista_precio; 
			$this->codigo_proveedor = $respuesta_query['codigo_proveedor']; 
			$this->id_estado = $respuesta_query['id_estado']; 
			$this->estado = $respuesta_query['estado']; 
			$this->multiplo = $respuesta_query['multiplo']; 
			$this->minimo = $respuesta_query['minimo']; 
			$this->pallet = $respuesta_query['pallet']; 
			$this->modulo = $respuesta_query['modulo']; 
			$this->unidades_modulo = $respuesta_query['unidades_modulo']; 
			$this->descuento_modulo = $respuesta_query['descuento_modulo']; 
			$this->condicion = $respuesta_query['condicion']; 
			$this->multiplo_condicion = $respuesta_query['multiplo_condicion']; 
			$this->descuento_condicion = $respuesta_query['descuento_condicion'];
			$this->descuento1 = $respuesta_query['descuento1']; 
			$this->descuento2 = $respuesta_query['descuento2']; 
			$this->descuento3 = $respuesta_query['descuento3']; 
			$this->descuento4 = $respuesta_query['descuento4']; 
			$this->precio_costo = $respuesta_query["precio_costo"];  
			$this->precio_sugerido = $respuesta_query["precio_sugerido"];  
			$this->fecha_actualizacion_precios = $respuesta_query['fecha_actualizacion_precios']; 
			$this->fecha_actualizacion_datos = $respuesta_query['fecha_actualizacion_datos']; 
			$this->error = false;
        }
		catch(Exception $e){
			$this->lista_precio = $lista_precio; 
			$this->codigo_proveedor = false;
			$this->estado = false;
			$this->multiplo = false;
			$this->minimo = false;
			$this->pallet = false;
			$this->modulo = false;
			$this->unidades_modulo = false;
			$this->descuento_modulo = false;
			$this->condicion = false;
			$this->multiplo_condicion = false;
			$this->descuento_condicion = false;
			$this->descuento1 = false;
			$this->descuento2 = false;
			$this->descuento3 = false;
			$this->descuento4 = false;
			$this->precio_costo = false;
			$this->precio_sugerido = false; 
			$this->fecha_actualizacion_precios = false;
			$this->fecha_actualizacion_datos = false;
			$this->error = $e;
		}
        return $this;
    }
	public function desde_id($id_prod, $id_lista, $conZweb){
		//global $conZweb;     
		// usa la funcion de postgres ccg_info()     
		$prepare = $conZweb->dbase->pdo->prepare(file_get_contents('src/Model/Legacy/Zweb/Productos-CCGInfoProd.sql'));
		$prepare->execute(array(
			':idLista' => $id_lista,    
			':idProd' => $id_prod  
		));
		$res = $prepare->fetchAll(PDO::FETCH_ASSOC);
		$this->desde_respuesta_query( $res[0], $id_lista);
	}
}

class CCGinfoProd extends CCGinfoProdFactory {
	public function __construct( $conZweb, $dato_producto, $id_lista ){
		if( gettype($dato_producto) == 'integer' || gettype($dato_producto) == 'string' ){
			return $this->desde_id( $dato_producto, $id_lista, $conZweb );
		}
		// usa resultado de la funcion de postgres ccg_info()
		return $this->desde_respuesta_query( $dato_producto, $id_lista );
    }
}

?>
