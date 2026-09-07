<?php

if (!defined('RUTA')) {
    define('RUTA', dirname(__DIR__));
}
require_once RUTA . '/src/autoload.php';

use AppBancos\Infrastructure\EsquemaBancos;

function comprobarEsquema($condicion, $mensaje)
{
    if (!$condicion) {
        throw new RuntimeException($mensaje);
    }
}

$debug = EsquemaBancos::desdeConfiguracion(['bancos_debug' => true]);
comprobarEsquema($debug->esDebug(), 'El perfil debug no quedó habilitado.');
comprobarEsquema($debug->tablaBancos('bancos_movimiento_extracto') === 'global_temp.bancos_movimiento_extracto', 'Debug no usa global_temp para BANCOS.');
comprobarEsquema($debug->tablaLecturaErp('valor') === 'public.valor', 'Debug debe leer el ERP desde public.');
comprobarEsquema($debug->tablaEscrituraErp('valor') === 'global_temp.valor', 'Debug no redirige las escrituras ERP a global_temp.');

$produccion = EsquemaBancos::desdeConfiguracion(['bancos_debug' => false]);
comprobarEsquema(!$produccion->esDebug(), 'El perfil de producción quedó en debug.');
comprobarEsquema($produccion->tablaBancos('bancos_movimiento_extracto') === 'global_prod.bancos_movimiento_extracto', 'Producción no usa global_prod para BANCOS.');
comprobarEsquema($produccion->tablaLecturaErp('valor') === 'public.valor', 'Producción no lee el ERP desde public.');
comprobarEsquema($produccion->tablaEscrituraErp('valor') === 'public.valor', 'Producción no escribe el ERP en public.');

try {
    $debug->tablaBancos('valor');
    throw new RuntimeException('Se aceptó una tabla no operativa como tabla BANCOS.');
} catch (InvalidArgumentException $error) {
}

try {
    $debug->tablaEscrituraErp('valor; drop table public.valor');
    throw new RuntimeException('Se aceptó un identificador SQL inválido.');
} catch (InvalidArgumentException $error) {
}

echo "Esquema BANCOS OK\n";
