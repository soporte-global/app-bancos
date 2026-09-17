<?php

require_once dirname(__DIR__) . '/src/config.php';
require_once RUTA . '/src/autoload.php';

use AppBancos\Application\ActualizarValidacionAutomaticaRegla;
use AppBancos\Application\EjecutorComandoIdempotente;
use AppBancos\Application\RecursoNoDisponibleException;
use AppBancos\Infrastructure\EsquemaBancos;
use AppBancos\Repository\AuditoriaRepository;
use AppBancos\Repository\ConfiguracionClasificacionRepository;
use AppBancos\Repository\IdempotenciaRepository;

function comprobarValidacionAutomatica($condicion, $mensaje)
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
     WHERE lower(usuario) = 'mcaballero' AND habilitado IS TRUE AND fecha_eliminacion IS NULL"
)->fetchColumn();
comprobarValidacionAutomatica($operadorId > 0, 'No se encontro el administrador de prueba.');

$base = $pdo->query(
    "SELECT configuracion_id, subtipo_valor_zetti_id
     FROM global_temp.bancos_regla_clasificacion
     ORDER BY configuracion_id, id LIMIT 1"
)->fetch(PDO::FETCH_ASSOC);
comprobarValidacionAutomatica($base !== false, 'Faltan reglas sombra para la prueba.');

$marca = bin2hex(random_bytes(8));
$clave = 'test-config-validar-' . $marca;
$insertar = $pdo->prepare(
    "INSERT INTO global_temp.bancos_regla_clasificacion
        (configuracion_id, subtipo_valor_zetti_id, sentido, codigo_extracto,
         validar_automaticamente, observacion, usuario_creacion, usuario_modificacion)
     VALUES (:configuracion_id, :subtipo_id, 'A', :codigo, false, :observacion, :usuario, :usuario)
     RETURNING id"
);
$insertar->execute([
    ':configuracion_id' => $base['configuracion_id'],
    ':subtipo_id' => $base['subtipo_valor_zetti_id'],
    ':codigo' => 'TEST-' . $marca,
    ':observacion' => 'TEST-CONFIG-VALIDAR|' . $marca,
    ':usuario' => $operadorId,
]);
$reglaId = (string) $insertar->fetchColumn();

$esquema = EsquemaBancos::desdeConfiguracion(['bancos_debug' => true]);
$repositorio = new ConfiguracionClasificacionRepository($pdo, $esquema);
$casoDeUso = new ActualizarValidacionAutomaticaRegla(
    $repositorio,
    new EjecutorComandoIdempotente(
        $pdo,
        new IdempotenciaRepository($pdo, $esquema),
        new AuditoriaRepository($pdo, $esquema)
    )
);
$nuevaReglaId = null;

try {
    $entrada = [
        'configuracion_id' => (string) $base['configuracion_id'],
        'regla_id' => $reglaId,
        'validar_automaticamente' => true,
    ];
    $primera = $casoDeUso->ejecutar($entrada, $operadorId, $clave);
    $repetida = $casoDeUso->ejecutar($entrada, $operadorId, $clave);
    comprobarValidacionAutomatica($primera['respuesta']['cambio'] === true, 'El cambio no fue aplicado.');
    comprobarValidacionAutomatica($primera['respuesta']['validar_automaticamente'] === true, 'La bandera no quedo habilitada.');
    comprobarValidacionAutomatica($repetida['repetida'] === true, 'El reintento no recupero la respuesta idempotente.');
    $nuevaReglaId = $primera['respuesta']['regla_id'];
    comprobarValidacionAutomatica($nuevaReglaId !== $reglaId, 'El cambio no creo una version nueva.');
    comprobarValidacionAutomatica($primera['respuesta']['version'] === 2, 'La nueva version no incremento su numero.');

    $consulta = $pdo->prepare(
        'SELECT id::text, activo, validar_automaticamente, usuario_modificacion, reemplaza_regla_id::text
         FROM global_temp.bancos_regla_clasificacion WHERE id IN (:anterior, :nueva) ORDER BY id'
    );
    $consulta->execute([':anterior' => $reglaId, ':nueva' => $nuevaReglaId]);
    $versiones = $consulta->fetchAll(PDO::FETCH_ASSOC);
    comprobarValidacionAutomatica(count($versiones) === 2, 'No se conservaron ambas versiones.');
    $porId = [];
    foreach ($versiones as $version) {
        $porId[$version['id']] = $version;
    }
    comprobarValidacionAutomatica((bool) $porId[$reglaId]['activo'] === false, 'La version anterior sigue activa.');
    comprobarValidacionAutomatica((bool) $porId[$nuevaReglaId]['validar_automaticamente'] === true, 'La nueva version no fue persistida.');
    comprobarValidacionAutomatica($porId[$nuevaReglaId]['reemplaza_regla_id'] === $reglaId, 'Se perdio la cadena de reemplazo.');
    comprobarValidacionAutomatica((int) $porId[$nuevaReglaId]['usuario_modificacion'] === $operadorId, 'No se registro al operador.');

    $detalle = $repositorio->consultar((string) $base['configuracion_id']);
    $encontrada = array_values(array_filter($detalle['reglas'], static function (array $regla) use ($nuevaReglaId) {
        return $regla['id'] === $nuevaReglaId;
    }));
    comprobarValidacionAutomatica(count($encontrada) === 1 && $encontrada[0]['validar_automaticamente'], 'La lectura no refleja el cambio.');

    try {
        $entradaAjena = $entrada;
        $entradaAjena['configuracion_id'] = '999999999999';
        $casoDeUso->ejecutar($entradaAjena, $operadorId, 'test-config-ajena-' . $marca);
        throw new RuntimeException('Se actualizo una regla fuera de su configuracion.');
    } catch (RecursoNoDisponibleException $esperada) {
    }
    try {
        $entradaInvalida = $entrada;
        $entradaInvalida['validar_automaticamente'] = 1;
        $casoDeUso->ejecutar($entradaInvalida, $operadorId, 'test-config-bool-' . $marca);
        throw new RuntimeException('Se acepto un booleano no estricto.');
    } catch (InvalidArgumentException $esperada) {
    }

    $consulta = $pdo->prepare(
        "SELECT ea.detalles
         FROM global_temp.bancos_evento_auditoria ea
         JOIN global_temp.bancos_solicitud_idempotente si ON si.id = ea.solicitud_id
         WHERE si.clave = :clave"
    );
    $consulta->execute([':clave' => $clave]);
    $detalles = json_decode((string) $consulta->fetchColumn(), true);
    comprobarValidacionAutomatica($detalles['anterior'] === false && $detalles['cambio'] === true, 'La auditoria no conserva el estado anterior.');

    $permisos = $pdo->query(
        "SELECT p.origen_id, count(pu.id) FILTER (WHERE pu.ignorado IS NULL) AS asignaciones
         FROM global_prod.hub_permisos p
         LEFT JOIN global_prod.hub_permisos_usuario pu ON pu.permiso = p.id
         WHERE p.origen_id IN (
             'app_bancos:destino:configuraciones',
             'app_bancos:accion:configuracion-actualizar-validacion-automatica'
         ) AND p.ignorado IS NULL
         GROUP BY p.origen_id"
    )->fetchAll(PDO::FETCH_KEY_PAIR);
    comprobarValidacionAutomatica((int) $permisos['app_bancos:destino:configuraciones'] === 2, 'El destino no quedo limitado a los dos administradores.');
    comprobarValidacionAutomatica((int) $permisos['app_bancos:accion:configuracion-actualizar-validacion-automatica'] === 0, 'Se asigno el permiso granular a usuarios nuevos.');

    $vista = file_get_contents(dirname(__DIR__) . '/app/html/configuraciones.php');
    $javascript = file_get_contents(dirname(__DIR__) . '/app/js/shared.js');
    $api = file_get_contents(dirname(__DIR__) . '/api.php');
    foreach (['data-configuracion-page', 'data-alternar-validacion', 'no clasifica retroactivamente'] as $fragmento) {
        comprobarValidacionAutomatica(strpos($vista, $fragmento) !== false, 'Falta el contrato visual: ' . $fragmento);
    }
    foreach (['configuracion.actualizar-validacion-automatica', 'Idempotency-Key', 'data-validar-automaticamente'] as $fragmento) {
        comprobarValidacionAutomatica(strpos($javascript, $fragmento) !== false, 'Falta el contrato cliente: ' . $fragmento);
    }
    foreach (["'configuracion.actualizar-validacion-automatica'", "'configuracion-actualizar-validacion-automatica'", 'HTTP_IDEMPOTENCY_KEY'] as $fragmento) {
        comprobarValidacionAutomatica(strpos($api, $fragmento) !== false, 'Falta la proteccion API: ' . $fragmento);
    }
} finally {
    $pdo->beginTransaction();
    $consulta = $pdo->prepare(
        "DELETE FROM global_temp.bancos_evento_auditoria
         WHERE solicitud_id IN (
             SELECT id FROM global_temp.bancos_solicitud_idempotente
             WHERE clave IN (:clave, :ajena, :booleana)
         )"
    );
    $consulta->execute([
        ':clave' => $clave,
        ':ajena' => 'test-config-ajena-' . $marca,
        ':booleana' => 'test-config-bool-' . $marca,
    ]);
    $consulta = $pdo->prepare(
        'DELETE FROM global_temp.bancos_solicitud_idempotente
         WHERE clave IN (:clave, :ajena, :booleana)'
    );
    $consulta->execute([
        ':clave' => $clave,
        ':ajena' => 'test-config-ajena-' . $marca,
        ':booleana' => 'test-config-bool-' . $marca,
    ]);
    if ($nuevaReglaId !== null) {
        $consulta = $pdo->prepare('DELETE FROM global_temp.bancos_regla_clasificacion WHERE id = :id');
        $consulta->execute([':id' => $nuevaReglaId]);
    }
    $consulta = $pdo->prepare('DELETE FROM global_temp.bancos_regla_clasificacion WHERE id = :id');
    $consulta->execute([':id' => $reglaId]);
    $pdo->commit();
}

echo "Actualizacion de validacion automatica debug OK\n";
