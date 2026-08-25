<script id="contexto-app" type="application/json"><?php
echo json_encode(
    $contextoApp,
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
);
?></script>
<script src="app/compatibilidad/contexto.js?v=<?php echo (int) filemtime(RUTA . '/app/compatibilidad/contexto.js'); ?>"></script>
<script src="_shared/js/contexto-app.js?v=<?php echo (int) filemtime(RUTA . '/_shared/js/contexto-app.js'); ?>"></script>
<?php if (is_file(RUTA . '/app/js/shared.js')): ?>
    <script src="app/js/shared.js?v=<?php echo (int) filemtime(RUTA . '/app/js/shared.js'); ?>"></script>
<?php endif; ?>
