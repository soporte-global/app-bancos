<?php
namespace AppBancos\Repository;

use AppBancos\Application\RecursoNoDisponibleException;
use AppBancos\Infrastructure\EsquemaBancos;
use PDO;
use PDOException;
use RuntimeException;

final class ReglaAsignacionRepository
{
    private $pdo;
    private $configuraciones;
    private $reglas;
    private $subtipos;
    public function __construct(PDO $pdo,EsquemaBancos $esquema){$this->pdo=$pdo;$this->configuraciones=$esquema->tablaBancos('bancos_configuracion');$this->reglas=$esquema->tablaBancos('bancos_regla_asignacion_usuario');$this->subtipos=$esquema->tablaLecturaErp('subtipo_valor');}

    public function listar($configuracionId)
    {
        $q=$this->pdo->prepare("SELECT r.id::text,r.subtipo_valor_zetti_id::text subtipo_id,sv.nombre subtipo,r.usuario::text usuario_id,l.usuario,r.version FROM {$this->reglas} r JOIN {$this->subtipos} sv ON sv.id=r.subtipo_valor_zetti_id JOIN global_prod.rrhh_login l ON l.id=r.usuario WHERE r.configuracion_id=:configuracion_id AND r.activo IS TRUE ORDER BY sv.nombre,r.id");
        $q->execute([':configuracion_id'=>(string)$configuracionId]);
        return array_map(static function(array$f){$f['version']=(int)$f['version'];return $f;},$q->fetchAll(PDO::FETCH_ASSOC));
    }

    public function listarResponsablesElegibles()
    {
        $sql="SELECT l.id::text,l.usuario FROM global_prod.rrhh_login l WHERE l.habilitado IS TRUE AND l.fecha_eliminacion IS NULL AND EXISTS(SELECT 1 FROM global_prod.hub_permisos_efectivos_usuario e JOIN global_prod.hub_permisos p ON p.id=e.permiso WHERE e.usuario=l.id AND p.aplicacion=".(int)ID_APLICACION." AND p.tipo_permiso=1 AND p.ignorado IS NULL) ORDER BY lower(l.usuario),l.id";
        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerResponsablesActivos($configuracionId)
    {
        $q=$this->pdo->prepare("SELECT r.subtipo_valor_zetti_id::text,r.usuario::text FROM {$this->reglas} r JOIN global_prod.rrhh_login l ON l.id=r.usuario AND l.habilitado IS TRUE AND l.fecha_eliminacion IS NULL WHERE r.configuracion_id=:configuracion_id AND r.activo IS TRUE AND EXISTS(SELECT 1 FROM global_prod.hub_permisos_efectivos_usuario e JOIN global_prod.hub_permisos p ON p.id=e.permiso WHERE e.usuario=r.usuario AND p.aplicacion=".(int)ID_APLICACION." AND p.tipo_permiso=1 AND p.ignorado IS NULL)");
        $q->execute([':configuracion_id'=>(string)$configuracionId]);
        return $q->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    public function guardar($configuracionId,$reglaId,$subtipoId,$usuarioId,$operadorId,$motivo)
    {
        $this->bloquearConfiguracion($configuracionId);$this->validarSubtipo($subtipoId);$this->validarResponsable($usuarioId);
        if($reglaId===null){$n=$this->insertar($configuracionId,$subtipoId,$usuarioId,$operadorId,$motivo,null,1);return $this->respuesta(null,$n,true);}
        $a=$this->bloquearRegla($configuracionId,$reglaId);
        if((string)$a['subtipo_valor_zetti_id']===(string)$subtipoId&&(string)$a['usuario']===(string)$usuarioId){return $this->respuesta($a,$a,false);}
        $this->desactivar($a['id'],$operadorId);$n=$this->insertar($configuracionId,$subtipoId,$usuarioId,$operadorId,$motivo,$a['id'],(int)$a['version']+1);return $this->respuesta($a,$n,true);
    }
    public function retirar($configuracionId,$reglaId,$operadorId){$this->bloquearConfiguracion($configuracionId);$a=$this->bloquearRegla($configuracionId,$reglaId);$this->desactivar($a['id'],$operadorId);return $this->respuesta($a,$a,true)+['activo'=>false];}
    private function bloquearConfiguracion($id){$q=$this->pdo->prepare("SELECT id FROM {$this->configuraciones} WHERE id=:id AND activo IS TRUE FOR UPDATE");$q->execute([':id'=>(string)$id]);if($q->fetchColumn()===false){throw new RecursoNoDisponibleException('La configuracion no existe o no esta activa.');}}
    private function validarSubtipo($id){$q=$this->pdo->prepare("SELECT 1 FROM {$this->subtipos} WHERE id=:id");$q->execute([':id'=>(int)$id]);if($q->fetchColumn()===false){throw new RecursoNoDisponibleException('El subtipo de valor no existe en ERP.');}}
    private function validarResponsable($id){$q=$this->pdo->prepare("SELECT 1 FROM global_prod.rrhh_login l WHERE l.id=:id AND l.habilitado IS TRUE AND l.fecha_eliminacion IS NULL AND EXISTS(SELECT 1 FROM global_prod.hub_permisos_efectivos_usuario e JOIN global_prod.hub_permisos p ON p.id=e.permiso WHERE e.usuario=l.id AND p.aplicacion=:aplicacion AND p.tipo_permiso=1 AND p.ignorado IS NULL)");$q->execute([':id'=>(int)$id,':aplicacion'=>ID_APLICACION]);if($q->fetchColumn()===false){throw new RecursoNoDisponibleException('El responsable no tiene acceso efectivo a APP BANCOS.');}}
    private function bloquearRegla($c,$id){$q=$this->pdo->prepare("SELECT id,configuracion_id,subtipo_valor_zetti_id,usuario,version FROM {$this->reglas} WHERE id=:id AND configuracion_id=:c AND activo IS TRUE FOR UPDATE");$q->execute([':id'=>(string)$id,':c'=>(string)$c]);$f=$q->fetch(PDO::FETCH_ASSOC);if($f===false){throw new RecursoNoDisponibleException('La regla de asignacion no existe o ya no esta activa.');}return$f;}
    private function desactivar($id,$op){$q=$this->pdo->prepare("UPDATE {$this->reglas} SET activo=false,fecha_modificacion=current_timestamp,usuario_modificacion=:op WHERE id=:id AND activo IS TRUE");$q->execute([':op'=>(int)$op,':id'=>(string)$id]);if($q->rowCount()!==1){throw new RuntimeException('No se pudo conservar la regla anterior.');}}
    private function insertar($c,$s,$u,$op,$motivo,$reemplaza,$version){try{$q=$this->pdo->prepare("INSERT INTO {$this->reglas}(configuracion_id,subtipo_valor_zetti_id,usuario,activo,observacion,version,reemplaza_regla_id,origen,usuario_creacion,usuario_modificacion) VALUES(:c,:s,:u,true,:motivo,:version,:reemplaza,'MANUAL',:op,:op) RETURNING id,configuracion_id,subtipo_valor_zetti_id,usuario,version,reemplaza_regla_id");$q->execute([':c'=>(string)$c,':s'=>(int)$s,':u'=>(int)$u,':motivo'=>$motivo,':version'=>(int)$version,':reemplaza'=>$reemplaza,':op'=>(int)$op]);}catch(PDOException$e){if($e->getCode()==='23505'){throw new RecursoNoDisponibleException('El subtipo ya posee un responsable activo en esta configuracion.');}throw$e;}$f=$q->fetch(PDO::FETCH_ASSOC);if($f===false){throw new RuntimeException('No se pudo crear la regla de asignacion.');}return$f;}
    private function respuesta($a,array$n,$cambio){return['configuracion_id'=>(string)$n['configuracion_id'],'regla_asignacion_id_anterior'=>$a===null?null:(string)$a['id'],'regla_asignacion_id'=>(string)$n['id'],'subtipo_valor_zetti_id'=>(string)$n['subtipo_valor_zetti_id'],'usuario_id'=>(string)$n['usuario'],'version'=>(int)$n['version'],'cambio'=>(bool)$cambio];}
}
