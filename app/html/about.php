<?php
if (!defined('RUTA')) {
    http_response_code(403);
    exit;
}

$documentos = [
    'uso' => [
        'archivo' => RUTA . '/docs/manual-de-uso.md',
        'nombre' => 'Guía de uso',
        'descripcion' => 'Pantallas, operaciones y resolución de problemas para quienes trabajan con extractos.',
    ],
    'tecnica' => [
        'archivo' => RUTA . '/docs/manual-tecnico.md',
        'nombre' => 'Documentación técnica',
        'descripcion' => 'Arquitectura, seguridad, esquemas, sincronización y soporte de la instalación.',
    ],
];
$seleccion = (string) ($_GET['doc'] ?? 'uso');
if (!isset($documentos[$seleccion])) {
    http_response_code(404);
    $seleccion = 'uso';
}
$fuente = file_get_contents($documentos[$seleccion]['archivo']);
if ($fuente === false) {
    throw new RuntimeException('No se pudo cargar la documentación de APP BANCOS.');
}
$documento = AppBancos\Documentation\MarkdownSeguro::renderizar($fuente);
$escapar = static function ($texto) {
    return htmlspecialchars((string) $texto, ENT_QUOTES, 'UTF-8');
};
?>
<main class="afterheader bandeja-page">
<section class="bancos-app about-page">
    <header class="about-encabezado">
        <p class="bandeja-detalle-sobretitulo">APP BANCOS</p>
        <h1>Acerca de y documentación</h1>
        <p>Consultá cómo usar la aplicación y los criterios técnicos de esta versión.</p>
    </header>

    <nav class="about-documentos" aria-label="Documentos de ayuda">
        <?php foreach ($documentos as $clave => $datos): ?>
            <a href="index.php?pag=about&amp;doc=<?php echo $escapar($clave); ?>"<?php echo $seleccion === $clave ? ' aria-current="page"' : ''; ?>>
                <strong><?php echo $escapar($datos['nombre']); ?></strong>
                <span><?php echo $escapar($datos['descripcion']); ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="about-contenido">
        <nav class="about-indice" aria-label="Secciones de <?php echo $escapar($documentos[$seleccion]['nombre']); ?>">
            <h2>En esta página</h2>
            <ol>
                <?php foreach ($documento['indice'] as $seccion): ?>
                    <li><a href="#<?php echo $escapar($seccion['id']); ?>"><?php echo $escapar($seccion['titulo']); ?></a></li>
                <?php endforeach; ?>
            </ol>
        </nav>
        <article class="about-documento">
            <?php echo $documento['html']; ?>
        </article>
    </div>
</section>
</main>
