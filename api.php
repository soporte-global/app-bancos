<?php
$raiz = __DIR__;
require_once $raiz . '/src/config.php';
require_once $raiz . '/src/autoload.php';

use AppBancos\Http\ApiException;
use AppBancos\Application\EjecutorComandoIdempotente;
use AppBancos\Application\AsociarValorMovimientoMensual;
use AppBancos\Application\AsociarAsientoMovimientoMensual;
use AppBancos\Application\CrearBorradorMovimientoMensual;
use AppBancos\Application\CerrarMovimientoMensual;
use AppBancos\Application\CerrarMovimientoMensualSinErp;
use AppBancos\Application\ConciliarCheque;
use AppBancos\Application\ConfirmarImportacionExtracto;
use AppBancos\Application\ClasificadorImportacionExtracto;
use AppBancos\Application\GenerarReporteErroresImportacion;
use AppBancos\Application\BuscarRecursosErpMovimientoMensual;
use AppBancos\Application\GestionarMensajesMovimientoMensual;
use AppBancos\Application\AsignarResponsableMovimientoMensual;
use AppBancos\Application\ActualizarValidacionAutomaticaRegla;
use AppBancos\Application\GuardarReglaClasificacion;
use AppBancos\Application\RetirarReglaClasificacion;
use AppBancos\Application\VincularCuentaConfiguracion;
use AppBancos\Application\DesvincularCuentaConfiguracion;
use AppBancos\Application\GuardarMapeoCuentaContable;
use AppBancos\Application\RetirarMapeoCuentaContable;
use AppBancos\Application\GuardarReglaAsignacion;
use AppBancos\Application\RetirarReglaAsignacion;
use AppBancos\Application\ParserExtractoDelimitado;
use AppBancos\Application\PrevisualizarImportacionExtracto;
use AppBancos\Application\PrepararMovimientoMensual;
use AppBancos\Application\PrevalidarCierreMovimientoMensual;
use AppBancos\Application\PrevalidarConciliacionCheque;
use AppBancos\Application\RevertirPreparacionMovimientoMensual;
use AppBancos\Infrastructure\EsquemaBancos;
use AppBancos\Repository\AuditoriaRepository;
use AppBancos\Repository\BorradorAsientoErpGateway;
use AppBancos\Repository\IdempotenciaRepository;
use AppBancos\Repository\MovimientoMensualRepository;
use AppBancos\Repository\BusquedaRecursosErpRepository;
use AppBancos\Repository\MensajeriaMovimientoRepository;
use AppBancos\Repository\AsignacionMovimientoRepository;
use AppBancos\Repository\ConfiguracionClasificacionRepository;
use AppBancos\Repository\CierreMovimientoRepository;
use AppBancos\Repository\ConciliacionChequeErpGateway;
use AppBancos\Repository\ImportacionExtractoRepository;
use AppBancos\Repository\MapeoCuentaContableRepository;
use AppBancos\Repository\ReglaAsignacionRepository;
use AppBancos\Repository\PreflightCierreMovimientoRepository;
use AppBancos\Repository\PreflightConciliacionChequeRepository;
use AppBancos\Repository\ValorErpGateway;
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

    if ($accion === 'movimiento.prevalidar-cierre') {
        if ($metodo !== 'GET') {
            throw new ApiException(405, 'METODO_NO_PERMITIDO', 'El preflight de cierre solo acepta GET.');
        }
        (new AutorizadorAccion(ID_APLICACION))->exigir($sesion, 'movimiento-cerrar');
        $esquema = EsquemaBancos::desdeConfiguracion(['bancos_debug' => BANCOS_DEBUG]);
        $resultado = (new PrevalidarCierreMovimientoMensual(
            new PreflightCierreMovimientoRepository($conexion, $esquema)
        ))->ejecutar($_GET);
        responderJson(200, [
            'data' => $resultado,
            'meta' => ['solo_lectura' => true],
        ]);
    }

    if ($accion === 'cheque.prevalidar-conciliacion') {
        if ($metodo !== 'GET') {
            throw new ApiException(405, 'METODO_NO_PERMITIDO', 'El preflight de conciliacion de cheque solo acepta GET.');
        }
        (new AutorizadorAccion(ID_APLICACION))->exigir($sesion, 'cheque-conciliar');
        $esquema = EsquemaBancos::desdeConfiguracion(['bancos_debug' => BANCOS_DEBUG]);
        $resultado = (new PrevalidarConciliacionCheque(
            new PreflightConciliacionChequeRepository($conexion, $esquema)
        ))->ejecutar($_GET);
        responderJson(200, [
            'data' => $resultado,
            'meta' => ['solo_lectura' => true, 'persistida' => false],
        ]);
    }

    if ($accion === 'cheque.conciliar') {
        if ($metodo !== 'POST') {
            throw new ApiException(405, 'METODO_NO_PERMITIDO', 'Conciliar un cheque solo acepta POST.');
        }
        if (!BANCOS_DEBUG) {
            throw new ApiException(503, 'ESCRITURA_NO_HABILITADA', 'La conciliacion solo esta habilitada en el sandbox.');
        }
        $tipoContenido = strtolower(trim((string) ($_SERVER['CONTENT_TYPE'] ?? '')));
        if (strpos($tipoContenido, 'application/json') !== 0) {
            throw new ApiException(415, 'CONTENIDO_NO_ADMITIDO', 'La solicitud debe usar application/json.');
        }
        $entrada = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($entrada) || json_last_error() !== JSON_ERROR_NONE) {
            throw new ApiException(400, 'JSON_INVALIDO', 'El cuerpo JSON es invalido.');
        }
        (new ProteccionCsrf())->validar($_SESSION, $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        (new AutorizadorAccion(ID_APLICACION))->exigir($sesion, 'cheque-conciliar');
        $claveIdempotencia = trim((string) ($_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? ''));
        $esquema = EsquemaBancos::desdeConfiguracion(['bancos_debug' => BANCOS_DEBUG]);
        $resultado = (new ConciliarCheque(
            new CierreMovimientoRepository($conexion, $esquema),
            new PreflightConciliacionChequeRepository($conexion, $esquema),
            new ConciliacionChequeErpGateway($conexion, $esquema),
            new EjecutorComandoIdempotente(
                $conexion,
                new IdempotenciaRepository($conexion, $esquema),
                new AuditoriaRepository($conexion, $esquema)
            )
        ))->ejecutar($entrada, $sesion->cuenta()->id(), $claveIdempotencia);
        responderJson($resultado['codigo_http'], [
            'data' => $resultado['respuesta'],
            'meta' => ['repetida' => $resultado['repetida'], 'sandbox' => true],
        ]);
    }

    if ($accion === 'movimiento.cerrar-sin-erp') {
        if ($metodo !== 'POST') {
            throw new ApiException(405, 'METODO_NO_PERMITIDO', 'Cerrar un movimiento solo acepta POST.');
        }
        if (!BANCOS_DEBUG) {
            throw new ApiException(
                503,
                'ESCRITURA_NO_HABILITADA',
                'El cierre de movimientos solo esta habilitado en el sandbox.'
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
        (new ProteccionCsrf())->validar($_SESSION, $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        (new AutorizadorAccion(ID_APLICACION))->exigir($sesion, 'movimiento-cerrar');
        $claveIdempotencia = trim((string) ($_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? ''));
        $esquema = EsquemaBancos::desdeConfiguracion(['bancos_debug' => BANCOS_DEBUG]);
        $resultado = (new CerrarMovimientoMensualSinErp(
            new CierreMovimientoRepository($conexion, $esquema),
            new PreflightCierreMovimientoRepository($conexion, $esquema),
            new EjecutorComandoIdempotente(
                $conexion,
                new IdempotenciaRepository($conexion, $esquema),
                new AuditoriaRepository($conexion, $esquema)
            )
        ))->ejecutar($entrada, $sesion->cuenta()->id(), $claveIdempotencia);
        responderJson($resultado['codigo_http'], [
            'data' => $resultado['respuesta'],
            'meta' => ['repetida' => $resultado['repetida'], 'efectos_erp' => 0],
        ]);
    }

    if ($accion === 'movimiento.cerrar') {
        if ($metodo !== 'POST') {
            throw new ApiException(405, 'METODO_NO_PERMITIDO', 'Cerrar un movimiento solo acepta POST.');
        }
        if (!BANCOS_DEBUG) {
            throw new ApiException(
                503,
                'ESCRITURA_NO_HABILITADA',
                'El cierre de movimientos solo esta habilitado en el sandbox.'
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
        (new ProteccionCsrf())->validar($_SESSION, $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        (new AutorizadorAccion(ID_APLICACION))->exigir($sesion, 'movimiento-cerrar');
        $claveIdempotencia = trim((string) ($_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? ''));
        $esquema = EsquemaBancos::desdeConfiguracion(['bancos_debug' => BANCOS_DEBUG]);
        $resultado = (new CerrarMovimientoMensual(
            new CierreMovimientoRepository($conexion, $esquema),
            new PreflightCierreMovimientoRepository($conexion, $esquema),
            new ValorErpGateway($conexion, $esquema),
            new BorradorAsientoErpGateway($conexion, $esquema),
            new EjecutorComandoIdempotente(
                $conexion,
                new IdempotenciaRepository($conexion, $esquema),
                new AuditoriaRepository($conexion, $esquema)
            )
        ))->ejecutar($entrada, $sesion->cuenta()->id(), $claveIdempotencia);
        responderJson($resultado['codigo_http'], [
            'data' => $resultado['respuesta'],
            'meta' => [
                'repetida' => $resultado['repetida'],
                'efectos_erp' => $resultado['respuesta']['efectos_erp'],
            ],
        ]);
    }

    if (in_array($accion, [
        'erp.buscar-valores',
        'erp.buscar-asientos',
        'erp.buscar-nodos',
        'erp.buscar-cuentas',
    ], true)) {
        if ($metodo !== 'GET') {
            throw new ApiException(405, 'METODO_NO_PERMITIDO', 'La busqueda ERP solo acepta GET.');
        }
        if ($accion === 'erp.buscar-valores') {
            $permiso = 'movimiento-asociar-valor';
        } elseif ($accion === 'erp.buscar-asientos') {
            $permiso = 'movimiento-asociar-asiento';
        } else {
            $permiso = 'movimiento-crear-borrador';
        }
        (new AutorizadorAccion(ID_APLICACION))->exigir($sesion, $permiso);
        $esquema = EsquemaBancos::desdeConfiguracion(['bancos_debug' => BANCOS_DEBUG]);
        $busqueda = new BuscarRecursosErpMovimientoMensual(
            new BusquedaRecursosErpRepository($conexion, $esquema)
        );
        if ($accion === 'erp.buscar-valores') {
            $resultado = $busqueda->buscarValores($_GET);
        } elseif ($accion === 'erp.buscar-asientos') {
            $resultado = $busqueda->buscarAsientos($_GET);
        } elseif ($accion === 'erp.buscar-nodos') {
            $resultado = $busqueda->buscarNodos($_GET);
        } else {
            $resultado = $busqueda->buscarCuentas($_GET);
        }
        responderJson(200, [
            'data' => $resultado['resultados'],
            'meta' => array_diff_key($resultado, ['resultados' => true]),
        ]);
    }

    if ($accion === 'importacion.previsualizar') {
        if ($metodo !== 'POST') {
            throw new ApiException(405, 'METODO_NO_PERMITIDO', 'Previsualizar una importacion solo acepta POST.');
        }
        if (!BANCOS_DEBUG) {
            throw new ApiException(
                503,
                'ESCRITURA_NO_HABILITADA',
                'El flujo de importacion solo esta habilitado en el sandbox.'
            );
        }
        $tipoContenido = strtolower(trim((string) ($_SERVER['CONTENT_TYPE'] ?? '')));
        if (strpos($tipoContenido, 'multipart/form-data') !== 0) {
            throw new ApiException(415, 'CONTENIDO_NO_ADMITIDO', 'La solicitud debe usar multipart/form-data.');
        }
        (new ProteccionCsrf())->validar($_SESSION, $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        (new AutorizadorAccion(ID_APLICACION))->exigir($sesion, 'importacion-previsualizar');
        $esquema = EsquemaBancos::desdeConfiguracion(['bancos_debug' => BANCOS_DEBUG]);
        $repositorioImportacion = new ImportacionExtractoRepository($conexion, $esquema);
        $resultado = (new PrevisualizarImportacionExtracto(
            new ParserExtractoDelimitado(),
            $repositorioImportacion,
            new ClasificadorImportacionExtracto()
        ))->ejecutar($_POST, isset($_FILES['archivo']) && is_array($_FILES['archivo']) ? $_FILES['archivo'] : []);
        responderJson(200, ['data' => $resultado, 'meta' => ['persistida' => false]]);
    }

    if ($accion === 'importacion.reporte-errores') {
        if ($metodo !== 'POST') {
            throw new ApiException(405, 'METODO_NO_PERMITIDO', 'Generar el reporte de errores solo acepta POST.');
        }
        if (!BANCOS_DEBUG) {
            throw new ApiException(
                503,
                'ESCRITURA_NO_HABILITADA',
                'El flujo de importacion solo esta habilitado en el sandbox.'
            );
        }
        $tipoContenido = strtolower(trim((string) ($_SERVER['CONTENT_TYPE'] ?? '')));
        if (strpos($tipoContenido, 'multipart/form-data') !== 0) {
            throw new ApiException(415, 'CONTENIDO_NO_ADMITIDO', 'La solicitud debe usar multipart/form-data.');
        }
        (new ProteccionCsrf())->validar($_SESSION, $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        (new AutorizadorAccion(ID_APLICACION))->exigir($sesion, 'importacion-previsualizar');
        $esquema = EsquemaBancos::desdeConfiguracion(['bancos_debug' => BANCOS_DEBUG]);
        $repositorioImportacion = new ImportacionExtractoRepository($conexion, $esquema);
        $previsualizacion = new PrevisualizarImportacionExtracto(
            new ParserExtractoDelimitado(),
            $repositorioImportacion,
            new ClasificadorImportacionExtracto()
        );
        $resultado = (new GenerarReporteErroresImportacion($previsualizacion))->ejecutar(
            $_POST,
            isset($_FILES['archivo']) && is_array($_FILES['archivo']) ? $_FILES['archivo'] : []
        );
        responderCsv($resultado['nombre_archivo'], $resultado['contenido']);
    }

    if ($accion === 'importacion.confirmar') {
        if ($metodo !== 'POST') {
            throw new ApiException(405, 'METODO_NO_PERMITIDO', 'Confirmar una importacion solo acepta POST.');
        }
        if (!BANCOS_DEBUG) {
            throw new ApiException(
                503,
                'ESCRITURA_NO_HABILITADA',
                'El flujo de importacion solo esta habilitado en el sandbox.'
            );
        }
        $tipoContenido = strtolower(trim((string) ($_SERVER['CONTENT_TYPE'] ?? '')));
        if (strpos($tipoContenido, 'multipart/form-data') !== 0) {
            throw new ApiException(415, 'CONTENIDO_NO_ADMITIDO', 'La solicitud debe usar multipart/form-data.');
        }
        (new ProteccionCsrf())->validar($_SESSION, $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        (new AutorizadorAccion(ID_APLICACION))->exigir($sesion, 'importacion-confirmar');
        $claveIdempotencia = trim((string) ($_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? ''));
        $esquema = EsquemaBancos::desdeConfiguracion(['bancos_debug' => BANCOS_DEBUG]);
        $repositorioImportacion = new ImportacionExtractoRepository($conexion, $esquema);
        $resultado = (new ConfirmarImportacionExtracto(
            new ParserExtractoDelimitado(),
            $repositorioImportacion,
            new ClasificadorImportacionExtracto(),
            new EjecutorComandoIdempotente(
                $conexion,
                new IdempotenciaRepository($conexion, $esquema),
                new AuditoriaRepository($conexion, $esquema)
            )
        ))->ejecutar(
            $_POST,
            isset($_FILES['archivo']) && is_array($_FILES['archivo']) ? $_FILES['archivo'] : [],
            $sesion->cuenta()->id(),
            $claveIdempotencia
        );
        responderJson($resultado['codigo_http'], [
            'data' => $resultado['respuesta'],
            'meta' => ['repetida' => $resultado['repetida']],
        ]);
    }

    if ($accion === 'configuracion.actualizar-validacion-automatica') {
        if ($metodo !== 'POST') {
            throw new ApiException(405, 'METODO_NO_PERMITIDO', 'Actualizar una regla solo acepta POST.');
        }
        if (!BANCOS_DEBUG) {
            throw new ApiException(
                503,
                'ESCRITURA_NO_HABILITADA',
                'El mantenimiento de configuraciones solo esta habilitado en el sandbox.'
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
        (new ProteccionCsrf())->validar($_SESSION, $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        (new AutorizadorAccion(ID_APLICACION))->exigir(
            $sesion,
            'configuracion-actualizar-validacion-automatica'
        );
        $claveIdempotencia = trim((string) ($_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? ''));
        $esquema = EsquemaBancos::desdeConfiguracion(['bancos_debug' => BANCOS_DEBUG]);
        $resultado = (new ActualizarValidacionAutomaticaRegla(
            new ConfiguracionClasificacionRepository($conexion, $esquema),
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

    if (in_array($accion, ['configuracion.guardar-regla', 'configuracion.retirar-regla'], true)) {
        if ($metodo !== 'POST') {
            throw new ApiException(405, 'METODO_NO_PERMITIDO', 'Administrar reglas solo acepta POST.');
        }
        if (!BANCOS_DEBUG) {
            throw new ApiException(
                503,
                'ESCRITURA_NO_HABILITADA',
                'El mantenimiento de configuraciones solo esta habilitado en el sandbox.'
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
        (new ProteccionCsrf())->validar($_SESSION, $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        (new AutorizadorAccion(ID_APLICACION))->exigir($sesion, 'configuracion-administrar-reglas');
        $claveIdempotencia = trim((string) ($_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? ''));
        $esquema = EsquemaBancos::desdeConfiguracion(['bancos_debug' => BANCOS_DEBUG]);
        $repositorio = new ConfiguracionClasificacionRepository($conexion, $esquema);
        $ejecutor = new EjecutorComandoIdempotente(
            $conexion,
            new IdempotenciaRepository($conexion, $esquema),
            new AuditoriaRepository($conexion, $esquema)
        );
        if ($accion === 'configuracion.guardar-regla') {
            $casoDeUso = new GuardarReglaClasificacion($repositorio, $ejecutor);
        } else {
            $casoDeUso = new RetirarReglaClasificacion($repositorio, $ejecutor);
        }
        $resultado = $casoDeUso->ejecutar(
            $entrada,
            $sesion->cuenta()->id(),
            $claveIdempotencia
        );
        responderJson($resultado['codigo_http'], [
            'data' => $resultado['respuesta'],
            'meta' => ['repetida' => $resultado['repetida']],
        ]);
    }

    if (in_array($accion, ['configuracion.vincular-cuenta', 'configuracion.desvincular-cuenta'], true)) {
        if ($metodo !== 'POST') {
            throw new ApiException(405, 'METODO_NO_PERMITIDO', 'Administrar cuentas de una configuracion solo acepta POST.');
        }
        if (!BANCOS_DEBUG) {
            throw new ApiException(
                503,
                'ESCRITURA_NO_HABILITADA',
                'El mantenimiento de configuraciones solo esta habilitado en el sandbox.'
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
        (new ProteccionCsrf())->validar($_SESSION, $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        (new AutorizadorAccion(ID_APLICACION))->exigir($sesion, 'configuracion-administrar-cuentas');
        $claveIdempotencia = trim((string) ($_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? ''));
        $esquema = EsquemaBancos::desdeConfiguracion(['bancos_debug' => BANCOS_DEBUG]);
        $repositorio = new ConfiguracionClasificacionRepository($conexion, $esquema);
        $ejecutor = new EjecutorComandoIdempotente(
            $conexion,
            new IdempotenciaRepository($conexion, $esquema),
            new AuditoriaRepository($conexion, $esquema)
        );
        $casoDeUso = $accion === 'configuracion.vincular-cuenta'
            ? new VincularCuentaConfiguracion($repositorio, $ejecutor)
            : new DesvincularCuentaConfiguracion($repositorio, $ejecutor);
        $resultado = $casoDeUso->ejecutar($entrada, $sesion->cuenta()->id(), $claveIdempotencia);
        responderJson($resultado['codigo_http'], [
            'data' => $resultado['respuesta'],
            'meta' => ['repetida' => $resultado['repetida']],
        ]);
    }

    if (in_array($accion, ['configuracion.guardar-mapeo', 'configuracion.retirar-mapeo'], true)) {
        if ($metodo !== 'POST') {
            throw new ApiException(405, 'METODO_NO_PERMITIDO', 'Administrar mapeos contables solo acepta POST.');
        }
        if (!BANCOS_DEBUG) {
            throw new ApiException(503, 'ESCRITURA_NO_HABILITADA', 'El mantenimiento de configuraciones solo esta habilitado en el sandbox.');
        }
        $tipoContenido = strtolower(trim((string) ($_SERVER['CONTENT_TYPE'] ?? '')));
        if (strpos($tipoContenido, 'application/json') !== 0) {
            throw new ApiException(415, 'CONTENIDO_NO_ADMITIDO', 'La solicitud debe usar application/json.');
        }
        $entrada = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($entrada) || json_last_error() !== JSON_ERROR_NONE) {
            throw new ApiException(400, 'JSON_INVALIDO', 'El cuerpo JSON es invalido.');
        }
        (new ProteccionCsrf())->validar($_SESSION, $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        (new AutorizadorAccion(ID_APLICACION))->exigir($sesion, 'configuracion-administrar-mapeos');
        $claveIdempotencia = trim((string) ($_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? ''));
        $esquema = EsquemaBancos::desdeConfiguracion(['bancos_debug' => BANCOS_DEBUG]);
        $repositorio = new MapeoCuentaContableRepository($conexion, $esquema);
        $ejecutor = new EjecutorComandoIdempotente($conexion, new IdempotenciaRepository($conexion, $esquema), new AuditoriaRepository($conexion, $esquema));
        $casoDeUso = $accion === 'configuracion.guardar-mapeo'
            ? new GuardarMapeoCuentaContable($repositorio, $ejecutor)
            : new RetirarMapeoCuentaContable($repositorio, $ejecutor);
        $resultado = $casoDeUso->ejecutar($entrada, $sesion->cuenta()->id(), $claveIdempotencia);
        responderJson($resultado['codigo_http'], ['data' => $resultado['respuesta'], 'meta' => ['repetida' => $resultado['repetida']]]);
    }

    if (in_array($accion, ['configuracion.guardar-asignacion', 'configuracion.retirar-asignacion'], true)) {
        if ($metodo !== 'POST') { throw new ApiException(405, 'METODO_NO_PERMITIDO', 'Administrar reglas de asignacion solo acepta POST.'); }
        if (!BANCOS_DEBUG) { throw new ApiException(503, 'ESCRITURA_NO_HABILITADA', 'El mantenimiento de configuraciones solo esta habilitado en el sandbox.'); }
        $tipoContenido=strtolower(trim((string)($_SERVER['CONTENT_TYPE']??'')));
        if(strpos($tipoContenido,'application/json')!==0){throw new ApiException(415,'CONTENIDO_NO_ADMITIDO','La solicitud debe usar application/json.');}
        $entrada=json_decode((string)file_get_contents('php://input'),true);
        if(!is_array($entrada)||json_last_error()!==JSON_ERROR_NONE){throw new ApiException(400,'JSON_INVALIDO','El cuerpo JSON es invalido.');}
        (new ProteccionCsrf())->validar($_SESSION,$_SERVER['HTTP_X_CSRF_TOKEN']??'');
        (new AutorizadorAccion(ID_APLICACION))->exigir($sesion,'configuracion-administrar-asignaciones');
        $claveIdempotencia=trim((string)($_SERVER['HTTP_IDEMPOTENCY_KEY']??''));
        $esquema=EsquemaBancos::desdeConfiguracion(['bancos_debug'=>BANCOS_DEBUG]);
        $repositorio=new ReglaAsignacionRepository($conexion,$esquema);
        $ejecutor=new EjecutorComandoIdempotente($conexion,new IdempotenciaRepository($conexion,$esquema),new AuditoriaRepository($conexion,$esquema));
        $casoDeUso=$accion==='configuracion.guardar-asignacion'?new GuardarReglaAsignacion($repositorio,$ejecutor):new RetirarReglaAsignacion($repositorio,$ejecutor);
        $resultado=$casoDeUso->ejecutar($entrada,$sesion->cuenta()->id(),$claveIdempotencia);
        responderJson($resultado['codigo_http'],['data'=>$resultado['respuesta'],'meta'=>['repetida'=>$resultado['repetida']]]);
    }

    if ($accion === 'movimiento.asignar-responsable') {
        if ($metodo !== 'POST') {
            throw new ApiException(405, 'METODO_NO_PERMITIDO', 'Asignar responsable solo acepta POST.');
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
        (new ProteccionCsrf())->validar($_SESSION, $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        (new AutorizadorAccion(ID_APLICACION))->exigir($sesion, 'movimiento-asignar-responsable');
        $claveIdempotencia = trim((string) ($_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? ''));
        $esquema = EsquemaBancos::desdeConfiguracion(['bancos_debug' => BANCOS_DEBUG]);
        $resultado = (new AsignarResponsableMovimientoMensual(
            new AsignacionMovimientoRepository($conexion, $esquema),
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

    if (in_array($accion, [
        'movimiento.agregar-mensaje',
        'movimiento.marcar-mensajes-leidos',
    ], true)) {
        if ($metodo !== 'POST') {
            throw new ApiException(405, 'METODO_NO_PERMITIDO', 'La mensajeria solo acepta POST.');
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

        (new ProteccionCsrf())->validar($_SESSION, $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        $permiso = $accion === 'movimiento.agregar-mensaje'
            ? 'movimiento-agregar-mensaje'
            : 'movimiento-marcar-mensajes-leidos';
        (new AutorizadorAccion(ID_APLICACION))->exigir($sesion, $permiso);
        $claveIdempotencia = trim((string) ($_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? ''));
        $esquema = EsquemaBancos::desdeConfiguracion(['bancos_debug' => BANCOS_DEBUG]);
        $mensajeria = new GestionarMensajesMovimientoMensual(
            new MensajeriaMovimientoRepository($conexion, $esquema),
            new EjecutorComandoIdempotente(
                $conexion,
                new IdempotenciaRepository($conexion, $esquema),
                new AuditoriaRepository($conexion, $esquema)
            )
        );
        $resultado = $accion === 'movimiento.agregar-mensaje'
            ? $mensajeria->agregar($entrada, $sesion->cuenta()->id(), $claveIdempotencia)
            : $mensajeria->marcarLeidos($entrada, $sesion->cuenta()->id(), $claveIdempotencia);

        responderJson($resultado['codigo_http'], [
            'data' => $resultado['respuesta'],
            'meta' => ['repetida' => $resultado['repetida']],
        ]);
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

    if ($accion === 'movimiento.asociar-valor') {
        if ($metodo !== 'POST') {
            throw new ApiException(405, 'METODO_NO_PERMITIDO', 'Asociar un valor solo acepta POST.');
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

        (new ProteccionCsrf())->validar($_SESSION, $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        (new AutorizadorAccion(ID_APLICACION))->exigir($sesion, 'movimiento-asociar-valor');
        $claveIdempotencia = trim((string) ($_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? ''));
        $esquema = EsquemaBancos::desdeConfiguracion(['bancos_debug' => BANCOS_DEBUG]);
        $resultado = (new AsociarValorMovimientoMensual(
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

    if ($accion === 'movimiento.asociar-asiento') {
        if ($metodo !== 'POST') {
            throw new ApiException(405, 'METODO_NO_PERMITIDO', 'Asociar un asiento solo acepta POST.');
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

        (new ProteccionCsrf())->validar($_SESSION, $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        (new AutorizadorAccion(ID_APLICACION))->exigir($sesion, 'movimiento-asociar-asiento');
        $claveIdempotencia = trim((string) ($_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? ''));
        $esquema = EsquemaBancos::desdeConfiguracion(['bancos_debug' => BANCOS_DEBUG]);
        $resultado = (new AsociarAsientoMovimientoMensual(
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

    if ($accion === 'movimiento.crear-borrador') {
        if ($metodo !== 'POST') {
            throw new ApiException(405, 'METODO_NO_PERMITIDO', 'Crear un borrador solo acepta POST.');
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

        (new ProteccionCsrf())->validar($_SESSION, $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        (new AutorizadorAccion(ID_APLICACION))->exigir($sesion, 'movimiento-crear-borrador');
        $claveIdempotencia = trim((string) ($_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? ''));
        $esquema = EsquemaBancos::desdeConfiguracion(['bancos_debug' => BANCOS_DEBUG]);
        $resultado = (new CrearBorradorMovimientoMensual(
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
    if ($error instanceof AppBancos\Application\RecursoNoDisponibleException) {
        responderError(409, 'RECURSO_NO_DISPONIBLE', $error->getMessage());
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

function responderCsv($nombreArchivo, $contenido)
{
    $nombreArchivo = preg_replace('/[^A-Za-z0-9._-]+/', '-', basename((string) $nombreArchivo));
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
    header('Content-Length: ' . strlen((string) $contenido));
    http_response_code(200);
    echo $contenido;
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
