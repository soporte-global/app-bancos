<?php
require_once dirname(__DIR__) . '/src/config.php';
require_once RUTA . '/src/autoload.php';

use AppBancos\Application\EjecutorComandoIdempotente;
use AppBancos\Application\GuardarMapeoCuentaContable;
use AppBancos\Application\RetirarMapeoCuentaContable;
use AppBancos\Infrastructure\EsquemaBancos;
use AppBancos\Repository\AuditoriaRepository;
use AppBancos\Repository\IdempotenciaRepository;
use AppBancos\Repository\MapeoCuentaContableRepository;

function comprobarMapeo($condicion,$mensaje){if(!$condicion){throw new RuntimeException($mensaje);}}

$pdo=new PDO('pgsql:host='.HOST.';port='.PORT.';dbname='.DBASE,USER,PASS,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$operador=(int)$pdo->query("SELECT id FROM global_prod.rrhh_login WHERE lower(usuario)='mcaballero' AND habilitado IS TRUE AND fecha_eliminacion IS NULL")->fetchColumn();
$configuracion=(string)$pdo->query('SELECT id FROM global_temp.bancos_configuracion WHERE activo IS TRUE ORDER BY id LIMIT 1')->fetchColumn();
$consulta=$pdo->prepare("SELECT sv.id::text FROM public.subtipo_valor sv WHERE NOT EXISTS(SELECT 1 FROM global_temp.bancos_mapeo_cuenta_contable m WHERE m.configuracion_id=:configuracion AND m.subtipo_valor_zetti_id=sv.id) ORDER BY sv.id LIMIT 1");
$consulta->execute([':configuracion'=>$configuracion]);
$subtipo=(string)$consulta->fetchColumn();
$cuentas=$pdo->query('SELECT id::text FROM public.cuenta ORDER BY id LIMIT 2')->fetchAll(PDO::FETCH_COLUMN);
comprobarMapeo($operador>0&&$configuracion!==''&&$subtipo!==''&&count($cuentas)===2,'Falta contexto para probar mapeos.');

$marca=bin2hex(random_bytes(7));
$claves=['crear'=>'test-mapeo-crear-'.$marca,'versionar'=>'test-mapeo-versionar-'.$marca,'retirar'=>'test-mapeo-retirar-'.$marca];
$esquema=EsquemaBancos::desdeConfiguracion(['bancos_debug'=>true]);
$repo=new MapeoCuentaContableRepository($pdo,$esquema);
$ejecutor=new EjecutorComandoIdempotente($pdo,new IdempotenciaRepository($pdo,$esquema),new AuditoriaRepository($pdo,$esquema));
$guardar=new GuardarMapeoCuentaContable($repo,$ejecutor);
$retirar=new RetirarMapeoCuentaContable($repo,$ejecutor);
$ids=[];
try{
    $entrada=['configuracion_id'=>$configuracion,'mapeo_id'=>null,'subtipo_valor_zetti_id'=>(int)$subtipo,'cuenta_zetti_id'=>$cuentas[0],'motivo'=>'Alta aislada de mapeo contable'];
    $creado=$guardar->ejecutar($entrada,$operador,$claves['crear']);
    $repetido=$guardar->ejecutar($entrada,$operador,$claves['crear']);
    $ids[]=$creado['respuesta']['mapeo_id'];
    comprobarMapeo($creado['codigo_http']===201&&$creado['respuesta']['version']===1,'No se creo la version inicial.');
    comprobarMapeo($repetido['repetida']===true,'El alta no fue idempotente.');

    $entrada['mapeo_id']=$ids[0];$entrada['cuenta_zetti_id']=$cuentas[1];$entrada['motivo']='Cambio aislado de cuenta contable';
    $versionado=$guardar->ejecutar($entrada,$operador,$claves['versionar']);
    $ids[]=$versionado['respuesta']['mapeo_id'];
    comprobarMapeo($versionado['respuesta']['version']===2&&$versionado['respuesta']['mapeo_id_anterior']===$ids[0],'No se encadeno la version nueva.');

    $baja=$retirar->ejecutar(['configuracion_id'=>$configuracion,'mapeo_id'=>$ids[1],'motivo'=>'Retiro aislado del mapeo'], $operador,$claves['retirar']);
    comprobarMapeo($baja['respuesta']['activo']===false,'No se aplico la baja logica.');
    $consulta=$pdo->prepare('SELECT id::text,activo,version,reemplaza_mapeo_id::text FROM global_temp.bancos_mapeo_cuenta_contable WHERE id IN (:a,:b) ORDER BY version');
    $consulta->execute([':a'=>$ids[0],':b'=>$ids[1]]);$versiones=$consulta->fetchAll(PDO::FETCH_ASSOC);
    comprobarMapeo(count($versiones)===2&&!(bool)$versiones[0]['activo']&&!(bool)$versiones[1]['activo'],'No se conservaron las versiones inactivas.');
    comprobarMapeo($versiones[1]['reemplaza_mapeo_id']===$ids[0],'La cadena historica es incorrecta.');

    $visibles=array_filter($repo->listar($configuracion),static function(array $m)use($ids){return in_array($m['id'],$ids,true);});
    comprobarMapeo(count($visibles)===0,'La lectura expone mapeos retirados.');
    $permiso=$pdo->query("SELECT count(DISTINCT p.id) permisos,count(pu.id) asignaciones FROM global_prod.hub_permisos p LEFT JOIN global_prod.hub_permisos_usuario pu ON pu.permiso=p.id AND pu.ignorado IS NULL WHERE p.origen_id='app_bancos:accion:configuracion-administrar-mapeos' AND p.ignorado IS NULL")->fetch(PDO::FETCH_ASSOC);
    comprobarMapeo((int)$permiso['permisos']===1&&(int)$permiso['asignaciones']===0,'El permiso granular no cumple el contrato.');
    $vista=file_get_contents(dirname(__DIR__).'/app/html/configuraciones.php');$js=file_get_contents(dirname(__DIR__).'/app/js/shared.js');$api=file_get_contents(dirname(__DIR__).'/api.php');
    foreach(['data-editor-mapeo','data-editar-mapeo','data-retirar-mapeo'] as $f){comprobarMapeo(strpos($vista,$f)!==false,'Falta contrato visual: '.$f);}
    foreach(['configuracion.guardar-mapeo','configuracion.retirar-mapeo','aplicarMapeo'] as $f){comprobarMapeo(strpos($js,$f)!==false,'Falta contrato cliente: '.$f);}
    foreach(["'configuracion.guardar-mapeo'","'configuracion.retirar-mapeo'","'configuracion-administrar-mapeos'"] as $f){comprobarMapeo(strpos($api,$f)!==false,'Falta proteccion API: '.$f);}
}finally{
    $pdo->beginTransaction();
    $q=$pdo->prepare("DELETE FROM global_temp.bancos_evento_auditoria WHERE solicitud_id IN(SELECT id FROM global_temp.bancos_solicitud_idempotente WHERE clave IN(:crear,:versionar,:retirar))");$q->execute($claves);
    $q=$pdo->prepare('DELETE FROM global_temp.bancos_solicitud_idempotente WHERE clave IN(:crear,:versionar,:retirar)');$q->execute($claves);
    foreach(array_reverse($ids) as $id){$q=$pdo->prepare('DELETE FROM global_temp.bancos_mapeo_cuenta_contable WHERE id=:id');$q->execute([':id'=>$id]);}
    $pdo->commit();
}
echo "Administracion de mapeos contables debug OK\n";
