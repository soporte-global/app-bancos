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
$limiteEsActivo = (int) $limite !== 50;
if (($bandeja->error ?? null) !== null) {
    $estadoCarga = 'No se pudo cargar la bandeja';
    $estadoContexto = 'error';
} elseif ($cantidadMovimientos !== null) {
    $estadoCarga = $cantidadMovimientos . ($cantidadMovimientos === 1 ? ' movimiento cargado' : ' movimientos cargados');
    $estadoContexto = 'cargado';
} else {
    $estadoCarga = 'Esperando consulta';
    $estadoContexto = 'espera';
}
?>
<main class="afterheader bandeja-page">
<div class="bandeja-mensual" data-bandeja>
    <div class="bandeja-shell">
        <h1>Bandeja mensual</h1>
        <p class="bandeja-introduccion">Consulta de sólo lectura. En modo debug, los movimientos se leen desde <code>global_temp</code>.</p>

        <nav class="bandeja-contexto" aria-label="Contexto de la bandeja" data-bandeja-contexto data-estado="<?php echo $estadoContexto; ?>">
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
                <div class="bandeja-contexto-filtros" data-filtros-activos>
                    <span data-filtros-vacio<?php echo $limiteEsActivo ? ' hidden' : ''; ?>>Filtros activos: ninguno</span>
                    <ul class="bandeja-filtros-lista" aria-label="Filtros activos" data-filtros-lista<?php echo $limiteEsActivo ? '' : ' hidden'; ?>>
                        <?php if ($limiteEsActivo): ?>
                            <li>
                                <span>Límite: <?php echo $escapar($limite); ?></span>
                                <button type="button" aria-label="Quitar filtro Límite" data-limpiar-filtro="limite">×</button>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
                <button class="bandeja-tema" type="button" aria-pressed="false" data-alternar-tema>Modo oscuro</button>
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

        <section class="bandeja-panel bandeja-cargando" aria-live="polite" data-estado-cargando hidden>
            <div class="bandeja-estado-encabezado">
                <span class="bandeja-spinner" aria-hidden="true"></span>
                <div>
                    <h2>Cargando movimientos</h2>
                    <p>Conservamos la cuenta, el período y los filtros mientras se actualiza la bandeja.</p>
                </div>
            </div>
            <div class="bandeja-skeleton" aria-hidden="true">
                <span></span><span></span><span></span>
            </div>
        </section>

        <?php if (($bandeja->error ?? null) !== null): ?>
            <section class="bandeja-panel bandeja-estado bandeja-estado-error" role="alert" data-contenido-bandeja>
                <span class="bandeja-estado-icono" aria-hidden="true">!</span>
                <div>
                    <h2>No pudimos cargar los movimientos</h2>
                    <p><?php echo $escapar($bandeja->error); ?></p>
                    <p class="bandeja-estado-ayuda">La cuenta, el período y los filtros permanecen disponibles para volver a intentar.</p>
                    <button type="button" data-reintentar-consulta>Reintentar consulta</button>
                </div>
            </section>
        <?php elseif ($resultado !== null): ?>
        <?php if ($cantidadMovimientos === 0): ?>
            <section class="bandeja-panel bandeja-estado bandeja-estado-vacio" data-contenido-bandeja>
                <span class="bandeja-estado-icono" aria-hidden="true">0</span>
                <div>
                    <h2>No hay movimientos para este contexto</h2>
                    <p>No encontramos resultados para la cuenta <?php echo $escapar($cuenta); ?> en <?php echo $escapar($periodoLegible); ?>. Podés ajustar los datos de consulta sin perder el estado actual.</p>
                </div>
            </section>
        <?php else: ?>
        <section class="bandeja-panel" aria-labelledby="bandeja-resultados-titulo">
            <p class="bandeja-resumen" id="bandeja-resultados-titulo"><?php echo count($resultado->movimientos); ?> movimientos en esta página.</p>
            <div class="bandeja-table-region" tabindex="0" role="region" aria-label="Movimientos de la bandeja">
            <table class="bandeja-tabla">
            <colgroup>
                <col class="bandeja-col-fecha">
                <col class="bandeja-col-referencia">
                <col class="bandeja-col-descripcion">
                <col class="bandeja-col-credito">
                <col class="bandeja-col-debito">
                <col class="bandeja-col-estado">
                <col class="bandeja-col-asociacion">
                <col class="bandeja-col-borrador">
                <col class="bandeja-col-mensaje">
                <col class="bandeja-col-detalle">
            </colgroup>
            <thead>
                <tr><th>Fecha</th><th>Referencia</th><th>Descripción</th><th>Crédito</th><th>Débito</th><th>Estado</th><th>Asociación</th><th>Borrador</th><th>Último mensaje</th><th>Detalle</th></tr>
            </thead>
            <tbody>
            <?php foreach ($resultado->movimientos as $movimiento): ?>
                <tr>
                    <td data-label="Fecha"><?php echo $escapar($movimiento['fecha_operacion']); ?></td>
                    <td data-label="Referencia"><?php echo $escapar($movimiento['referencia']); ?></td>
                    <td data-label="Descripción"><?php echo $escapar($movimiento['descripcion']); ?></td>
                    <td class="importe" data-label="Crédito"><?php echo $escapar($movimiento['credito']); ?></td>
                    <td class="importe" data-label="Débito"><?php echo $escapar($movimiento['debito']); ?></td>
                    <?php
                        $estadoCodigo = (string) ($movimiento['estado_codigo'] ?? 'SIN_ESTADO');
                        $estadoClase = strtolower(str_replace('_', '-', $estadoCodigo));
                    ?>
                    <td data-label="Estado"><span class="estado-etiqueta estado-etiqueta--<?php echo $escapar($estadoClase); ?>"><?php echo $escapar($estadoCodigo); ?></span></td>
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
                    <td class="bandeja-resumen-celda" data-label="Borrador"><span class="bandeja-resumen-texto"><?php
                        if ($movimiento['borrador_id'] !== null) {
                            echo 'Borrador #' . $escapar($movimiento['borrador_id']);
                            echo ' · ' . $escapar($movimiento['borrador_modelo'] ?: 'Sin modelo');
                        } else {
                            echo '<span class="sin-dato">Sin borrador</span>';
                        }
                    ?></span></td>
                    <td class="mensaje bandeja-resumen-celda" data-label="Último mensaje"><span class="bandeja-resumen-texto"><?php
                        if ($movimiento['ultimo_mensaje_cuerpo'] !== null) {
                            echo $escapar($movimiento['ultimo_mensaje_tipo']) . ' · ' . $escapar($movimiento['ultimo_mensaje_cuerpo']);
                        } else {
                            echo '<span class="sin-dato">Sin mensajes</span>';
                        }
                    ?></span></td>
                    <td data-label="Detalle">
                        <button class="bandeja-ver-detalle" type="button" data-abrir-detalle aria-label="Ver detalle de <?php echo $escapar($movimiento['referencia']); ?>">Ver detalle</button>
                        <template data-detalle-movimiento>
                            <article class="bandeja-detalle-contenido">
                                <header class="bandeja-detalle-resumen">
                                    <p class="bandeja-detalle-sobretitulo">Movimiento <?php echo $escapar($movimiento['referencia']); ?></p>
                                    <h2><?php echo $escapar($movimiento['descripcion']); ?></h2>
                                    <p><?php echo $escapar($movimiento['fecha_operacion']); ?> · Crédito <?php echo $escapar($movimiento['credito']); ?> · Débito <?php echo $escapar($movimiento['debito']); ?></p>
                                    <span class="estado-etiqueta estado-etiqueta--<?php echo $escapar($estadoClase); ?>"><?php echo $escapar($estadoCodigo); ?></span>
                                </header>

                                <section class="bandeja-detalle-seccion">
                                    <h3>Asociación</h3>
                                    <p><?php
                                        if ($movimiento['valor_zetti_id'] !== null) {
                                            echo 'Valor #' . $escapar($movimiento['valor_zetti_id']);
                                        } elseif ($movimiento['asiento_zetti_id'] !== null) {
                                            echo 'Asiento #' . $escapar($movimiento['asiento_zetti_id']);
                                        } elseif ($movimiento['borrador_asiento_id'] !== null) {
                                            echo 'Borrador de asiento #' . $escapar($movimiento['borrador_asiento_id']);
                                        } else {
                                            echo 'Sin asociación registrada';
                                        }
                                    ?></p>
                                </section>

                                <section class="bandeja-detalle-seccion">
                                    <h3>Mensajes</h3>
                                    <?php if ($movimiento['ultimo_mensaje_cuerpo'] !== null): ?>
                                        <p><strong><?php echo $escapar($movimiento['ultimo_mensaje_tipo']); ?>:</strong> <?php echo $escapar($movimiento['ultimo_mensaje_cuerpo']); ?></p>
                                    <?php else: ?>
                                        <p class="sin-dato">Sin mensajes registrados.</p>
                                    <?php endif; ?>
                                </section>

                                <section class="bandeja-detalle-seccion">
                                    <h3>Borradores</h3>
                                    <?php if ($movimiento['borrador_id'] !== null): ?>
                                        <dl class="bandeja-detalle-datos">
                                            <div><dt>Identificador</dt><dd>#<?php echo $escapar($movimiento['borrador_id']); ?></dd></div>
                                            <div><dt>Fecha contable</dt><dd><?php echo $escapar($movimiento['borrador_fecha_contable']); ?></dd></div>
                                            <div><dt>Modelo</dt><dd><?php echo $escapar($movimiento['borrador_modelo'] ?: 'Sin modelo'); ?></dd></div>
                                            <div><dt>Debe</dt><dd><?php echo $escapar($movimiento['borrador_total_debe']); ?></dd></div>
                                            <div><dt>Haber</dt><dd><?php echo $escapar($movimiento['borrador_total_haber']); ?></dd></div>
                                        </dl>
                                    <?php else: ?>
                                        <p class="sin-dato">Sin borradores registrados.</p>
                                    <?php endif; ?>
                                </section>

                                <section class="bandeja-detalle-seccion">
                                    <h3>Historial</h3>
                                    <p>Estado actual: <strong><?php echo $escapar($estadoCodigo); ?></strong>.</p>
                                    <p class="sin-dato">La consulta actual no incluye eventos históricos adicionales.</p>
                                </section>
                            </article>
                        </template>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            </table>
            </div>
            <nav class="bandeja-paginacion" aria-label="Paginación de movimientos" data-paginacion data-cantidad="<?php echo $escapar($cantidadMovimientos); ?>">
                <p class="bandeja-posicion" aria-live="polite" data-posicion-pagina>
                    <?php if (!isset($_GET['cursor'])): ?>
                        Página 1 · movimientos <?php echo $cantidadMovimientos > 0 ? '1–' . $escapar($cantidadMovimientos) : '0'; ?>
                    <?php else: ?>
                        Página actual · <?php echo $escapar($cantidadMovimientos); ?> movimientos visibles
                    <?php endif; ?>
                </p>
                <div class="bandeja-paginacion-controles">
                    <a class="bandeja-pagina bandeja-anterior" aria-disabled="true" data-pagina-anterior>Anterior</a>
                    <?php if ($resultado->siguiente_cursor !== null): ?>
                        <a class="bandeja-pagina bandeja-siguiente" data-pagina-siguiente href="<?php echo $escapar(RUTA_WEB . '?pag=bandeja-mensual&cuenta_bancaria_id=' . rawurlencode((string) $resultado->cuenta_bancaria_id) . '&inicio_periodo=' . rawurlencode($resultado->inicio_periodo) . '&limite=' . rawurlencode((string) $resultado->limite) . '&cursor=' . rawurlencode($resultado->siguiente_cursor)); ?>">Siguiente</a>
                    <?php else: ?>
                        <a class="bandeja-pagina bandeja-siguiente" aria-disabled="true">Siguiente</a>
                    <?php endif; ?>
                </div>
            </nav>
        </section>
        <div class="bandeja-detalle-fondo" data-detalle-fondo hidden></div>
        <aside class="bandeja-detalle" role="dialog" aria-modal="true" aria-labelledby="bandeja-detalle-titulo" data-panel-detalle hidden>
            <header class="bandeja-detalle-cabecera">
                <h2 id="bandeja-detalle-titulo">Detalle del movimiento</h2>
                <button type="button" aria-label="Cerrar detalle" data-cerrar-detalle>×</button>
            </header>
            <div data-detalle-cuerpo></div>
        </aside>
        <?php endif; ?>
        <?php else: ?>
            <section class="bandeja-panel bandeja-estado bandeja-estado-inicial" data-contenido-bandeja>
                <span class="bandeja-estado-icono" aria-hidden="true">→</span>
                <div>
                    <h2>Elegí una cuenta y un período</h2>
                    <p>Completá ambos datos para consultar los movimientos de la bandeja.</p>
                    <button type="button" data-seleccionar-contexto>Seleccionar cuenta y período</button>
                </div>
            </section>
        <?php endif; ?>
    </div>
</div>
</main>
