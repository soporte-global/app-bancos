<?php 
namespace HClasses\Zweb\Usuarios;
use PDO;



// ----------------------------------------------------------------------------
// NUNCA IMPLEMENTADO: y necesita revisión:
// ----------------------------------------------------------------------------
class Comisiones { //detalle 
    // ¿y si para obtener el general uso el detallado en un with y despues vario lo que va en el select?
    // acá entonces irian los datos agrupados en los public y un public $detalle con todo el resultado
    public $alias;
    public $total_comisionado;
    public $porcent_clientes;
    public $detallePC;
    public $detalleVentas;

    public function __construct($datos, $con, $detalle = false){
        //si es un objeto lo array-ifica
        if (is_object($datos)) {
            $datos = get_object_vars($datos);
        }
        $this->alias = $datos['alias'];
        //
        //parte de la query original, extraido para ejecutarlo una sola vez
        $noCom = $con->pdo->prepare(file_get_contents(RUTA . '/src/Model/Legacy/Zweb/Usuarios-Comisiones-entidad_no_comisionable.sql'));
        $noCom->execute();
        $noCom = $noCom->fetch(PDO::FETCH_ASSOC)['entidad'];
        //
        //ejecuta query agrupadora
        $prepare = $con->pdo->prepare(
            str_replace(
                '"$$QUERY_COMISIONES$$"',
                file_get_contents(RUTA . '/src/Model/Legacy/Zweb/Usuarios-Comisiones.sql'),
                file_get_contents(RUTA . '/src/Model/Legacy/Zweb/Usuarios-ComisionesAgrup.sql')
            )
        );
        $prepare->execute(array(
            ':alias' => $this->alias,
            ':desde' => $datos['desde'],
            ':hasta' => $datos['hasta'],
            ':no_comision' => $noCom
        ));
        $this->detallePC = $prepare->fetch(PDO::FETCH_ASSOC);
        $this->calcula_comisiones();
        //
        //si se quiere más nivel de detalle, debe volver a ejecutar
        if($detalle){
            $prepare = $con->pdo->prepare(
                file_get_contents(RUTA . '/src/Model/Legacy/Zweb/Usuarios-Comisiones.sql')
            );
            $prepare->execute(array(
                ':alias' => $this->alias,
                ':desde' => $datos['desde'],
                ':hasta' => $datos['hasta'],
                ':no_comision' => $noCom
            ));
            $this->detalleVentas = $prepare->fetch(PDO::FETCH_ASSOC);
        }else{
            $this->detalleVentas = false;
        }
        //
        return $this;
    }

    private function calcula_comisiones(){
        //obtiene total de ventas y total de ventas a clientes
        $totalVentas = array_reduce($this->detallePC, function($carry, $item) {
            return $carry + $item["total_ventas"];
        }, 0);
        $totalVentasCliente = array_reduce($this->detallePC, function($carry, $item) {
            return $carry + $item["ventas_cliente"];
        }, 0);
        //calcula y setea porcentaje de ventas a clientes
        $this->porcent_clientes = ($totalVentasCliente / $totalVentas) * 100;
        $min_cliente = $this->detallePC[0]['min_cliente'];
        //si el porcentaje no es suficiente, no comisiona y sale de la función
        if($this->porcent_clientes < $min_cliente){
            $this->total_comisionado = 0;
            return;
        }
        //obtiene datos extra de configuración del piso
        $sin_0 = $this->detallePC[0]['sin_0'];
        $piso_restante = $this->detallePC[0]['piso_comision'];
        
    }
    
}


class CuentaCorriente {
	
}
?>
