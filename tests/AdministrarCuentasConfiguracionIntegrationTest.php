<?php

require_once dirname(__DIR__) . '/src/config.php';
require_once RUTA . '/src/autoload.php';

use AppBancos\Application\DesvincularCuentaConfiguracion;
use AppBancos\Application\EjecutorComandoIdempotente;
use AppBancos\Application\RecursoNoDisponibleException;
use AppBancos\Application\VincularCuentaConfiguracion;
use AppBancos\Infrastructure\EsquemaBancos;
use AppBancos\Repository\AuditoriaRepository;
use AppBancos\Repository\ConfiguracionClasificacionRepository;
use AppBancos\Repository\IdempotenciaRepository;
use AppBancos\Repository\ImportacionExtractoRepository;

function comprobarCuentaConfiguracion($condicion, $mensaje)
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
$configuracionId = (string) $pdo->query(
    'SELECT id FROM global_temp.bancos_configuracion WHERE activo IS TRUE ORDER BY id LIMIT 1'
)->fetchColumn();
$buscarCuenta = $pdo->prepare(
    "SELECT cb.id::text
     FROM public.cuenta_bancaria cb
     WHERE NOT EXISTS (
         SELECT 1 FROM global_temp.bancos_configuracion_cuenta cc
         WHERE cc.configuracion_id = :configuracion_id
           AND cc.cuenta_bancaria_zetti_id = cb.id
     )
     ORDER BY cb.id LIMIT 1"
);
$buscarCuenta->execute([':configuracion_id' => $configuracionId]);
$cuentaId = (string) $buscarCuenta->fetchColumn();
comprobarCuentaConfiguracion($operadorId > 0 && $configuracionId !== '' && $cuentaId !== '', 'Falta contexto para probar los vinculos.');

$marca = bin2hex(random_bytes(7));
$claves = [
    'vincular' => 'test-config-cuenta-vincular-' . $marca,
    'desvincular' => 'test-config-cuenta-desvincular-' . $marca,
    'reactivar' => 'test-config-cuenta-reactivar-' . $marca,
];
$esquema = EsquemaBancos::desdeConfiguracion(['bancos_debug' => true]);
$repositorio = new ConfiguracionClasificacionRepository($pdo, $esquema);
$ejecutor = new EjecutorComandoIdempotente(
    $pdo,
    new IdempotenciaRepository($pdo, $esquema),
    new AuditoriaRepository($pdo, $esquema)
);
$vincular = new VincularCuentaConfiguracion($repositorio, $ejecutor);
$desvincular = new DesvincularCuentaConfiguracion($repositorio, $ejecutor);
$entrada = [
    'configuracion_id' => $configuracionId,
    'cuenta_bancaria_id' => $cuentaId,
    'motivo' => 'Cobertura aislada del vinculo de cuenta',
];

try {
    $alta = $vincular->ejecutar($entrada, $operadorId, $claves['vincular']);
    $repetida = $vincular->ejecutar($entrada, $operadorId, $claves['vincular']);
    comprobarCuentaConfiguracion($alta['respuesta']['activa'] === true && $alta['respuesta']['cambio'] === true, 'No se activo el vinculo.');
    comprobarCuentaConfiguracion($repetida['repetida'] === true, 'El alta no fue idempotente.');

    $fila = $pdo->prepare(
        "SELECT activo, origen, usuario_creacion, usuario_modificacion
         FROM global_temp.bancos_configuracion_cuenta
         WHERE configuracion_id=:configuracion_id AND cuenta_bancaria_zetti_id=:cuenta_id"
    );
    $fila->execute([':configuracion_id' => $configuracionId, ':cuenta_id' => $cuentaId]);
    $vinculo = $fila->fetch(PDO::FETCH_ASSOC);
    comprobarCuentaConfiguracion((bool) $vinculo['activo'] && $vinculo['origen'] === 'MANUAL', 'El vinculo no conserva vigencia y origen.');
    comprobarCuentaConfiguracion((int) $vinculo['usuario_creacion'] === $operadorId, 'No se registro el operador del alta.');

    (new ImportacionExtractoRepository($pdo, $esquema))->validarConfiguracionCuenta($configuracionId, $cuentaId);
    $detalle = $repositorio->consultar($configuracionId);
    $visibles = array_filter($detalle['cuentas'], static function (array $cuenta) use ($cuentaId) {
        return $cuenta['id'] === $cuentaId;
    });
    comprobarCuentaConfiguracion(count($visibles) === 1, 'La cuenta activa no aparece en la configuracion.');

    $baja = $desvincular->ejecutar([
        'configuracion_id' => $configuracionId,
        'cuenta_bancaria_id' => $cuentaId,
        'motivo' => 'Cobertura aislada de la baja logica',
    ], $operadorId, $claves['desvincular']);
    comprobarCuentaConfiguracion($baja['respuesta']['activa'] === false, 'No se aplico la baja logica.');
    try {
        (new ImportacionExtractoRepository($pdo, $esquema))->validarConfiguracionCuenta($configuracionId, $cuentaId);
        throw new RuntimeException('Una cuenta desvinculada sigue habilitando importaciones.');
    } catch (RecursoNoDisponibleException $esperada) {
    }

    $reactivada = $vincular->ejecutar($entrada, $operadorId, $claves['reactivar']);
    comprobarCuentaConfiguracion($reactivada['respuesta']['activa'] === true, 'No se reactivo el vinculo historico.');
    comprobarCuentaConfiguracion((int) $pdo->query(
        "SELECT count(*) FROM global_temp.bancos_configuracion_cuenta
         WHERE configuracion_id={$configuracionId} AND cuenta_bancaria_zetti_id={$cuentaId}"
    )->fetchColumn() === 1, 'La reactivacion duplico el vinculo.');

    $permiso = $pdo->query(
        "SELECT count(DISTINCT p.id) permisos, count(pu.id) asignaciones
         FROM global_prod.hub_permisos p
         LEFT JOIN global_prod.hub_permisos_usuario pu ON pu.permiso=p.id AND pu.ignorado IS NULL
         WHERE p.origen_id='app_bancos:accion:configuracion-administrar-cuentas'
           AND p.ignorado IS NULL"
    )->fetch(PDO::FETCH_ASSOC);
    comprobarCuentaConfiguracion((int) $permiso['permisos'] === 1 && (int) $permiso['asignaciones'] === 0, 'El permiso granular no cumple el contrato.');

    $vista = file_get_contents(dirname(__DIR__) . '/app/html/configuraciones.php');
    $javascript = file_get_contents(dirname(__DIR__) . '/app/js/shared.js');
    $api = file_get_contents(dirname(__DIR__) . '/api.php');
    foreach (['data-editor-vinculo', 'data-desvincular-cuenta', 'futuras importaciones'] as $fragmento) {
        comprobarCuentaConfiguracion(strpos($vista, $fragmento) !== false, 'Falta el contrato visual: ' . $fragmento);
    }
    foreach (['configuracion.vincular-cuenta', 'configuracion.desvincular-cuenta', 'aplicarVinculo'] as $fragmento) {
        comprobarCuentaConfiguracion(strpos($javascript, $fragmento) !== false, 'Falta el contrato cliente: ' . $fragmento);
    }
    foreach (["'configuracion.vincular-cuenta'", "'configuracion.desvincular-cuenta'", "'configuracion-administrar-cuentas'"] as $fragmento) {
        comprobarCuentaConfiguracion(strpos($api, $fragmento) !== false, 'Falta la proteccion API: ' . $fragmento);
    }
} finally {
    $pdo->beginTransaction();
    $consulta = $pdo->prepare(
        "DELETE FROM global_temp.bancos_evento_auditoria
         WHERE solicitud_id IN (
             SELECT id FROM global_temp.bancos_solicitud_idempotente
             WHERE clave IN (:vincular, :desvincular, :reactivar)
         )"
    );
    $consulta->execute($claves);
    $consulta = $pdo->prepare(
        'DELETE FROM global_temp.bancos_solicitud_idempotente
         WHERE clave IN (:vincular, :desvincular, :reactivar)'
    );
    $consulta->execute($claves);
    $consulta = $pdo->prepare(
        'DELETE FROM global_temp.bancos_configuracion_cuenta
         WHERE configuracion_id=:configuracion_id AND cuenta_bancaria_zetti_id=:cuenta_id'
    );
    $consulta->execute([':configuracion_id' => $configuracionId, ':cuenta_id' => $cuentaId]);
    $pdo->commit();
}

echo "Administracion de cuentas por configuracion debug OK\n";
