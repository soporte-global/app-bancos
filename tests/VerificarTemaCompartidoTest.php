<?php

require_once dirname(__DIR__) . '/_shared/php/temas.php';

$configApp = file_get_contents(dirname(__DIR__) . '/app/config.php');
$configCore = file_get_contents(dirname(__DIR__) . '/src/config.php');
$head = file_get_contents(dirname(__DIR__) . '/_shared/html/html-head.html');
$encabezado = file_get_contents(dirname(__DIR__) . '/_shared/html/header.php');
$headerScript = file_get_contents(dirname(__DIR__) . '/_shared/js/header.js');

$raizTemporal = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'bancos-temas-' . bin2hex(random_bytes(6));
$directorioTemas = $raizTemporal . DIRECTORY_SEPARATOR . 'estilos';
$temaClaro = $directorioTemas . DIRECTORY_SEPARATOR . 'dia.css';
$temaOscuro = $directorioTemas . DIRECTORY_SEPARATOR . 'noche.css';

if (!mkdir($directorioTemas, 0770, true) && !is_dir($directorioTemas)) {
    throw new RuntimeException('No se pudo crear el directorio temporal de temas.');
}

$afirmar = static function ($condicion, $mensaje) {
    if (!$condicion) {
        throw new RuntimeException($mensaje);
    }
};

$afirmar(strpos($configApp, "'tema_claro_css' => 'app/css/tema_claro.css'") !== false, 'Falta configurar el tema claro.');
$afirmar(strpos($configApp, "'tema_oscuro_css' => 'app/css/tema_oscuro.css'") !== false, 'Falta configurar el tema oscuro.');
$afirmar(strpos($configCore, "require_once RUTA . '/_shared/php/temas.php'") !== false, 'El Core no carga el resolver de temas.');
$afirmar(strpos($head, 'id="tema_app"') !== false, 'El head no publica el enlace de tema.');
$afirmar(strpos($head, 'app/css/tema_componentes.css?v=') !== false, 'La apariencia común no está versionada.');
$afirmar(strpos($encabezado, 'id="tema_oscuro"') !== false, 'El header no publica el selector compartido.');
$afirmar(strpos($headerScript, 'data.cookieTema') === false, 'El script debe consumir dataset con la API DOM.');
$afirmar(strpos($headerScript, 'temaApp.dataset.cookieTema') !== false, 'El selector no persiste la cookie resuelta.');

$config = [
    'tema_claro_css' => '\\estilos\\dia.css',
    'tema_oscuro_css' => '/estilos/noche.css',
];

try {
    $sinTemas = resolver_temas($config, $raizTemporal, 'app bancos', []);
    $afirmar($sinTemas['claro_css'] === 'estilos/dia.css', 'No normalizó la ruta clara.');
    $afirmar($sinTemas['oscuro_css'] === 'estilos/noche.css', 'No normalizó la ruta oscura.');
    $afirmar($sinTemas['cookie'] === 'app_bancos_tema', 'No derivó la cookie por aplicación.');
    $afirmar($sinTemas['css_activo'] === '', 'No debe cargar un tema inexistente.');
    $afirmar($sinTemas['selector_disponible'] === false, 'No debe publicar el selector sin temas.');

    file_put_contents($temaClaro, '/* claro */');
    $soloClaro = resolver_temas($config, $raizTemporal, 'app bancos', []);
    $afirmar($soloClaro['css_activo'] === 'estilos/dia.css', 'No cargó el único tema claro.');
    $afirmar($soloClaro['selector_disponible'] === false, 'Un solo tema no debe publicar selector.');

    file_put_contents($temaOscuro, '/* oscuro */');
    $ambos = resolver_temas($config, $raizTemporal, 'app bancos', ['app_bancos_tema' => 'oscuro']);
    $afirmar($ambos['css_activo'] === 'estilos/noche.css', 'No respetó la preferencia oscura.');
    $afirmar($ambos['actual'] === 'oscuro', 'No marcó oscuro como actual.');
    $afirmar($ambos['selector_disponible'] === true, 'El par completo debe publicar selector.');

    $invalida = resolver_temas($config, $raizTemporal, 'app bancos', ['app_bancos_tema' => 'otro']);
    $afirmar($invalida['actual'] === 'claro', 'Una cookie inválida debe volver a claro.');
} finally {
    if (is_file($temaClaro)) {
        unlink($temaClaro);
    }
    if (is_file($temaOscuro)) {
        unlink($temaOscuro);
    }
    if (is_dir($directorioTemas)) {
        rmdir($directorioTemas);
    }
    if (is_dir($raizTemporal)) {
        rmdir($raizTemporal);
    }
}

echo "OK: contrato compartido de temas validado.\n";
