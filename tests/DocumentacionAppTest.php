<?php
define('RUTA', dirname(__DIR__));
require_once RUTA . '/src/autoload.php';

use AppBancos\Documentation\MarkdownSeguro;

$comprobar = static function ($condicion, $mensaje) {
    if (!$condicion) {
        throw new RuntimeException($mensaje);
    }
};

$muestra = MarkdownSeguro::renderizar("# Título\n\n## Uso <script>alert(1)</script>\n\n- **Seguro** y `codigo`\n\n```php\n<script>alert(2)</script>\n```");
$comprobar(strpos($muestra['html'], '<script>') === false, 'El Markdown ejecutaría HTML incrustado.');
$comprobar(strpos($muestra['html'], '&lt;script&gt;') !== false, 'El HTML debe escaparse.');
$comprobar(strpos($muestra['html'], '<strong>Seguro</strong>') !== false, 'No se renderiza énfasis.');
$comprobar(strpos($muestra['html'], '<code>codigo</code>') !== false, 'No se renderiza código en línea.');
$comprobar(count($muestra['indice']) === 1, 'El índice debe tomar sólo secciones H2.');

foreach (['manual-de-uso.md' => 'Importar un extracto', 'manual-tecnico.md' => 'Sincronización mientras legacy sigue activo'] as $archivo => $capitulo) {
    $fuente = file_get_contents(RUTA . '/docs/' . $archivo);
    $comprobar($fuente !== false && strpos($fuente, $capitulo) !== false, 'Falta contenido en ' . $archivo);
    $render = MarkdownSeguro::renderizar($fuente);
    $comprobar(count($render['indice']) >= 8, 'El índice de ' . $archivo . ' está incompleto.');
    $comprobar(strpos($render['html'], '<h1') !== false && strpos($render['html'], '<h2') !== false, 'No se renderizaron los capítulos de ' . $archivo);
}

$ruta = file_get_contents(RUTA . '/_shared/php/redireccion_inicial.php');
$menu = file_get_contents(RUTA . '/_shared/html/header.php');
$comprobar(strpos($ruta, "\$paginaAutorizada = \$pagina === 'about'") !== false, 'Acerca de debe abrirse para usuarios con acceso general a la app.');
$comprobar(strpos($ruta, 'if (!$accesoAplicacion)') !== false, 'La denegación general debe preceder a Acerca de.');
$comprobar(strpos($menu, 'index.php?pag=about') !== false, 'Falta el enlace de documentación en el menú.');

$_GET['doc'] = 'uso';
ob_start();
include RUTA . '/app/html/about.php';
$paginaUso = ob_get_clean();
$comprobar(strpos($paginaUso, 'Guía de uso de APP BANCOS') !== false, 'La guía de uso no aparece en la página.');
$comprobar(strpos($paginaUso, 'aria-current="page"') !== false, 'Falta identificar la guía activa.');

$_GET['doc'] = 'tecnica';
ob_start();
include RUTA . '/app/html/about.php';
$paginaTecnica = ob_get_clean();
$comprobar(strpos($paginaTecnica, 'Documentación técnica de APP BANCOS') !== false, 'El documento técnico no aparece en la página.');
unset($_GET['doc']);

echo "Documentación y página Acerca de OK\n";
