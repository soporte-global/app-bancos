<?php

function resolver_temas(array $config, $ruta, $carpeta, array $cookies)
{
    $claroCss = ltrim(str_replace('\\', '/', trim((string) ($config['tema_claro_css'] ?? 'app/css/tema_claro.css'))), '/');
    $oscuroCss = ltrim(str_replace('\\', '/', trim((string) ($config['tema_oscuro_css'] ?? 'app/css/tema_oscuro.css'))), '/');
    $cookie = preg_replace('/[^A-Za-z0-9_.-]/', '_', $carpeta) . '_tema';
    $preferido = (string) ($cookies[$cookie] ?? 'claro');
    if (!in_array($preferido, ['claro', 'oscuro'], true)) {
        $preferido = 'claro';
    }
    $claroDisponible = $claroCss !== '' && is_file($ruta . '/' . $claroCss);
    $oscuroDisponible = $oscuroCss !== '' && is_file($ruta . '/' . $oscuroCss);
    $selectorDisponible = $claroDisponible && $oscuroDisponible;
    $actual = 'claro';
    $cssActivo = '';
    if ($selectorDisponible) {
        $actual = $preferido;
        $cssActivo = $actual === 'oscuro' ? $oscuroCss : $claroCss;
    } elseif ($claroDisponible) {
        $cssActivo = $claroCss;
    } elseif ($oscuroDisponible) {
        $actual = 'oscuro';
        $cssActivo = $oscuroCss;
    }
    return [
        'claro_css' => $claroCss,
        'oscuro_css' => $oscuroCss,
        'cookie' => $cookie,
        'actual' => $actual,
        'css_activo' => $cssActivo,
        'selector_disponible' => $selectorDisponible,
    ];
}
