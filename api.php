<?php
$raiz = __DIR__;
require_once $raiz . '/src/config.php';
require_once $raiz . '/src/autoload.php';

use AppBancos\Http\ApiException;
use AppBancos\Application\EjecutorComandoIdempotente;
use AppBancos\Application\PrepararMovimientoMensual;
use AppBancos\Application\RevertirPreparacionMovimientoMensual;
use AppBancos\Infrastructure\EsquemaBancos;
use AppBancos\Repository\AuditoriaRepository;
use AppBancos\Repository\IdempotenciaRepository;
use AppBancos\Repository\MovimientoMensualRepository;
use AppBancos\Security\AutorizadorAccion;
use AppBancos\Security\ProteccionCsrf;
use GlobalApps\Core\Acceso\PoliticaAcceso;
use GlobalApps\Core\Identidad\Autenticador;
use GlobalApps\Core\Identidad\SesionCache;
use GlobalApps\Core\Infrastructure\Persistence\PdoProvider;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

try {
    $metodo = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    $accion = trim((string) ($_GET['accion'] ?? ''));
    if ($accion === '' || !preg_match('/^[a-z][a-z0-9._-]{1,79}$/', $accion)) {
        throw new ApiException(400, 'ACCION_INVALIDA', 'La accion solicitada es invalida.');
    }

    $cuentaId = isset($_SESSION[AUTH_SESSION_KEY]['cuenta_id'])
        ? (int) $_SESSION[AUTH_SESSION_KEY]['cuenta_id']
        : 0;
    $venceAt = (int) ($_SESSION[AUTH_SESSION_KEY]['vence_at'] ?? 0);
    if ($cuentaId <= 0 || $venceAt <= time()) {
        unset($_SESSION[AUTH_SESSION_KEY], $_SESSION[FolderName]['sesion_cache']);
        throw new ApiException(401, 'SESION_REQUERIDA', 'La sesion no esta autenticada.');
    }

    $proveedor = new PdoProvider([
        'ftweb' => [
            'dsn' => 'pgsql:host=' . HOST . ';port=' . PORT . ';dbname=' . DBASE,
            'user' => USER,
            'password' => PASS,
            'options' => [PDO::ATTR_PERSISTENT => FTWEB_PERSISTENT],
        ],
    ]);
    $conexion = $proveedor->ftweb();
    $cache = new SesionCache(FolderName, AUTH_CACHE_TTL);
    $sesion = $cache->obtener();
    if ($sesion === null || $sesion->cuenta()->id() !== $cuentaId) {
        $sesion = (new Autenticador($conexion))->restaurar($cuentaId);
        $cache->guardar($sesion);
    }

    (new PoliticaAcceso(ID_APLICACION, [
        'libre' => FREE_FOR_ALL,
        'requiere_empleado' => REQUIERE_EMPLEADO,
        'requiere_zweb' => REQUIERE_ZWEB_USER,
        'requiere_cliente' => REQUIERE_CLIENTE,
    ]))->validar($sesion);

    if ($metodo === 'GET' && $accion === 'csrf') {
        $token = (new ProteccionCsrf())->obtenerToken($_SESSION);
        responderJson(200, [
            'data' => ['csrf_token' => $token],
            'meta' => ['cuenta_id' => $sesion->cuenta()->id()],
        ]);
    }
    if ($accion === 'csrf') {
        throw new ApiException(405, 'METODO_NO_PERMITIDO', 'La accion csrf solo acepta GET.');
    }

    if ($accion === 'movimiento.preparar') {
        if ($metodo !== 'POST') {
            throw new ApiException(405, 'METODO_NO_PERMITIDO', 'Preparar un movimiento solo acepta POST.');
        }
        if (!BANCOS_DEBUG) {
            throw new ApiException(
                503,
                'ESCRITURA_NO_HABILITADA',
                'Las escrituras funcionales solo estan habilitadas en el sandbox.'
            );
        }
        $tipoContenido = strtolower(trim((string) ($_SERVER['CONTENT_TYPE'] ?? '')));
        if (strpos($tipoContenido, 'application/json') !== 0) {
            throw new ApiException(415, 'CONTENIDO_NO_ADMITIDO', 'La solicitud debe usar application/json.');
        }
        $cuerpo = file_get_contents('php://input');
        $entrada = json_decode((string) $cuerpo, true);
        if (!is_array($entrada) || json_last_error() !== JSON_ERROR_NONE) {
            throw new ApiException(400, 'JSON_INVALIDO', 'El cuerpo JSON es invalido.');
        }

        $csrf = new ProteccionCsrf();
        $csrf->validar($_SESSION, $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        (new AutorizadorAccion(ID_APLICACION))->exigir($sesion, 'movimiento-preparar');
        $claveIdempotencia = trim((string) ($_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? ''));
        $esquema = EsquemaBancos::desdeConfiguracion(['bancos_debug' => BANCOS_DEBUG]);
        $resultado = (new PrepararMovimientoMensual(
            new MovimientoMensualRepository($conexion, $esquema),
            new EjecutorComandoIdempotente(
                $conexion,
                new IdempotenciaRepository($conexion, $esquema),
                new AuditoriaRepository($conexion, $esquema)
            )
        ))->ejecutar($entrada, $sesion->cuenta()->id(), $claveIdempotencia);

        responderJson($resultado['codigo_http'], [
            'data' => $resultado['respuesta'],
            'meta' => ['repetida' => $resultado['repetida']],
        ]);
    }

    if ($accion === 'movimiento.revertir-preparacion') {
        if ($metodo !== 'POST') {
            throw new ApiException(405, 'METODO_NO_PERMITIDO', 'Revertir la preparacion solo acepta POST.');
        }
        if (!BANCOS_DEBUG) {
            throw new ApiException(
                503,
                'ESCRITURA_NO_HABILITADA',
                'Las escrituras funcionales solo estan habilitadas en el sandbox.'
            );
        }
        $tipoContenido = strtolower(trim((string) ($_SERVER['CONTENT_TYPE'] ?? '')));
        if (strpos($tipoContenido, 'application/json') !== 0) {
            throw new ApiException(415, 'CONTENIDO_NO_ADMITIDO', 'La solicitud debe usar application/json.');
        }
        $cuerpo = file_get_contents('php://input');
        $entrada = json_decode((string) $cuerpo, true);
        if (!is_array($entrada) || json_last_error() !== JSON_ERROR_NONE) {
            throw new ApiException(400, 'JSON_INVALIDO', 'El cuerpo JSON es invalido.');
        }

        $csrf = new ProteccionCsrf();
        $csrf->validar($_SESSION, $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        (new AutorizadorAccion(ID_APLICACION))->exigir($sesion, 'movimiento-revertir-preparacion');
        $claveIdempotencia = trim((string) ($_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? ''));
        $esquema = EsquemaBancos::desdeConfiguracion(['bancos_debug' => BANCOS_DEBUG]);
        $resultado = (new RevertirPreparacionMovimientoMensual(
            new MovimientoMensualRepository($conexion, $esquema),
            new EjecutorComandoIdempotente(
                $conexion,
                new IdempotenciaRepository($conexion, $esquema),
                new AuditoriaRepository($conexion, $esquema)
            )
        ))->ejecutar($entrada, $sesion->cuenta()->id(), $claveIdempotencia);

        responderJson($resultado['codigo_http'], [
            'data' => $resultado['respuesta'],
            'meta' => ['repetida' => $resultado['repetida']],
        ]);
    }

    // Las mutaciones se agregan aquí por nombre cerrado. Cada handler deberá
    // exigir permiso interno, X-CSRF-Token e Idempotency-Key antes del comando.
    throw new ApiException(404, 'ACCION_NO_ENCONTRADA', 'La accion solicitada no existe.');
} catch (Throwable $error) {
    if ($error instanceof ApiException) {
        responderError($error->estadoHttp(), $error->codigoApi(), $error->getMessage());
    }
    if ($error instanceof AppBancos\Application\ConflictoIdempotenciaException) {
        responderError(409, 'CONFLICTO_IDEMPOTENCIA', $error->getMessage());
    }
    if ($error instanceof AppBancos\Application\TransicionMovimientoException) {
        responderError(409, 'TRANSICION_INVALIDA', $error->getMessage());
    }
    if ($error instanceof AppBancos\Application\MovimientoNoEncontradoException) {
        responderError(404, 'MOVIMIENTO_NO_ENCONTRADO', $error->getMessage());
    }
    if ($error instanceof InvalidArgumentException) {
        responderError(422, 'ENTRADA_INVALIDA', $error->getMessage());
    }
    if ($error instanceof GlobalApps\Core\Identidad\AutenticacionException) {
        unset($_SESSION[AUTH_SESSION_KEY], $_SESSION[FolderName]['sesion_cache']);
        responderError(401, 'SESION_INVALIDA', 'La sesion no pudo restaurarse.');
    }
    if ($error instanceof GlobalApps\Core\Acceso\AccesoDenegadoException) {
        responderError(403, 'ACCESO_DENEGADO', 'La cuenta no tiene acceso a la aplicacion.');
    }
    error_log(get_class($error) . ': ' . $error->getMessage());
    responderError(500, 'ERROR_INTERNO', 'No se pudo completar la solicitud.');
}

function responderJson($estado, array $contenido)
{
    http_response_code((int) $estado);
    echo json_encode($contenido, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function responderError($estado, $codigo, $mensaje)
{
    responderJson($estado, [
        'error' => [
            'codigo' => (string) $codigo,
            'mensaje' => (string) $mensaje,
        ],
    ]);
}
