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
?>
<main class="afterheader">
    <style>
        .bandeja-mensual { color: #172033; background: #ffffff; padding: 24px; }
        .bandeja-mensual h1 { color: #0b3d75; }
        .bandeja-mensual p, .bandeja-mensual label { color: #27364d; }
        .bandeja-mensual form { display: flex; flex-wrap: wrap; gap: 12px; align-items: end; margin: 20px 0; }
        .bandeja-mensual label { display: grid; gap: 4px; font-weight: 600; }
        .bandeja-mensual input { color: #172033; background: #fff; border: 1px solid #56708f; padding: 7px; }
        .bandeja-mensual button, .bandeja-mensual a.bandeja-siguiente { background: #0b5cad; color: #fff; border: 0; padding: 9px 14px; font-weight: 700; text-decoration: none; }
        .bandeja-mensual table { width: 100%; border-collapse: collapse; color: #172033; background: #fff; }
        .bandeja-mensual th { background: #0b3d75; color: #fff; text-align: left; }
        .bandeja-mensual th, .bandeja-mensual td { border: 1px solid #b7c5d6; padding: 9px; vertical-align: top; }
        .bandeja-mensual tbody tr:nth-child(even) { background: #edf3f9; }
        .bandeja-mensual .mensaje { max-width: 280px; white-space: pre-wrap; }
        .bandeja-mensual .estado { font-weight: 700; color: #084f38; }
        .bandeja-mensual .sin-dato { color: #5b6574; }
    </style>
<div class="bandeja-mensual">
    <h1>Bandeja mensual</h1>
    <p>Consulta de sólo lectura. En modo debug, los movimientos se leen desde <code>global_temp</code>.</p>

    <form method="get" action="<?php echo $escapar(RUTA_WEB); ?>">
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

    <?php if (($bandeja->error ?? null) !== null): ?>
        <p role="alert"><?php echo $escapar($bandeja->error); ?></p>
    <?php elseif ($resultado !== null): ?>
        <p><?php echo count($resultado->movimientos); ?> movimientos en esta página.</p>
        <table>
            <thead>
                <tr><th>Fecha</th><th>Referencia</th><th>Descripción</th><th>Crédito</th><th>Débito</th><th>Estado</th><th>Asociación</th><th>Borrador</th><th>Último mensaje</th></tr>
            </thead>
            <tbody>
            <?php foreach ($resultado->movimientos as $movimiento): ?>
                <tr>
                    <td><?php echo $escapar($movimiento['fecha_operacion']); ?></td>
                    <td><?php echo $escapar($movimiento['referencia']); ?></td>
                    <td><?php echo $escapar($movimiento['descripcion']); ?></td>
                    <td><?php echo $escapar($movimiento['credito']); ?></td>
                    <td><?php echo $escapar($movimiento['debito']); ?></td>
                    <td class="estado"><?php echo $escapar($movimiento['estado_codigo'] ?? 'SIN_ESTADO'); ?></td>
                    <td><?php
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
                    <td><?php
                        if ($movimiento['borrador_id'] !== null) {
                            echo 'Borrador #' . $escapar($movimiento['borrador_id']) . '<br>';
                            echo $escapar($movimiento['borrador_fecha_contable']) . ' · ' . $escapar($movimiento['borrador_modelo'] ?: 'Sin modelo') . '<br>';
                            echo 'Debe: ' . $escapar($movimiento['borrador_total_debe']) . '<br>';
                            echo 'Haber: ' . $escapar($movimiento['borrador_total_haber']);
                        } else {
                            echo '<span class="sin-dato">Sin borrador</span>';
                        }
                    ?></td>
                    <td class="mensaje"><?php
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
        <?php if ($resultado->siguiente_cursor !== null): ?>
            <p>
                <a class="bandeja-siguiente" href="<?php echo $escapar(RUTA_WEB . '?pag=bandeja-mensual&cuenta_bancaria_id=' . rawurlencode((string) $resultado->cuenta_bancaria_id) . '&inicio_periodo=' . rawurlencode($resultado->inicio_periodo) . '&limite=' . rawurlencode((string) $resultado->limite) . '&cursor=' . rawurlencode($resultado->siguiente_cursor)); ?>">Página siguiente</a>
            </p>
        <?php endif; ?>
    <?php endif; ?>
</div>
</main>
