<?php

require_once dirname(__DIR__) . '/src/config.php';
require_once RUTA . '/src/autoload.php';

use AppBancos\Application\ParserExtractoDelimitado;
use AppBancos\Application\PrevisualizarImportacionExtracto;
use AppBancos\Application\ClasificadorImportacionExtracto;
use AppBancos\Application\GenerarReporteErroresImportacion;
use AppBancos\Application\RecursoNoDisponibleException;
use AppBancos\Infrastructure\EsquemaBancos;
use AppBancos\Repository\ContextoImportacionRepository;
use AppBancos\Repository\ImportacionExtractoRepository;

function comprobarPrevisualizacion($condicion, $mensaje)
{
    if (!$condicion) {
        throw new RuntimeException($mensaje);
    }
}

$parser = new ParserExtractoDelimitado();
$valido = $parser->analizar(__DIR__ . '/fixtures/importacion-valida.csv', 'extracto.csv', '2026-09-01');
comprobarPrevisualizacion($valido['valido'] === true, 'El archivo valido fue rechazado.');
comprobarPrevisualizacion($valido['total_filas'] === 2 && $valido['filas_validas'] === 2, 'El conteo valido es incorrecto.');
comprobarPrevisualizacion($valido['credito_total'] === '1234.56000', 'El total de credito es incorrecto.');
comprobarPrevisualizacion($valido['debito_total'] === '87.40000', 'El total de debito es incorrecto.');
comprobarPrevisualizacion($valido['previsualizacion'][0]['fecha_operacion'] === '2026-09-05', 'La fecha no fue normalizada.');
comprobarPrevisualizacion(strlen($valido['hash_sha256']) === 64, 'El hash SHA-256 es invalido.');

$clasificador = new ClasificadorImportacionExtracto();
$clasificacion = $clasificador->clasificar([
    ['codigo_extracto' => ' CREDITO ', 'credito' => '1.00000', 'debito' => '0.00000'],
    ['codigo_extracto' => 'credito', 'credito' => '0.00000', 'debito' => '1.00000'],
    ['codigo_extracto' => null, 'credito' => '1.00000', 'debito' => '0.00000'],
    ['codigo_extracto' => 'MULTI', 'credito' => '1.00000', 'debito' => '0.00000'],
], [
    ['codigo_extracto' => 'credito', 'sentido' => 'C', 'subtipo_valor_zetti_id' => 82, 'validar_automaticamente' => true],
    ['codigo_extracto' => 'multi', 'sentido' => 'A', 'subtipo_valor_zetti_id' => 83, 'validar_automaticamente' => false],
    ['codigo_extracto' => 'MULTI', 'sentido' => 'C', 'subtipo_valor_zetti_id' => 93, 'validar_automaticamente' => true],
]);
comprobarPrevisualizacion($clasificacion['filas'][0]['clasificacion'] === 'UNIVOCA', 'No se resolvio la regla univoca.');
comprobarPrevisualizacion($clasificacion['filas'][1]['clasificacion'] === 'SIN_REGLA', 'Se ignoro el sentido de la regla.');
comprobarPrevisualizacion($clasificacion['filas'][2]['clasificacion'] === 'SIN_CODIGO', 'No se distinguio la ausencia de codigo.');
comprobarPrevisualizacion($clasificacion['filas'][3]['clasificacion'] === 'MULTIPLE', 'Se eligio una regla ambigua.');
comprobarPrevisualizacion(
    $clasificacion['resumen'] === [
        'univocas' => 1,
        'multiples' => 1,
        'sin_regla' => 1,
        'sin_codigo' => 1,
        'habilitan_validacion_automatica' => 2,
    ],
    'El resumen de clasificacion es incorrecto.'
);

$invalido = $parser->analizar(__DIR__ . '/fixtures/importacion-invalida.tsv', 'extracto.tsv', '2026-09-01');
comprobarPrevisualizacion($invalido['valido'] === false, 'El archivo invalido fue aceptado.');
comprobarPrevisualizacion($invalido['total_errores'] === 3 && $invalido['filas_validas'] === 0, 'No se informaron todos los errores por fila.');
comprobarPrevisualizacion($invalido['errores'][0]['fila'] === 2, 'El error no conserva la fila de origen.');
$invalidoCompleto = $parser->analizar(
    __DIR__ . '/fixtures/importacion-invalida.tsv',
    'extracto.tsv',
    '2026-09-01',
    false,
    true
);
comprobarPrevisualizacion(count($invalidoCompleto['errores_completos']) === 3, 'El parser no expuso el detalle completo solicitado.');

$rutaMuchosErrores = tempnam(sys_get_temp_dir(), 'bancos-errores-');
if ($rutaMuchosErrores === false) {
    throw new RuntimeException('No se pudo preparar el archivo temporal de errores.');
}
$filasConError = array_fill(0, 105, '2026-09-05;Fila invalida;1;1');
file_put_contents(
    $rutaMuchosErrores,
    "fecha_operacion;descripcion;credito;debito\n" . implode("\n", $filasConError)
);
try {
    $muchosErrores = $parser->analizar($rutaMuchosErrores, 'muchos.csv', '2026-09-01', false, true);
    comprobarPrevisualizacion($muchosErrores['total_errores'] === 105, 'El total de errores completo es incorrecto.');
    comprobarPrevisualizacion(count($muchosErrores['errores']) === 100, 'La previsualizacion no respeto su limite de errores.');
    comprobarPrevisualizacion($muchosErrores['errores_truncados'] === true, 'No se indico el truncamiento visual.');
    comprobarPrevisualizacion(count($muchosErrores['errores_completos']) === 105, 'El reporte omitio errores despues del limite visual.');
} finally {
    unlink($rutaMuchosErrores);
}

$pdo = new PDO(
    'pgsql:host=' . HOST . ';port=' . PORT . ';dbname=' . DBASE,
    USER,
    PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$esquema = EsquemaBancos::desdeConfiguracion(['bancos_debug' => true]);
$cuentas = (new ContextoImportacionRepository($pdo, $esquema))->consultar();
comprobarPrevisualizacion(count($cuentas) > 0, 'El sandbox no expuso cuentas con configuracion efectiva.');
$configuraciones = (new ContextoImportacionRepository($pdo, $esquema))->consultarConfiguraciones();
$configuracion = null;
foreach ($configuraciones as $candidata) {
    if ($candidata['cuenta_bancaria_id'] === $cuentas[0]['id']) {
        $configuracion = $candidata;
        break;
    }
}
comprobarPrevisualizacion($configuracion !== null, 'La cuenta no expuso configuraciones seleccionables.');
$casoDeUso = new PrevisualizarImportacionExtracto(
    $parser,
    new ImportacionExtractoRepository($pdo, $esquema),
    new ClasificadorImportacionExtracto()
);
$antes = [
    'lotes' => (int) $pdo->query('SELECT count(*) FROM global_temp.bancos_importacion_extracto')->fetchColumn(),
    'movimientos' => (int) $pdo->query('SELECT count(*) FROM global_temp.bancos_movimiento_extracto')->fetchColumn(),
];
$resultado = $casoDeUso->ejecutar(
    [
        'cuenta_bancaria_id' => $cuentas[0]['id'],
        'configuracion_id' => $configuracion['id'],
        'inicio_periodo' => '2026-09-01',
    ],
    [
        'error' => UPLOAD_ERR_OK,
        'tmp_name' => __DIR__ . '/fixtures/importacion-valida.csv',
        'name' => 'extracto.csv',
    ]
);
comprobarPrevisualizacion($resultado['configuraciones_disponibles'] >= 1, 'No se informaron configuraciones candidatas.');
$generadorReporte = new GenerarReporteErroresImportacion($casoDeUso);
$reporte = $generadorReporte->ejecutar(
    [
        'cuenta_bancaria_id' => $cuentas[0]['id'],
        'configuracion_id' => $configuracion['id'],
        'inicio_periodo' => '2026-09-01',
    ],
    [
        'error' => UPLOAD_ERR_OK,
        'tmp_name' => __DIR__ . '/fixtures/importacion-invalida.tsv',
        'name' => 'extracto.tsv',
    ]
);
comprobarPrevisualizacion($reporte['nombre_archivo'] === 'extracto-errores.csv', 'El nombre del reporte es incorrecto.');
comprobarPrevisualizacion(substr($reporte['contenido'], 0, 3) === "\xEF\xBB\xBF", 'El CSV no incluye BOM UTF-8.');
$lineasReporte = preg_split('/\R/', trim(substr($reporte['contenido'], 3)));
comprobarPrevisualizacion(count($lineasReporte) === 4, 'El CSV no contiene cabecera y todos los errores.');
comprobarPrevisualizacion($lineasReporte[0] === 'fila;error', 'La cabecera del CSV es incorrecta.');
comprobarPrevisualizacion(strpos($lineasReporte[1], '2;') === 0, 'El CSV no conserva la fila de origen.');

try {
    $generadorReporte->ejecutar(
        [
            'cuenta_bancaria_id' => $cuentas[0]['id'],
            'configuracion_id' => $configuracion['id'],
            'inicio_periodo' => '2026-09-01',
        ],
        [
            'error' => UPLOAD_ERR_OK,
            'tmp_name' => __DIR__ . '/fixtures/importacion-valida.csv',
            'name' => 'extracto.csv',
        ]
    );
    throw new RuntimeException('Se genero un reporte para un archivo sin errores.');
} catch (InvalidArgumentException $esperada) {
}
$despues = [
    'lotes' => (int) $pdo->query('SELECT count(*) FROM global_temp.bancos_importacion_extracto')->fetchColumn(),
    'movimientos' => (int) $pdo->query('SELECT count(*) FROM global_temp.bancos_movimiento_extracto')->fetchColumn(),
];
comprobarPrevisualizacion($antes === $despues, 'La previsualizacion persistio datos.');

try {
    $casoDeUso->ejecutar(
        ['cuenta_bancaria_id' => '1', 'configuracion_id' => '999999999', 'inicio_periodo' => '2026-09-01'],
        ['error' => UPLOAD_ERR_OK, 'tmp_name' => __DIR__ . '/fixtures/importacion-valida.csv', 'name' => 'extracto.csv']
    );
    throw new RuntimeException('Se acepto una cuenta sin configuracion.');
} catch (RecursoNoDisponibleException $esperada) {
}

$vista = file_get_contents(dirname(__DIR__) . '/app/html/importaciones.php');
$javascript = file_get_contents(dirname(__DIR__) . '/app/js/shared.js');
$api = file_get_contents(dirname(__DIR__) . '/api.php');
foreach (['data-form-importacion', 'Contrato del archivo', 'data-importacion-configuracion', 'data-descargar-errores'] as $fragmento) {
    comprobarPrevisualizacion(strpos($vista, $fragmento) !== false, 'Falta el contrato visual: ' . $fragmento);
}
foreach (['importacion.previsualizar', 'importacion.reporte-errores', 'new FormData', 'respuesta.blob()', "periodo.value + '-01'", 'Confirmá para crear el lote'] as $fragmento) {
    comprobarPrevisualizacion(strpos($javascript, $fragmento) !== false, 'Falta el contrato cliente: ' . $fragmento);
}
foreach (["'importacion.previsualizar'", "'importacion.reporte-errores'", "'importacion-previsualizar'", 'multipart/form-data', 'responderCsv'] as $fragmento) {
    comprobarPrevisualizacion(strpos($api, $fragmento) !== false, 'Falta la proteccion API: ' . $fragmento);
}

echo "Previsualizacion de importacion de extracto OK\n";
