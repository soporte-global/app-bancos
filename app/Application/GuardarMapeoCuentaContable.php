<?php
namespace AppBancos\Application;

use AppBancos\Repository\MapeoCuentaContableRepository;
use InvalidArgumentException;

final class GuardarMapeoCuentaContable
{
    private $mapeos;
    private $ejecutor;
    public function __construct(MapeoCuentaContableRepository $mapeos, EjecutorComandoIdempotente $ejecutor) { $this->mapeos=$mapeos; $this->ejecutor=$ejecutor; }
    public function ejecutar(array $entrada, $operadorId, $clave)
    {
        $configuracionId=$this->id($entrada['configuracion_id']??null,'configuracion_id');
        $mapeoId=($entrada['mapeo_id']??null)===null||trim((string)$entrada['mapeo_id'])===''?null:$this->id($entrada['mapeo_id'],'mapeo_id');
        $subtipo=filter_var($entrada['subtipo_valor_zetti_id']??null,FILTER_VALIDATE_INT);
        if($subtipo===false||$subtipo<1||$subtipo>32767){throw new InvalidArgumentException('subtipo_valor_zetti_id esta fuera de rango.');}
        $cuenta=$this->id($entrada['cuenta_zetti_id']??null,'cuenta_zetti_id');
        $motivo=$this->motivo($entrada['motivo']??null);
        $canonica=['configuracion_id'=>$configuracionId,'mapeo_id'=>$mapeoId,'subtipo_valor_zetti_id'=>(int)$subtipo,'cuenta_zetti_id'=>$cuenta,'motivo'=>$motivo];
        return $this->ejecutor->ejecutar($mapeoId===null?'configuracion.crear-mapeo':'configuracion.versionar-mapeo',$clave,$operadorId,$canonica,['accion'=>$mapeoId===null?'CONFIGURACION_CREAR_MAPEO':'CONFIGURACION_VERSIONAR_MAPEO','recurso_tipo'=>'MAPEO_CUENTA_CONTABLE','recurso_id'=>$mapeoId,'resultado'=>'EXITOSA','detalles'=>['motivo'=>$motivo]],function()use($canonica,$operadorId){$r=$this->mapeos->guardar($canonica['configuracion_id'],$canonica['mapeo_id'],$canonica['subtipo_valor_zetti_id'],$canonica['cuenta_zetti_id'],$operadorId,$canonica['motivo']);return ['codigo_http'=>$canonica['mapeo_id']===null?201:200,'respuesta'=>$r,'detalles_auditoria'=>['mapeo_id'=>$r['mapeo_id'],'mapeo_id_anterior'=>$r['mapeo_id_anterior'],'version'=>$r['version'],'cambio'=>$r['cambio']]];});
    }
    private function id($v,$c){$v=trim((string)$v);if(!preg_match('/^[0-9]{1,20}$/',$v)||($v=ltrim($v,'0'))===''){throw new InvalidArgumentException($c.' es invalido.');}return $v;}
    private function motivo($v){$v=trim((string)$v);if(mb_strlen($v,'UTF-8')<3||mb_strlen($v,'UTF-8')>200){throw new InvalidArgumentException('motivo debe contener entre 3 y 200 caracteres.');}return $v;}
}
