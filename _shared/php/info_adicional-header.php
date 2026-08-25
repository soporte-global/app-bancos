<?php
try{
    // determina nivel de acceso
    $nivel_acceso = 2;
    foreach( $user->permisos as $permiso ){
        if($permiso->id == ID_APLICACION){
            $nivel_acceso = $permiso->nivel;
        }
    }
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}
catch(Exception $e){
    envia_error_visualizacion($e);
}

?>
