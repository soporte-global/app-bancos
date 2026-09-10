<?php

require_once dirname(__DIR__) . '/src/config.php';
require_once RUTA . '/src/autoload.php';

use AppBancos\Application\ConfirmarImportacionExtracto;
use AppBancos\Application\ClasificadorImportacionExtracto;
use AppBancos\Application\EjecutorComandoIdempotente;
use AppBancos\Application\ParserExtractoDelimitado;
use AppBancos\Application\RecursoNoDisponibleException;
use AppBancos\Infrastructure\EsquemaBancos;
use AppBancos\Repository\AuditoriaRepository;
use AppBancos\Repository\BusquedaRecursosErpRepository;
use AppBancos\Repository\ContextoImportacionRepository;
use AppBancos\Repository\IdempotenciaRepository;
use AppBancos\Repository\ImportacionExtractoRepository;

function comprobarConfirmacionImportacion($condicion, $mensaje)
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
$esquema = EsquemaBancos::desdeConfiguracion(['bancos_debug' => true]);
$contexto = new ContextoImportacionRepository($pdo, $esquema);
$cuentas = $contexto->consultar();
$configuraciones = $contexto->consultarConfiguraciones();
$cuenta = null;
$configuracion = null;
$subtipoCredito = null;
$buscarSubtipo = $pdo->prepare(
    "SELECT v.subtipo_valor
     FROM public.valor v
     WHERE v.entidad = :cuenta_id
       AND v.subtipo_valor IS NOT NULL
       AND v.estado NOT IN (7, 20, 36)
       AND abs(v.monto_principal) >= 1234.56
       AND NOT EXISTS (
           SELECT 1 FROM global_temp.bancos_reserva_recurso r
           WHERE r.valor_zetti_id = v.id AND r.activo IS TRUE
       )
       AND NOT EXISTS (
           SELECT 1 FROM global_temp.bancos_asociacion_movimiento a
           WHERE a.valor_zetti_id = v.id AND a.activo IS TRUE
       )
     GROUP BY v.subtipo_valor
     ORDER BY count(*) DESC, v.subtipo_valor
     LIMIT 1"
);
foreach ($cuentas as $cuentaCandidata) {
    $configuracionCandidata = null;
    foreach ($configuraciones as $candidata) {
        if ($candidata['cuenta_bancaria_id'] === $cuentaCandidata['id']) {
            $configuracionCandidata = $candidata;
            break;
        }
    }
    if ($configuracionCandidata === null) {
        continue;
    }
    $buscarSubtipo->execute([':cuenta_id' => $cuentaCandidata['id']]);
    $subtipo = $buscarSubtipo->fetchColumn();
    if ($subtipo !== false) {
        $cuenta = $cuentaCandidata;
        $configuracion = $configuracionCandidata;
        $subtipoCredito = (int) $subtipo;
        break;
    }
}
comprobarConfirmacionImportacion(
    $cuenta !== null && $configuracion !== null && $subtipoCredito !== null,
    'No existe una cuenta/configuracion con valores ERP para la prueba.'
);

$usuarioId = (int) $pdo->query(
    "SELECT id FROM global_prod.rrhh_login
     WHERE lower(usuario) = 'hvega' AND habilitado IS TRUE AND fecha_eliminacion IS NULL"
)->fetchColumn();
comprobarConfirmacionImportacion($usuarioId > 0, 'No se encontro el operador hvega.');

$api = file_get_contents(dirname(__DIR__) . '/api.php');
$vista = file_get_contents(dirname(__DIR__) . '/app/html/importaciones.php');
$javascript = file_get_contents(dirname(__DIR__) . '/app/js/shared.js');
foreach (["'importacion.confirmar'", "'importacion-confirmar'", 'HTTP_IDEMPOTENCY_KEY', 'multipart/form-data'] as $fragmento) {
    comprobarConfirmacionImportacion(strpos($api, $fragmento) !== false, 'Falta la proteccion API: ' . $fragmento);
}
foreach (['data-confirmar-importacion', 'data-importacion-configuracion'] as $fragmento) {
    comprobarConfirmacionImportacion(strpos($vista, $fragmento) !== false, 'Falta el control visual: ' . $fragmento);
}
foreach (['importacion.confirmar', 'Idempotency-Key', 'nuevaClaveIdempotencia'] as $fragmento) {
    comprobarConfirmacionImportacion(strpos($javascript, $fragmento) !== false, 'Falta el contrato cliente: ' . $fragmento);
}

$repositorio = new ImportacionExtractoRepository($pdo, $esquema);
$casoDeUso = new ConfirmarImportacionExtracto(
    new ParserExtractoDelimitado(),
    $repositorio,
    new ClasificadorImportacionExtracto(),
    new EjecutorComandoIdempotente(
        $pdo,
        new IdempotenciaRepository($pdo, $esquema),
        new AuditoriaRepository($pdo, $esquema)
    )
);
$archivo = [
    'error' => UPLOAD_ERR_OK,
    'tmp_name' => __DIR__ . '/fixtures/importacion-valida.csv',
    'name' => 'extracto-confirmacion.csv',
];
$entrada = [
    'cuenta_bancaria_id' => $cuenta['id'],
    'configuracion_id' => $configuracion['id'],
    'inicio_periodo' => '2026-09-01',
];
$clave = 'test-importacion-' . bin2hex(random_bytes(10));
$claveDuplicada = 'test-importacion-' . bin2hex(random_bytes(10));
$claveInvalida = 'test-importacion-' . bin2hex(random_bytes(10));
$marcaReglas = 'TEST-CLASIFICACION-' . bin2hex(random_bytes(8));
$importacionId = null;

try {
    $insertarRegla = $pdo->prepare(
        "INSERT INTO global_temp.bancos_regla_clasificacion
            (configuracion_id, subtipo_valor_zetti_id, sentido, codigo_extracto,
             validar_automaticamente, observacion)
         VALUES (:configuracion_id, :subtipo_id, :sentido, :codigo, :validar, :observacion)"
    );
    foreach ([
        [$subtipoCredito, 'C', 'CREDITO', true],
        [83, 'D', 'DEBITO', true],
        [93, 'D', 'DEBITO', false],
    ] as $regla) {
        $insertarRegla->execute([
            ':configuracion_id' => $configuracion['id'],
            ':subtipo_id' => $regla[0],
            ':sentido' => $regla[1],
            ':codigo' => $regla[2],
            ':validar' => $regla[3] ? 'true' : 'false',
            ':observacion' => $marcaReglas,
        ]);
    }

    $primera = $casoDeUso->ejecutar($entrada, $archivo, $usuarioId, $clave);
    $importacionId = $primera['respuesta']['importacion_id'];
    $segunda = $casoDeUso->ejecutar($entrada, $archivo, $usuarioId, $clave);
    comprobarConfirmacionImportacion($primera['codigo_http'] === 201, 'La creacion no devolvio HTTP 201.');
    comprobarConfirmacionImportacion($primera['repetida'] === false, 'La primera confirmacion fue marcada como repetida.');
    comprobarConfirmacionImportacion($segunda['repetida'] === true, 'El reintento no fue idempotente.');
    comprobarConfirmacionImportacion($segunda['respuesta'] == $primera['respuesta'], 'El reintento cambio la respuesta.');
    comprobarConfirmacionImportacion($primera['respuesta']['total_movimientos'] === 2, 'El lote no informa dos movimientos.');
    comprobarConfirmacionImportacion($primera['respuesta']['estado'] === 'ABIERTO', 'El lote no quedo ABIERTO.');
    comprobarConfirmacionImportacion(
        $primera['respuesta']['clasificacion']['univocas'] === 1
            && $primera['respuesta']['clasificacion']['multiples'] === 1,
        'El resumen de clasificacion es incorrecto.'
    );

    $consulta = $pdo->prepare(
        "SELECT i.configuracion_id::text, i.cuenta_bancaria_zetti_id::text,
                i.inicio_periodo::text, i.total_movimientos, e.codigo,
                i.usuario_creacion, i.usuario_modificacion,
                count(m.id) AS movimientos,
                min(m.id_periodo) AS id_periodo_min,
                max(m.id_periodo) AS id_periodo_max,
                min(m.numero_fila_origen) AS fila_min,
                max(m.numero_fila_origen) AS fila_max,
                bool_and(m.usuario_creacion = :usuario_id AND m.usuario_modificacion = :usuario_id) AS operadores_validos
         FROM global_temp.bancos_importacion_extracto i
         JOIN global_temp.bancos_estado e ON e.id = i.estado_id
         JOIN global_temp.bancos_movimiento_extracto m ON m.importacion_id = i.id
         WHERE i.id = :importacion_id
         GROUP BY i.id, e.codigo"
    );
    $consulta->execute([':usuario_id' => $usuarioId, ':importacion_id' => $importacionId]);
    $persistida = $consulta->fetch(PDO::FETCH_ASSOC);
    comprobarConfirmacionImportacion($persistida !== false, 'No se encontro la importacion persistida.');
    comprobarConfirmacionImportacion($persistida['configuracion_id'] === $configuracion['id'], 'Se persistio otra configuracion.');
    comprobarConfirmacionImportacion($persistida['cuenta_bancaria_zetti_id'] === $cuenta['id'], 'Se persistio otra cuenta.');
    comprobarConfirmacionImportacion((int) $persistida['movimientos'] === 2, 'No se persistieron exactamente dos movimientos.');
    comprobarConfirmacionImportacion($persistida['id_periodo_min'] === 'IMP-' . $importacionId, 'id_periodo no identifica al lote.');
    comprobarConfirmacionImportacion($persistida['id_periodo_min'] === $persistida['id_periodo_max'], 'Los movimientos no comparten id_periodo.');
    comprobarConfirmacionImportacion((int) $persistida['fila_min'] === 2 && (int) $persistida['fila_max'] === 3, 'No se conservaron las filas de origen.');
    comprobarConfirmacionImportacion(in_array($persistida['operadores_validos'], [true, 1, '1', 't'], true), 'No se guardo el operador en los movimientos.');

    $consulta = $pdo->prepare(
        "SELECT id, numero_fila_origen, subtipo_valor_zetti_id
         FROM global_temp.bancos_movimiento_extracto
         WHERE importacion_id = :importacion_id
         ORDER BY numero_fila_origen"
    );
    $consulta->execute([':importacion_id' => $importacionId]);
    $clasificadas = $consulta->fetchAll(PDO::FETCH_ASSOC);
    comprobarConfirmacionImportacion((int) $clasificadas[0]['subtipo_valor_zetti_id'] === $subtipoCredito, 'La regla univoca no clasifico el credito.');
    comprobarConfirmacionImportacion($clasificadas[1]['subtipo_valor_zetti_id'] === null, 'La regla multiple eligio un subtipo arbitrario.');

    $candidatos = (new BusquedaRecursosErpRepository($pdo, $esquema))->buscarValores(
        (int) $clasificadas[0]['id'],
        $cuenta['id'],
        '2026-09-01',
        20
    );
    comprobarConfirmacionImportacion(count($candidatos['resultados']) > 0, 'La regla univoca no produjo candidatos ERP.');
    foreach ($candidatos['resultados'] as $candidato) {
        comprobarConfirmacionImportacion(
            (int) $candidato['subtipo_id'] === $subtipoCredito,
            'La busqueda asistida ignoro el subtipo permitido por la regla.'
        );
    }

    $consulta = $pdo->prepare(
        "SELECT count(*)
         FROM global_temp.bancos_evento_auditoria a
         JOIN global_temp.bancos_solicitud_idempotente s ON s.id = a.solicitud_id
         WHERE s.operacion = 'importacion.confirmar' AND s.clave = :clave"
    );
    $consulta->execute([':clave' => $clave]);
    comprobarConfirmacionImportacion((int) $consulta->fetchColumn() === 1, 'La confirmacion no dejo una unica auditoria.');

    try {
        $casoDeUso->ejecutar($entrada, $archivo, $usuarioId, $claveDuplicada);
        throw new RuntimeException('El mismo archivo se importo con otra clave.');
    } catch (RecursoNoDisponibleException $esperada) {
        comprobarConfirmacionImportacion(
            strpos($esperada->getMessage(), 'ya fue importado') !== false,
            'El conflicto de hash devolvio otro error.'
        );
    }

    try {
        $entradaInvalida = $entrada;
        $entradaInvalida['configuracion_id'] = '999999999999';
        $casoDeUso->ejecutar($entradaInvalida, $archivo, $usuarioId, $claveInvalida);
        throw new RuntimeException('Se acepto una configuracion ajena a la cuenta.');
    } catch (RecursoNoDisponibleException $esperada) {
    }
} finally {
    $pdo->beginTransaction();
    if ($importacionId !== null) {
        $consulta = $pdo->prepare('DELETE FROM global_temp.bancos_movimiento_extracto WHERE importacion_id = :id');
        $consulta->execute([':id' => $importacionId]);
        $consulta = $pdo->prepare('DELETE FROM global_temp.bancos_importacion_extracto WHERE id = :id');
        $consulta->execute([':id' => $importacionId]);
    }
    $consulta = $pdo->prepare(
        'DELETE FROM global_temp.bancos_regla_clasificacion WHERE observacion = :observacion'
    );
    $consulta->execute([':observacion' => $marcaReglas]);
    $claves = [$clave, $claveDuplicada, $claveInvalida];
    $consulta = $pdo->prepare(
        "DELETE FROM global_temp.bancos_evento_auditoria
         WHERE solicitud_id IN (
             SELECT id FROM global_temp.bancos_solicitud_idempotente
             WHERE operacion = 'importacion.confirmar' AND clave IN (?, ?, ?)
         )"
    );
    $consulta->execute($claves);
    $consulta = $pdo->prepare(
        "DELETE FROM global_temp.bancos_solicitud_idempotente
         WHERE operacion = 'importacion.confirmar' AND clave IN (?, ?, ?)"
    );
    $consulta->execute($claves);
    $pdo->commit();
}

echo "Confirmacion de importacion de extracto OK\n";
