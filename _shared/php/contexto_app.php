<?php
if (!defined('RUTA')) {
    http_response_code(403);
    exit;
}

function crear_contexto_app(
    GlobalApps\Core\Identidad\Sesion $sesion = null,
    $pagina = 'home',
    $dataEnviar = null
)
{
    return [
        'sesion' => $sesion === null
            ? null
            : GlobalApps\Core\Identidad\SesionWeb::crear(
                $sesion,
                ID_APLICACION,
                PERMISOS_TODAS_LAS_APPS
            ),
        'app' => [
            'id' => ID_APLICACION,
            'nombre' => NOMBRE,
            'pagina' => (string) $pagina,
            'ruta' => RUTA_WEB,
        ],
        'data' => $dataEnviar,
    ];
}

function validar_contexto_app($contextoApp)
{
    $dataValida = is_array($contextoApp)
        && isset($contextoApp['data'])
        && is_object($contextoApp['data']);
    if (
        !is_array($contextoApp)
        || !array_key_exists('sesion', $contextoApp)
        || !isset($contextoApp['app'])
        || !is_array($contextoApp['app'])
        || !$dataValida
    ) {
        throw new RuntimeException('El contexto canonico debe contener sesion, app y data como objeto.');
    }
}
