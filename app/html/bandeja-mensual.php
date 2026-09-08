<?php
$bandeja = $contextoApp['data']->bandeja_mensual ?? (object) [];
$resultado = $bandeja->resultado ?? null;
$filtros = $resultado ?: (object) [];
$escapar = static function ($valor) {
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
};
$cuenta = $filtros->cuenta_bancaria_id ?? ($_GET['cuenta_bancaria_id'] ?? '');
$periodo = $filtros->inicio_periodo ?? ($_GET['inicio_periodo'] ?? '');
$limite = $filtros->limite ?? ($_GET['limite'] ?? 50);
$meses = [
    '01' => 'enero', '02' => 'febrero', '03' => 'marzo', '04' => 'abril',
    '05' => 'mayo', '06' => 'junio', '07' => 'julio', '08' => 'agosto',
    '09' => 'septiembre', '10' => 'octubre', '11' => 'noviembre', '12' => 'diciembre',
];
$periodoLegible = 'Sin seleccionar';
if (preg_match('/^(\d{4})-(\d{2})-\d{2}$/', (string) $periodo, $partesPeriodo)) {
    $periodoLegible = ($meses[$partesPeriodo[2]] ?? $partesPeriodo[2]) . ' de ' . $partesPeriodo[1];
}
$cantidadMovimientos = $resultado !== null ? count($resultado->movimientos) : null;
if (($bandeja->error ?? null) !== null) {
    $estadoCarga = 'No se pudo cargar la bandeja';
} elseif ($cantidadMovimientos !== null) {
    $estadoCarga = $cantidadMovimientos . ($cantidadMovimientos === 1 ? ' movimiento cargado' : ' movimientos cargados');
} else {
    $estadoCarga = 'Esperando consulta';
}
?>
<main class="afterheader">
<div class="bandeja-mensual" data-bandeja>
    <div class="bandeja-shell">
        <h1>Bandeja mensual</h1>
        <p class="bandeja-introduccion">Consulta de sólo lectura. En modo debug, los movimientos se leen desde <code>global_temp</code>.</p>

        <nav class="bandeja-contexto" aria-label="Contexto de la bandeja" data-bandeja-contexto>
            <ol class="bandeja-contexto-miga">
                <li class="bandeja-contexto-origen">Extractos</li>
                <li>
                    <span class="bandeja-contexto-etiqueta">Cuenta</span>
                    <output data-contexto-cuenta><?php echo $cuenta !== '' ? $escapar($cuenta) : 'Sin seleccionar'; ?></output>
                </li>
                <li>
                    <span class="bandeja-contexto-etiqueta">Período</span>
                    <output data-contexto-periodo><?php echo $escapar($periodoLegible); ?></output>
                </li>
            </ol>
            <div class="bandeja-contexto-meta">
                <span class="bandeja-contexto-estado" aria-live="polite" data-contexto-estado><?php echo $escapar($estadoCarga); ?></span>
                <span class="bandeja-contexto-filtros">Filtros activos: ninguno</span>
            </div>
        </nav>

        <section class="bandeja-panel" aria-label="Consulta de movimientos">
            <form class="bandeja-filtros" method="get" action="<?php echo $escapar(RUTA_WEB); ?>">
                <input type="hidden" name="pag" value="bandeja-mensual">
                <label>
                    Cuenta bancaria
                    <input name="cuenta_bancaria_id" type="number" min="1" required value="<?php echo $escapar($cuenta); ?>">
                </label>
                <label>
                    Inicio de período
                    <input name="inicio_periodo" type="date" required value="<?php echo $escapar($periodo); ?>">
                </label>
                <label>
                    Límite
                    <input name="limite" type="number" min="1" max="100" value="<?php echo $escapar($limite); ?>">
                </label>
                <button type="submit">Consultar</button>
            </form>
        </section>

        <?php if (($bandeja->error ?? null) !== null): ?>
            <p role="alert"><?php echo $escapar($bandeja->error); ?></p>
        <?php elseif ($resultado !== null): ?>
        <section class="bandeja-panel" aria-labelledby="bandeja-resultados-titulo">
            <p class="bandeja-resumen" id="bandeja-resultados-titulo"><?php echo count($resultado->movimientos); ?> movimientos en esta página.</p>
            <div class="bandeja-table-region" tabindex="0" role="region" aria-label="Movimientos de la bandeja">
            <table class="bandeja-tabla">
            <thead>
                <tr><th>Fecha</th><th>Referencia</th><th>Descripción</th><th>Crédito</th><th>Débito</th><th>Estado</th><th>Asociación</th><th>Borrador</th><th>Último mensaje</th></tr>
            </thead>
            <tbody>
            <?php foreach ($resultado->movimientos as $movimiento): ?>
                <tr>
                    <td data-label="Fecha"><?php echo $escapar($movimiento['fecha_operacion']); ?></td>
                    <td data-label="Referencia"><?php echo $escapar($movimiento['referencia']); ?></td>
                    <td data-label="Descripción"><?php echo $escapar($movimiento['descripcion']); ?></td>
                    <td class="importe" data-label="Crédito"><?php echo $escapar($movimiento['credito']); ?></td>
                    <td class="importe" data-label="Débito"><?php echo $escapar($movimiento['debito']); ?></td>
                    <td class="estado" data-label="Estado"><?php echo $escapar($movimiento['estado_codigo'] ?? 'SIN_ESTADO'); ?></td>
                    <td data-label="Asociación"><?php
                        if ($movimiento['valor_zetti_id'] !== null) {
                            echo 'Valor #' . $escapar($movimiento['valor_zetti_id']);
                        } elseif ($movimiento['asiento_zetti_id'] !== null) {
                            echo 'Asiento #' . $escapar($movimiento['asiento_zetti_id']);
                        } elseif ($movimiento['borrador_asiento_id'] !== null) {
                            echo 'Borrador #' . $escapar($movimiento['borrador_asiento_id']);
                        } else {
                            echo '<span class="sin-dato">Sin asociación</span>';
                        }
                    ?></td>
                    <td data-label="Borrador"><?php
                        if ($movimiento['borrador_id'] !== null) {
                            echo 'Borrador #' . $escapar($movimiento['borrador_id']) . '<br>';
                            echo $escapar($movimiento['borrador_fecha_contable']) . ' · ' . $escapar($movimiento['borrador_modelo'] ?: 'Sin modelo') . '<br>';
                            echo 'Debe: ' . $escapar($movimiento['borrador_total_debe']) . '<br>';
                            echo 'Haber: ' . $escapar($movimiento['borrador_total_haber']);
                        } else {
                            echo '<span class="sin-dato">Sin borrador</span>';
                        }
                    ?></td>
                    <td class="mensaje" data-label="Último mensaje"><?php
                        if ($movimiento['ultimo_mensaje_cuerpo'] !== null) {
                            echo $escapar($movimiento['ultimo_mensaje_tipo']) . ': ' . $escapar($movimiento['ultimo_mensaje_cuerpo']);
                        } else {
                            echo '<span class="sin-dato">Sin mensajes</span>';
                        }
                    ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            </table>
            </div>
            <?php if ($resultado->siguiente_cursor !== null): ?>
                <p class="bandeja-paginacion">
                    <a class="bandeja-siguiente" href="<?php echo $escapar(RUTA_WEB . '?pag=bandeja-mensual&cuenta_bancaria_id=' . rawurlencode((string) $resultado->cuenta_bancaria_id) . '&inicio_periodo=' . rawurlencode($resultado->inicio_periodo) . '&limite=' . rawurlencode((string) $resultado->limite) . '&cursor=' . rawurlencode($resultado->siguiente_cursor)); ?>">Página siguiente</a>
                </p>
            <?php endif; ?>
        </section>
        <?php endif; ?>
    </div>
</div>
</main>
