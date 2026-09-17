<?php

require_once dirname(__DIR__) . '/src/config.php';
require_once RUTA . '/src/autoload.php';

use AppBancos\Application\EjecutorComandoIdempotente;
use AppBancos\Application\GuardarReglaClasificacion;
use AppBancos\Application\RetirarReglaClasificacion;
use AppBancos\Infrastructure\EsquemaBancos;
use AppBancos\Repository\AuditoriaRepository;
use AppBancos\Repository\ConfiguracionClasificacionRepository;
use AppBancos\Repository\IdempotenciaRepository;

function comprobarVersionRegla($condicion, $mensaje)
{
    if (!$condicion) {
        throw new RuntimeException($mensaje);
    }
}

$pdo = new PDO(
    'pgsql:host=' . HOST . ';port=' . PORT . ';dbname=' . DBASE,
    USER,
    PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$operadorId = (int) $pdo->query(
    "SELECT id FROM global_prod.rrhh_login
     WHERE lower(usuario)='mcaballero' AND habilitado IS TRUE AND fecha_eliminacion IS NULL"
)->fetchColumn();
$base = $pdo->query(
    "SELECT configuracion_id::text, subtipo_valor_zetti_id::text
     FROM global_temp.bancos_regla_clasificacion
     WHERE activo IS TRUE ORDER BY id LIMIT 1"
)->fetch(PDO::FETCH_ASSOC);
comprobarVersionRegla($operadorId > 0 && $base !== false, 'Falta contexto para probar el versionado.');

$marca = strtoupper(bin2hex(random_bytes(7)));
$claves = [
    'crear' => 'test-regla-crear-' . $marca,
    'versionar' => 'test-regla-versionar-' . $marca,
    'retirar' => 'test-regla-retirar-' . $marca,
];
$esquema = EsquemaBancos::desdeConfiguracion(['bancos_debug' => true]);
$repositorio = new ConfiguracionClasificacionRepository($pdo, $esquema);
$ejecutor = new EjecutorComandoIdempotente(
    $pdo,
    new IdempotenciaRepository($pdo, $esquema),
    new AuditoriaRepository($pdo, $esquema)
);
$guardar = new GuardarReglaClasificacion($repositorio, $ejecutor);
$retirar = new RetirarReglaClasificacion($repositorio, $ejecutor);
$ids = [];

try {
    $entrada = [
        'configuracion_id' => $base['configuracion_id'],
        'regla_id' => null,
        'subtipo_valor_zetti_id' => (int) $base['subtipo_valor_zetti_id'],
        'sentido' => 'c',
        'codigo_extracto' => ' test ' . $marca . ' ',
        'validar_automaticamente' => false,
        'motivo' => 'Alta aislada para prueba de versionado',
    ];
    $creada = $guardar->ejecutar($entrada, $operadorId, $claves['crear']);
    $repetida = $guardar->ejecutar($entrada, $operadorId, $claves['crear']);
    $ids[] = $creada['respuesta']['regla_id'];
    comprobarVersionRegla($creada['codigo_http'] === 201 && $creada['respuesta']['version'] === 1, 'El alta no creo la version inicial.');
    comprobarVersionRegla($creada['respuesta']['codigo_extracto'] === 'TEST ' . $marca, 'El codigo no fue normalizado.');
    comprobarVersionRegla($repetida['repetida'] === true, 'El alta no fue idempotente.');

    $entrada['regla_id'] = $creada['respuesta']['regla_id'];
    $entrada['sentido'] = 'A';
    $entrada['validar_automaticamente'] = true;
    $entrada['motivo'] = 'Cambio controlado de sentido y validacion';
    $versionada = $guardar->ejecutar($entrada, $operadorId, $claves['versionar']);
    $ids[] = $versionada['respuesta']['regla_id'];
    comprobarVersionRegla($versionada['respuesta']['version'] === 2, 'La nueva version no incremento el numero.');
    comprobarVersionRegla($versionada['respuesta']['regla_id_anterior'] === $ids[0], 'La version no referencia su antecedente.');

    $baja = $retirar->ejecutar([
        'configuracion_id' => $base['configuracion_id'],
        'regla_id' => $ids[1],
        'motivo' => 'Retiro aislado de la regla de prueba',
    ], $operadorId, $claves['retirar']);
    comprobarVersionRegla($baja['respuesta']['activo'] === false, 'El retiro no aplico la baja logica.');

    $consulta = $pdo->prepare(
        "SELECT id::text, activo, version, reemplaza_regla_id::text
         FROM global_temp.bancos_regla_clasificacion
         WHERE id IN (:primera, :segunda) ORDER BY version"
    );
    $consulta->execute([':primera' => $ids[0], ':segunda' => $ids[1]]);
    $versiones = $consulta->fetchAll(PDO::FETCH_ASSOC);
    comprobarVersionRegla(count($versiones) === 2, 'No se conservaron las dos versiones.');
    comprobarVersionRegla(!(bool) $versiones[0]['activo'] && !(bool) $versiones[1]['activo'], 'Una version retirada sigue activa.');
    comprobarVersionRegla($versiones[1]['reemplaza_regla_id'] === $ids[0], 'La cadena historica es incorrecta.');

    $detalle = $repositorio->consultar($base['configuracion_id']);
    $visibles = array_filter($detalle['reglas'], static function (array $regla) use ($ids) {
        return in_array($regla['id'], $ids, true);
    });
    comprobarVersionRegla(count($visibles) === 0, 'La consulta operativa expone reglas inactivas.');

    $permiso = $pdo->query(
        "SELECT count(DISTINCT p.id) AS permisos, count(pu.id) AS asignaciones
         FROM global_prod.hub_permisos p
         LEFT JOIN global_prod.hub_permisos_usuario pu ON pu.permiso=p.id AND pu.ignorado IS NULL
         WHERE p.origen_id='app_bancos:accion:configuracion-administrar-reglas'
           AND p.ignorado IS NULL"
    )->fetch(PDO::FETCH_ASSOC);
    comprobarVersionRegla((int) $permiso['permisos'] === 1, 'No se registro el permiso de administrar reglas.');
    comprobarVersionRegla((int) $permiso['asignaciones'] === 0, 'El permiso de administrar reglas tiene asignaciones directas.');

    $vista = file_get_contents(dirname(__DIR__) . '/app/html/configuraciones.php');
    $javascript = file_get_contents(dirname(__DIR__) . '/app/js/shared.js');
    $api = file_get_contents(dirname(__DIR__) . '/api.php');
    foreach (['data-editor-regla', 'data-editar-regla', 'data-retirar-regla', 'baja lógica'] as $fragmento) {
        comprobarVersionRegla(strpos($vista, $fragmento) !== false, 'Falta el contrato visual: ' . $fragmento);
    }
    foreach (['configuracion.guardar-regla', 'configuracion.retirar-regla', 'aplicarReglaGuardada'] as $fragmento) {
        comprobarVersionRegla(strpos($javascript, $fragmento) !== false, 'Falta el contrato cliente: ' . $fragmento);
    }
    foreach (["'configuracion.guardar-regla'", "'configuracion.retirar-regla'", "'configuracion-administrar-reglas'"] as $fragmento) {
        comprobarVersionRegla(strpos($api, $fragmento) !== false, 'Falta la proteccion API: ' . $fragmento);
    }
} finally {
    $pdo->beginTransaction();
    $consulta = $pdo->prepare(
        "DELETE FROM global_temp.bancos_evento_auditoria
         WHERE solicitud_id IN (
             SELECT id FROM global_temp.bancos_solicitud_idempotente
             WHERE clave IN (:crear, :versionar, :retirar)
         )"
    );
    $consulta->execute($claves);
    $consulta = $pdo->prepare(
        'DELETE FROM global_temp.bancos_solicitud_idempotente
         WHERE clave IN (:crear, :versionar, :retirar)'
    );
    $consulta->execute($claves);
    foreach (array_reverse($ids) as $id) {
        $consulta = $pdo->prepare('DELETE FROM global_temp.bancos_regla_clasificacion WHERE id=:id');
        $consulta->execute([':id' => $id]);
    }
    $pdo->commit();
}

echo "Versionado y baja logica de reglas debug OK\n";
