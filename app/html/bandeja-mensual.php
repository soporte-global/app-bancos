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
                <tr><th>Fecha</th><th>Referencia</th><th>Descripción</th><th>Crédito</th><th>Débito</th><th>Estado</th></tr>
            </thead>
            <tbody>
            <?php foreach ($resultado->movimientos as $movimiento): ?>
                <tr>
                    <td><?php echo $escapar($movimiento['fecha_operacion']); ?></td>
                    <td><?php echo $escapar($movimiento['referencia']); ?></td>
                    <td><?php echo $escapar($movimiento['descripcion']); ?></td>
                    <td><?php echo $escapar($movimiento['credito']); ?></td>
                    <td><?php echo $escapar($movimiento['debito']); ?></td>
                    <td><?php echo $escapar($movimiento['estado_id']); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php if ($resultado->siguiente_cursor !== null): ?>
            <p>
                <a href="<?php echo $escapar(RUTA_WEB . '?pag=bandeja-mensual&cuenta_bancaria_id=' . rawurlencode((string) $resultado->cuenta_bancaria_id) . '&inicio_periodo=' . rawurlencode($resultado->inicio_periodo) . '&limite=' . rawurlencode((string) $resultado->limite) . '&cursor=' . rawurlencode($resultado->siguiente_cursor)); ?>">Página siguiente</a>
            </p>
        <?php endif; ?>
    <?php endif; ?>
</main>
