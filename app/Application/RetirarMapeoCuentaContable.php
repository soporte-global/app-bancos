<?php
namespace AppBancos\Application;

use AppBancos\Repository\MapeoCuentaContableRepository;
use InvalidArgumentException;

final class RetirarMapeoCuentaContable
{
    private $mapeos;
    private $ejecutor;
    public function __construct(MapeoCuentaContableRepository $mapeos, EjecutorComandoIdempotente $ejecutor){$this->mapeos=$mapeos;$this->ejecutor=$ejecutor;}
    public function ejecutar(array $entrada,$operadorId,$clave){$configuracionId=$this->id($entrada['configuracion_id']??null,'configuracion_id');$mapeoId=$this->id($entrada['mapeo_id']??null,'mapeo_id');$motivo=trim((string)($entrada['motivo']??''));if(mb_strlen($motivo,'UTF-8')<3||mb_strlen($motivo,'UTF-8')>200){throw new InvalidArgumentException('motivo debe contener entre 3 y 200 caracteres.');}$canonica=['configuracion_id'=>$configuracionId,'mapeo_id'=>$mapeoId,'motivo'=>$motivo];return $this->ejecutor->ejecutar('configuracion.retirar-mapeo',$clave,$operadorId,$canonica,['accion'=>'CONFIGURACION_RETIRAR_MAPEO','recurso_tipo'=>'MAPEO_CUENTA_CONTABLE','recurso_id'=>$mapeoId,'resultado'=>'EXITOSA','detalles'=>['motivo'=>$motivo]],function()use($canonica,$operadorId){return ['codigo_http'=>200,'respuesta'=>$this->mapeos->retirar($canonica['configuracion_id'],$canonica['mapeo_id'],$operadorId)];});}
    private function id($v,$c){$v=trim((string)$v);if(!preg_match('/^[0-9]{1,20}$/',$v)||($v=ltrim($v,'0'))===''){throw new InvalidArgumentException($c.' es invalido.');}return $v;}
}
