<?php
$bandeja = $contextoApp['data']->bandeja_mensual ?? (object) [];
$resultado = $bandeja->resultado ?? null;
$contexto = is_array($bandeja->contexto ?? null) ? $bandeja->contexto : [];
$cuentas = is_array($contexto['cuentas'] ?? null) ? $contexto['cuentas'] : [];
$responsables = is_array($contexto['responsables'] ?? null) ? $contexto['responsables'] : [];
$escrituraHabilitada = (bool) ($bandeja->escritura_habilitada ?? false);
$sesionActual = is_array($contextoApp['sesion'] ?? null) ? $contextoApp['sesion'] : [];
$puedePreparar = (int) ($sesionActual['nivel_acceso'] ?? 2) === 1;
$puedeRevertirPreparacion = $puedePreparar;
$puedeAsociarValor = $puedePreparar;
$puedeAsociarAsiento = $puedePreparar;
$puedeCrearBorrador = $puedePreparar;
$puedeAgregarMensaje = $puedePreparar;
$puedeMarcarMensajesLeidos = $puedePreparar;
$puedeAsignarResponsable = $puedePreparar;
if (!$puedePreparar || !$puedeRevertirPreparacion || !$puedeAsociarValor || !$puedeAsociarAsiento
    || !$puedeCrearBorrador || !$puedeAgregarMensaje || !$puedeMarcarMensajesLeidos || !$puedeAsignarResponsable
) {
    $permisosAcceso = $sesionActual['user']['permisos'] ?? [];
    foreach ($permisosAcceso as $permisoAcceso) {
        foreach ($permisoAcceso['permisos_internos'] ?? [] as $permisoInterno) {
            if (($permisoInterno['nombre_interno'] ?? null) === 'movimiento-preparar') {
                $puedePreparar = true;
            }
            if (($permisoInterno['nombre_interno'] ?? null) === 'movimiento-revertir-preparacion') {
                $puedeRevertirPreparacion = true;
            }
            if (($permisoInterno['nombre_interno'] ?? null) === 'movimiento-asociar-valor') {
                $puedeAsociarValor = true;
            }
            if (($permisoInterno['nombre_interno'] ?? null) === 'movimiento-asociar-asiento') {
                $puedeAsociarAsiento = true;
            }
            if (($permisoInterno['nombre_interno'] ?? null) === 'movimiento-crear-borrador') {
                $puedeCrearBorrador = true;
            }
            if (($permisoInterno['nombre_interno'] ?? null) === 'movimiento-agregar-mensaje') {
                $puedeAgregarMensaje = true;
            }
            if (($permisoInterno['nombre_interno'] ?? null) === 'movimiento-marcar-mensajes-leidos') {
                $puedeMarcarMensajesLeidos = true;
            }
            if (($permisoInterno['nombre_interno'] ?? null) === 'movimiento-asignar-responsable') {
                $puedeAsignarResponsable = true;
            }
        }
    }
}
$filtrosResultado = $resultado !== null && is_array($resultado->filtros ?? null)
    ? $resultado->filtros
    : [];
$escapar = static function ($valor) {
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
};
$cuenta = $resultado->cuenta_bancaria_id ?? ($_GET['cuenta_bancaria_id'] ?? '');
$periodo = $resultado->inicio_periodo ?? ($_GET['inicio_periodo'] ?? '');
$limite = $resultado->limite ?? ($_GET['limite'] ?? 50);
$estadoFiltro = $filtrosResultado['estado'] ?? ($_GET['estado'] ?? '');
$responsableFiltro = $filtrosResultado['responsable_id'] ?? ($_GET['responsable_id'] ?? '');
$asociacionFiltro = $filtrosResultado['asociacion'] ?? ($_GET['asociacion'] ?? '');
$mensajesFiltro = $filtrosResultado['mensajes'] ?? ($_GET['mensajes'] ?? '');
$meses = [
    '01' => 'enero', '02' => 'febrero', '03' => 'marzo', '04' => 'abril',
    '05' => 'mayo', '06' => 'junio', '07' => 'julio', '08' => 'agosto',
    '09' => 'septiembre', '10' => 'octubre', '11' => 'noviembre', '12' => 'diciembre',
];
$periodoLegible = static function ($valor) use ($meses) {
    if (preg_match('/^(\d{4})-(\d{2})-\d{2}$/', (string) $valor, $partes)) {
        return ($meses[$partes[2]] ?? $partes[2]) . ' de ' . $partes[1];
    }
    return 'Sin seleccionar';
};
$cuentaEtiqueta = (string) $cuenta;
$periodosDisponibles = [];
foreach ($cuentas as $opcionCuenta) {
    if ((string) ($opcionCuenta['id'] ?? '') === (string) $cuenta) {
        $cuentaEtiqueta = $opcionCuenta['etiqueta'];
        $periodosDisponibles = $opcionCuenta['periodos'];
        break;
    }
}
if ($cuentaEtiqueta === '') {
    $cuentaEtiqueta = 'Sin seleccionar';
}
$responsableEtiqueta = (string) $responsableFiltro;
foreach ($responsables as $responsable) {
    if ((string) $responsable['id'] === (string) $responsableFiltro) {
        $responsableEtiqueta = $responsable['usuario'];
        break;
    }
}
$filtrosActivos = [];
if ((int) $limite !== 50) {
    $filtrosActivos['limite'] = 'Límite: ' . $limite;
}
if ($estadoFiltro !== '' && $estadoFiltro !== null) {
    $filtrosActivos['estado'] = 'Estado: ' . $estadoFiltro;
}
if ($responsableFiltro !== '' && $responsableFiltro !== null) {
    $filtrosActivos['responsable_id'] = 'Responsable: ' . $responsableEtiqueta;
}
if ($asociacionFiltro !== '' && $asociacionFiltro !== null) {
    $filtrosActivos['asociacion'] = $asociacionFiltro === 'CON' ? 'Con asociación' : 'Sin asociación';
}
if ($mensajesFiltro !== '' && $mensajesFiltro !== null) {
    $filtrosActivos['mensajes'] = $mensajesFiltro === 'CON' ? 'Con mensajes' : 'Sin mensajes';
}
$cantidadMovimientos = $resultado !== null ? count($resultado->movimientos) : null;
$paginaActual = $resultado->pagina_actual ?? (!isset($_GET['cursor']) ? 1 : null);
$inicioActual = $resultado->inicio_actual ?? ($cantidadMovimientos > 0 && !isset($_GET['cursor']) ? 1 : null);
$finActual = $inicioActual !== null && $cantidadMovimientos > 0 ? $inicioActual + $cantidadMovimientos - 1 : 0;
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
$describirAsociacion = static function (array $asociacion) {
    if ($asociacion['valor_zetti_id'] !== null) {
        return 'Valor #' . $asociacion['valor_zetti_id'];
    }
    if ($asociacion['asiento_zetti_id'] !== null) {
        return 'Asiento #' . $asociacion['asiento_zetti_id'];
    }
    return 'Borrador #' . $asociacion['borrador_asiento_id'];
};
?>
<main class="afterheader bandeja-page">
<div class="bandeja-mensual bancos-app" data-bandeja aria-busy="false">
    <div class="bandeja-shell">
        <h1>Bandeja mensual</h1>
        <p class="bandeja-introduccion"><?php echo $escrituraHabilitada && ($puedePreparar || $puedeRevertirPreparacion || $puedeAsociarValor || $puedeCrearBorrador || $puedeAgregarMensaje || $puedeAsignarResponsable) ? 'Consulta de extractos y preparación controlada en el sandbox.' : 'Consulta de sólo lectura de los extractos y su seguimiento.'; ?></p>

        <nav class="bandeja-contexto" aria-label="Contexto de la bandeja" data-bandeja-contexto data-estado="<?php echo $estadoContexto; ?>">
            <ol class="bandeja-contexto-miga">
                <li class="bandeja-contexto-origen">Extractos</li>
                <li><span class="bandeja-contexto-etiqueta">Cuenta</span><output data-contexto-cuenta><?php echo $escapar($cuentaEtiqueta); ?></output></li>
                <li><span class="bandeja-contexto-etiqueta">Período</span><output data-contexto-periodo><?php echo $escapar($periodoLegible($periodo)); ?></output></li>
            </ol>
            <div class="bandeja-contexto-meta">
                <span class="bandeja-contexto-estado" aria-live="polite" data-contexto-estado><?php echo $escapar($estadoCarga); ?></span>
                <div class="bandeja-contexto-filtros" data-filtros-activos>
                    <span data-filtros-vacio<?php echo $filtrosActivos ? ' hidden' : ''; ?>>Filtros activos: ninguno</span>
                    <ul class="bandeja-filtros-lista" aria-label="Filtros activos" data-filtros-lista<?php echo $filtrosActivos ? '' : ' hidden'; ?>>
                        <?php foreach ($filtrosActivos as $nombre => $etiqueta): ?>
                            <li><span><?php echo $escapar($etiqueta); ?></span><button type="button" aria-label="Quitar filtro <?php echo $escapar($etiqueta); ?>" data-limpiar-filtro="<?php echo $escapar($nombre); ?>">×</button></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </nav>

        <section class="bandeja-panel bandeja-selector" aria-label="Consulta de movimientos">
            <form class="bandeja-filtros" method="get" action="<?php echo $escapar(RUTA_WEB); ?>">
                <input type="hidden" name="pag" value="bandeja-mensual">
                <label>Cuenta bancaria
                    <select name="cuenta_bancaria_id" required data-selector-cuenta>
                        <option value="">Seleccionar cuenta</option>
                        <?php foreach ($cuentas as $opcionCuenta): ?>
                            <option value="<?php echo $escapar($opcionCuenta['id']); ?>"<?php echo (string) $opcionCuenta['id'] === (string) $cuenta ? ' selected' : ''; ?>><?php echo $escapar($opcionCuenta['etiqueta']); ?></option>
                        <?php endforeach; ?>
                        <?php if ($cuenta !== '' && !$periodosDisponibles): ?><option value="<?php echo $escapar($cuenta); ?>" selected>Cuenta #<?php echo $escapar($cuenta); ?></option><?php endif; ?>
                    </select>
                </label>
                <label>Período
                    <select name="inicio_periodo" required data-selector-periodo>
                        <option value="">Seleccionar período</option>
                        <?php foreach ($cuentas as $opcionCuenta): ?>
                            <?php foreach ($opcionCuenta['periodos'] as $opcionPeriodo): ?>
                                <option value="<?php echo $escapar($opcionPeriodo); ?>" data-cuenta="<?php echo $escapar($opcionCuenta['id']); ?>"<?php echo (string) $opcionCuenta['id'] === (string) $cuenta && $opcionPeriodo === $periodo ? ' selected' : ''; ?>><?php echo $escapar($periodoLegible($opcionPeriodo)); ?></option>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                        <?php if ($periodo !== '' && !in_array($periodo, $periodosDisponibles, true)): ?><option value="<?php echo $escapar($periodo); ?>" data-cuenta="<?php echo $escapar($cuenta); ?>" selected><?php echo $escapar($periodoLegible($periodo)); ?></option><?php endif; ?>
                    </select>
                </label>
                <label>Estado
                    <select name="estado">
                        <option value="">Todos</option>
                        <?php foreach (['ABIERTO', 'PARA_CERRAR', 'CERRADO'] as $estado): ?><option value="<?php echo $estado; ?>"<?php echo $estadoFiltro === $estado ? ' selected' : ''; ?>><?php echo $estado; ?></option><?php endforeach; ?>
                    </select>
                </label>
                <label>Responsable
                    <select name="responsable_id">
                        <option value="">Todos</option>
                        <?php foreach ($responsables as $responsable): ?><option value="<?php echo $escapar($responsable['id']); ?>"<?php echo (string) $responsable['id'] === (string) $responsableFiltro ? ' selected' : ''; ?>><?php echo $escapar($responsable['usuario']); ?></option><?php endforeach; ?>
                    </select>
                </label>
                <label>Asociación
                    <select name="asociacion"><option value="">Todas</option><option value="CON"<?php echo $asociacionFiltro === 'CON' ? ' selected' : ''; ?>>Con asociación</option><option value="SIN"<?php echo $asociacionFiltro === 'SIN' ? ' selected' : ''; ?>>Sin asociación</option></select>
                </label>
                <label>Mensajes
                    <select name="mensajes"><option value="">Todos</option><option value="CON"<?php echo $mensajesFiltro === 'CON' ? ' selected' : ''; ?>>Con mensajes</option><option value="SIN"<?php echo $mensajesFiltro === 'SIN' ? ' selected' : ''; ?>>Sin mensajes</option></select>
                </label>
                <label>Límite
                    <input name="limite" type="number" min="1" max="100" value="<?php echo $escapar($limite); ?>">
                </label>
                <button type="submit">Consultar</button>
            </form>
        </section>

        <section class="bandeja-panel bandeja-cargando" aria-live="polite" data-estado-cargando hidden><div class="bandeja-estado-encabezado"><span class="bandeja-spinner" aria-hidden="true"></span><div><h2>Cargando movimientos</h2><p>Conservamos la cuenta, el período y los filtros mientras se actualiza la bandeja.</p></div></div><div class="bandeja-skeleton" aria-hidden="true"><span></span><span></span><span></span></div></section>

        <?php if (($bandeja->error ?? null) !== null): ?>
            <section class="bandeja-panel bandeja-estado bandeja-estado-error" role="alert" data-contenido-bandeja><span class="bandeja-estado-icono" aria-hidden="true">!</span><div><h2>No pudimos cargar los movimientos</h2><p><?php echo $escapar($bandeja->error); ?></p><p class="bandeja-estado-ayuda">El contexto permanece disponible para volver a intentar.</p><button type="button" data-reintentar-consulta>Reintentar consulta</button></div></section>
        <?php elseif ($resultado !== null && $cantidadMovimientos === 0): ?>
            <section class="bandeja-panel bandeja-estado bandeja-estado-vacio" data-contenido-bandeja><span class="bandeja-estado-icono" aria-hidden="true">0</span><div><h2>No hay movimientos para este contexto</h2><p>No encontramos resultados para los datos y filtros seleccionados.</p></div></section>
        <?php elseif ($resultado !== null): ?>
        <section class="bandeja-panel" aria-labelledby="bandeja-resultados-titulo">
            <p class="bandeja-resumen" id="bandeja-resultados-titulo"><?php echo $cantidadMovimientos; ?> movimientos en esta página.</p>
            <div class="bandeja-table-region" tabindex="0" role="region" aria-label="Movimientos de la bandeja">
            <table class="bandeja-tabla"><caption class="visualmente_oculto">Movimientos bancarios del contexto seleccionado</caption>
            <colgroup><col class="bandeja-col-fecha"><col class="bandeja-col-referencia"><col class="bandeja-col-descripcion"><col class="bandeja-col-credito"><col class="bandeja-col-debito"><col class="bandeja-col-estado"><col class="bandeja-col-asociacion"><col class="bandeja-col-borrador"><col class="bandeja-col-mensaje"><col class="bandeja-col-detalle"></colgroup>
            <thead><tr><th scope="col">Fecha</th><th scope="col">Referencia</th><th scope="col">Descripción</th><th scope="col">Crédito</th><th scope="col">Débito</th><th scope="col">Estado</th><th scope="col">Asociación</th><th scope="col">Borrador</th><th scope="col">Último mensaje</th><th scope="col">Detalle</th></tr></thead>
            <tbody>
            <?php foreach ($resultado->movimientos as $movimiento): ?>
                <?php $estadoCodigo = (string) ($movimiento['estado_codigo'] ?? 'SIN_ESTADO'); $estadoClase = strtolower(str_replace('_', '-', $estadoCodigo)); $asociaciones = $movimiento['asociaciones'] ?? []; if (!$asociaciones) { foreach (['valor_zetti_id', 'asiento_zetti_id', 'borrador_asiento_id'] as $destino) { if (($movimiento[$destino] ?? null) !== null) { $asociaciones[] = ['valor_zetti_id' => null, 'asiento_zetti_id' => null, 'borrador_asiento_id' => null, 'monto_asociado' => null, 'compartido' => false, 'observacion' => null, $destino => $movimiento[$destino]]; } } } $mensajes = $movimiento['mensajes'] ?? []; $mensajesNoLeidos = (int) ($movimiento['mensajes_no_leidos'] ?? 0); $historial = $movimiento['historial'] ?? []; $borradores = $movimiento['borradores'] ?? []; ?>
                <?php $tieneValorAsociado = false; foreach ($asociaciones as $asociacionActual) { if (($asociacionActual['valor_zetti_id'] ?? null) !== null) { $tieneValorAsociado = true; break; } } ?>
                <?php $tieneAsientoAsociado = false; foreach ($asociaciones as $asociacionActual) { if (($asociacionActual['asiento_zetti_id'] ?? null) !== null) { $tieneAsientoAsociado = true; break; } } ?>
                <?php $tieneBorradorActivo = count($borradores) > 0; ?>
                <tr>
                    <td data-label="Fecha"><?php echo $escapar($movimiento['fecha_operacion']); ?></td><td data-label="Referencia"><?php echo $escapar($movimiento['referencia']); ?></td><td data-label="Descripción"><?php echo $escapar($movimiento['descripcion']); ?></td><td class="importe" data-label="Crédito"><?php echo $escapar($movimiento['credito']); ?></td><td class="importe" data-label="Débito"><?php echo $escapar($movimiento['debito']); ?></td>
                    <td data-label="Estado"><span class="estado-etiqueta estado-etiqueta--<?php echo $escapar($estadoClase); ?>"><?php echo $escapar($estadoCodigo); ?></span></td>
                    <td data-label="Asociación"><?php echo $asociaciones ? $escapar(implode(' + ', array_map($describirAsociacion, $asociaciones))) : '<span class="sin-dato">Sin asociación</span>'; ?></td>
                    <td class="bandeja-resumen-celda" data-label="Borrador"><span class="bandeja-resumen-texto"><?php echo $movimiento['borrador_id'] !== null ? 'Borrador #' . $escapar($movimiento['borrador_id']) . ' · ' . $escapar($movimiento['borrador_modelo'] ?: 'Sin modelo') : '<span class="sin-dato">Sin borrador</span>'; ?></span></td>
                    <td class="mensaje bandeja-resumen-celda" data-label="Último mensaje"><span class="bandeja-resumen-texto"><?php echo $movimiento['ultimo_mensaje_cuerpo'] !== null ? $escapar($movimiento['ultimo_mensaje_tipo']) . ' · ' . $escapar($movimiento['ultimo_mensaje_cuerpo']) . ($mensajesNoLeidos > 0 ? ' · ' . $mensajesNoLeidos . ' sin leer' : '') : '<span class="sin-dato">Sin mensajes</span>'; ?></span></td>
                    <td data-label="Detalle"><button class="bandeja-ver-detalle" type="button" data-abrir-detalle aria-label="Ver detalle de <?php echo $escapar($movimiento['referencia']); ?>">Ver detalle</button>
                        <template data-detalle-movimiento><article class="bandeja-detalle-contenido">
                            <header class="bandeja-detalle-resumen"><p class="bandeja-detalle-sobretitulo">Movimiento <?php echo $escapar($movimiento['referencia']); ?></p><h2><?php echo $escapar($movimiento['descripcion']); ?></h2><p><?php echo $escapar($movimiento['fecha_operacion']); ?> · Crédito <?php echo $escapar($movimiento['credito']); ?> · Débito <?php echo $escapar($movimiento['debito']); ?></p><span class="estado-etiqueta estado-etiqueta--<?php echo $escapar($estadoClase); ?>"><?php echo $escapar($estadoCodigo); ?></span></header>
                            <?php if ($escrituraHabilitada && $puedeAsignarResponsable): ?><section class="bandeja-detalle-seccion bandeja-accion" data-accion-asignar-responsable><h3>Responsable</h3><p>Puede asignarse únicamente a usuarios con acceso efectivo a APP BANCOS.</p><label>Usuario<select required data-responsable-id><option value="">Seleccionar responsable</option><?php foreach ($responsables as $responsable): ?><option value="<?php echo $escapar($responsable['id']); ?>"<?php echo (string) ($movimiento['usuario_hub_id'] ?? '') === (string) $responsable['id'] ? ' selected' : ''; ?>><?php echo $escapar($responsable['usuario']); ?></option><?php endforeach; ?></select></label><button type="button" data-asignar-responsable data-movimiento-id="<?php echo $escapar($movimiento['id']); ?>" data-cuenta-bancaria-id="<?php echo $escapar($cuenta); ?>" data-inicio-periodo="<?php echo $escapar($periodo); ?>">Asignar responsable</button><p class="bandeja-accion-estado" role="status" aria-live="polite" data-accion-estado></p></section><?php endif; ?>
                            <?php if ($escrituraHabilitada && $puedeAsociarValor && $estadoCodigo === 'ABIERTO' && !$tieneValorAsociado): ?><section class="bandeja-detalle-seccion bandeja-accion" data-accion-asociar-valor><h3>Asociar valor ERP</h3><p>Reserva y vincula un valor disponible. El importe asociado se toma del movimiento; esta acción no modifica el ERP.</p><label>ID del valor ERP<input type="number" min="1" required inputmode="numeric" data-valor-zetti-id></label><button type="button" class="bandeja-buscar-recurso" data-buscar-valores>Buscar valores disponibles</button><div class="bandeja-resultados-recurso" data-resultados-valores aria-live="polite"></div><button type="button" data-asociar-valor data-movimiento-id="<?php echo $escapar($movimiento['id']); ?>" data-cuenta-bancaria-id="<?php echo $escapar($cuenta); ?>" data-inicio-periodo="<?php echo $escapar($periodo); ?>">Reservar y asociar</button><p class="bandeja-accion-estado" role="status" aria-live="polite" data-accion-estado></p></section><?php endif; ?>
                            <?php if ($escrituraHabilitada && $puedeAsociarAsiento && $estadoCodigo === 'ABIERTO' && !$tieneAsientoAsociado): ?><section class="bandeja-detalle-seccion bandeja-accion" data-accion-asociar-asiento><h3>Asociar asiento ERP</h3><p>Reserva y vincula un asiento existente y balanceado. En modalidad exclusiva su importe debe coincidir con el movimiento; esta acción no modifica el ERP.</p><label>ID del asiento ERP<input type="number" min="1" required inputmode="numeric" data-asiento-zetti-id></label><label class="bandeja-opcion-compartida"><input type="checkbox" data-asiento-compartido><span>Asiento compartido por varios movimientos</span></label><button type="button" class="bandeja-buscar-recurso" data-buscar-asientos>Buscar asientos compatibles</button><div class="bandeja-resultados-recurso" data-resultados-asientos aria-live="polite"></div><button type="button" data-asociar-asiento data-movimiento-id="<?php echo $escapar($movimiento['id']); ?>" data-cuenta-bancaria-id="<?php echo $escapar($cuenta); ?>" data-inicio-periodo="<?php echo $escapar($periodo); ?>">Reservar y asociar asiento</button><p class="bandeja-accion-estado" role="status" aria-live="polite" data-accion-estado></p></section><?php endif; ?>
                            <?php if ($escrituraHabilitada && $puedeCrearBorrador && $estadoCodigo === 'ABIERTO' && !$tieneBorradorActivo): ?>
                            <section class="bandeja-detalle-seccion bandeja-accion bandeja-accion-borrador" data-accion-crear-borrador>
                                <h3>Crear borrador contable</h3>
                                <p>El borrador debe tener entre 2 y 200 líneas y quedar balanceado. No genera asientos en el ERP.</p>
                                <div class="bandeja-borrador-datos">
                                    <div class="bandeja-selector-recurso" data-selector-nodo>
                                        <label>Buscar nodo ERP<input type="search" maxlength="80" autocomplete="off" placeholder="ID, código o nombre" data-borrador-nodo-busqueda></label>
                                        <input type="hidden" data-borrador-nodo>
                                        <button type="button" class="bandeja-buscar-recurso" data-buscar-nodos>Buscar nodos</button>
                                        <div class="bandeja-resultados-recurso" data-resultados-nodos aria-live="polite"></div>
                                    </div>
                                    <label>Fecha contable<input type="date" required value="<?php echo $escapar($movimiento['fecha_operacion']); ?>" data-borrador-fecha></label>
                                    <label>Modelo<input type="text" maxlength="80" required value="MANUAL" data-borrador-modelo></label>
                                </div>
                                <div class="bandeja-borrador-lineas" data-borrador-lineas>
                                    <?php foreach ([1, 2] as $lineaInicial): ?>
                                    <div class="bandeja-borrador-linea" data-borrador-linea>
                                        <div class="bandeja-selector-recurso bandeja-selector-cuenta">
                                            <label>Buscar cuenta contable<input type="search" maxlength="80" autocomplete="off" placeholder="ID, código o nombre" data-linea-cuenta-busqueda></label>
                                            <input type="hidden" data-linea-cuenta>
                                            <button type="button" class="bandeja-buscar-recurso" data-buscar-cuentas>Buscar cuentas</button>
                                            <div class="bandeja-resultados-recurso" data-resultados-cuentas aria-live="polite"></div>
                                        </div>
                                        <label>Debe<input type="number" min="0" step="0.00001" value="0" required data-linea-debe></label>
                                        <label>Haber<input type="number" min="0" step="0.00001" value="0" required data-linea-haber></label>
                                        <label>Detalle<input type="text" maxlength="200" data-linea-observacion></label>
                                        <button type="button" data-quitar-linea aria-label="Quitar línea">×</button>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="bandeja-borrador-controles"><button type="button" data-agregar-linea>Agregar línea</button><button type="button" data-crear-borrador data-movimiento-id="<?php echo $escapar($movimiento['id']); ?>" data-cuenta-bancaria-id="<?php echo $escapar($cuenta); ?>" data-inicio-periodo="<?php echo $escapar($periodo); ?>">Crear y asociar borrador</button></div>
                                <p class="bandeja-accion-estado" role="status" aria-live="polite" data-accion-estado></p>
                            </section>
                            <?php endif; ?>
                            <?php if ($escrituraHabilitada && $puedePreparar && $estadoCodigo === 'ABIERTO'): ?><section class="bandeja-detalle-seccion bandeja-accion" data-accion-preparar><h3>Preparación</h3><p>Marca el movimiento como listo para cierre. No crea ni modifica datos del ERP.</p><button type="button" data-preparar-movimiento data-movimiento-id="<?php echo $escapar($movimiento['id']); ?>" data-cuenta-bancaria-id="<?php echo $escapar($cuenta); ?>" data-inicio-periodo="<?php echo $escapar($periodo); ?>">Marcar para cerrar</button><p class="bandeja-accion-estado" role="status" aria-live="polite" data-accion-estado></p></section><?php endif; ?>
                            <?php if ($escrituraHabilitada && $puedeRevertirPreparacion && $estadoCodigo === 'PARA_CERRAR'): ?><section class="bandeja-detalle-seccion bandeja-accion" data-accion-revertir><h3>Revertir preparación</h3><p>Vuelve el movimiento a ABIERTO y desactiva sus asociaciones, reservas y borradores activos. El historial se conserva.</p><label>Motivo<textarea maxlength="200" minlength="3" required data-motivo-reversion placeholder="Indicá por qué debe volver a ABIERTO"></textarea></label><button type="button" data-revertir-preparacion data-movimiento-id="<?php echo $escapar($movimiento['id']); ?>" data-cuenta-bancaria-id="<?php echo $escapar($cuenta); ?>" data-inicio-periodo="<?php echo $escapar($periodo); ?>">Volver a abierto</button><p class="bandeja-accion-estado" role="status" aria-live="polite" data-accion-estado></p></section><?php endif; ?>
                            <section class="bandeja-detalle-seccion"><h3>Asociaciones</h3><?php if ($asociaciones): ?><ol class="bandeja-detalle-lista"><?php foreach ($asociaciones as $asociacion): ?><li><strong><?php echo $escapar($describirAsociacion($asociacion)); ?></strong><?php if ($asociacion['monto_asociado'] !== null): ?> · <?php echo $escapar($asociacion['monto_asociado']); ?><?php endif; ?><?php if ($asociacion['compartido'] === true || $asociacion['compartido'] === 't'): ?> · compartido<?php endif; ?><?php if ($asociacion['observacion']): ?><small><?php echo $escapar($asociacion['observacion']); ?></small><?php endif; ?></li><?php endforeach; ?></ol><?php else: ?><p class="sin-dato">Sin asociaciones registradas.</p><?php endif; ?></section>
                            <section class="bandeja-detalle-seccion bandeja-mensajeria" data-mensajeria-movimiento>
                                <h3>Mensajes</h3><?php if ($mensajesNoLeidos > 0): ?><p><strong><?php echo $mensajesNoLeidos; ?> sin leer</strong></p><?php endif; ?>
                                <?php if ($mensajes): ?><ol class="bandeja-detalle-lista"><?php foreach ($mensajes as $mensaje): ?><li<?php echo !in_array($mensaje['leido'] ?? false, [true, 1, '1', 't'], true) ? ' class="mensaje-no-leido"' : ''; ?>><strong><?php echo $escapar($mensaje['tipo_mensaje']); ?></strong> · <?php echo $escapar($mensaje['emisor'] ?: 'Sistema'); ?> · <time><?php echo $escapar($mensaje['emitido_en']); ?></time><?php echo !in_array($mensaje['leido'] ?? false, [true, 1, '1', 't'], true) ? ' · no leído' : ''; ?><span><?php echo $escapar($mensaje['cuerpo']); ?></span></li><?php endforeach; ?></ol><?php else: ?><p class="sin-dato">Sin mensajes registrados.</p><?php endif; ?>
                                <?php if ($escrituraHabilitada && $puedeAgregarMensaje): ?><label>Nuevo mensaje<textarea minlength="1" maxlength="2000" required placeholder="Escribí un mensaje para el seguimiento" data-mensaje-cuerpo></textarea></label><button type="button" data-agregar-mensaje data-movimiento-id="<?php echo $escapar($movimiento['id']); ?>" data-cuenta-bancaria-id="<?php echo $escapar($cuenta); ?>" data-inicio-periodo="<?php echo $escapar($periodo); ?>">Publicar mensaje</button><?php endif; ?>
                                <?php if ($escrituraHabilitada && $puedeMarcarMensajesLeidos && $mensajesNoLeidos > 0): ?><button type="button" data-marcar-mensajes-leidos data-movimiento-id="<?php echo $escapar($movimiento['id']); ?>" data-cuenta-bancaria-id="<?php echo $escapar($cuenta); ?>" data-inicio-periodo="<?php echo $escapar($periodo); ?>">Marcar mensajes como leídos</button><?php endif; ?>
                                <?php if ($escrituraHabilitada && ($puedeAgregarMensaje || ($puedeMarcarMensajesLeidos && $mensajesNoLeidos > 0))): ?><p class="bandeja-accion-estado" role="status" aria-live="polite" data-mensajeria-estado></p><?php endif; ?>
                            </section>
                            <section class="bandeja-detalle-seccion"><h3>Borradores</h3><?php if ($borradores): ?><?php foreach ($borradores as $borrador): ?><div class="bandeja-borrador"><p><strong>Borrador #<?php echo $escapar($borrador['id']); ?></strong> · <?php echo $escapar($borrador['modelo'] ?: 'Sin modelo'); ?> · <?php echo $escapar($borrador['fecha_contable']); ?> · <?php echo $escapar($borrador['estado_codigo']); ?></p><?php if ($borrador['lineas']): ?><div class="bandeja-lineas-region"><table class="bandeja-lineas"><thead><tr><th>Cuenta</th><th>Debe</th><th>Haber</th></tr></thead><tbody><?php foreach ($borrador['lineas'] as $linea): ?><tr><td><?php echo $escapar(($linea['cuenta_codigo'] ?: $linea['cuenta_zetti_id']) . ' · ' . $linea['cuenta_nombre']); ?></td><td><?php echo $escapar($linea['debe']); ?></td><td><?php echo $escapar($linea['haber']); ?></td></tr><?php endforeach; ?></tbody></table></div><?php else: ?><p class="sin-dato">Sin líneas.</p><?php endif; ?></div><?php endforeach; ?><?php else: ?><p class="sin-dato">Sin borradores registrados.</p><?php endif; ?></section>
                            <section class="bandeja-detalle-seccion"><h3>Historial</h3><?php if ($historial): ?><ol class="bandeja-detalle-lista bandeja-historial"><?php foreach ($historial as $evento): ?><li><strong><?php echo $escapar($evento['estado_codigo']); ?></strong> · <?php echo $escapar($evento['usuario'] ?: 'Usuario #' . $evento['usuario_hub_id']); ?> · <time><?php echo $escapar($evento['registrado_en']); ?></time><?php if ($evento['motivo'] || $evento['observacion']): ?><span><?php echo $escapar($evento['motivo'] ?: $evento['observacion']); ?></span><?php endif; ?></li><?php endforeach; ?></ol><?php else: ?><p>Estado actual: <strong><?php echo $escapar($estadoCodigo); ?></strong>.</p><p class="sin-dato">Sin eventos históricos adicionales.</p><?php endif; ?></section>
                        </article></template>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody></table></div>
            <?php $parametrosSiguiente = ['pag' => 'bandeja-mensual', 'cuenta_bancaria_id' => $resultado->cuenta_bancaria_id, 'inicio_periodo' => $resultado->inicio_periodo, 'limite' => $resultado->limite]; foreach ($resultado->filtros as $nombre => $valor) { if ($valor !== null && $valor !== '') { $parametrosSiguiente[$nombre] = $valor; } } ?>
            <nav class="bandeja-paginacion" aria-label="Paginación de movimientos" data-paginacion data-cantidad="<?php echo $escapar($cantidadMovimientos); ?>"><p class="bandeja-posicion" aria-live="polite" data-posicion-pagina><?php echo $paginaActual !== null && $inicioActual !== null ? 'Página ' . $escapar($paginaActual) . ' · registros ' . $escapar($inicioActual) . '–' . $escapar($finActual) . ' · ' . $escapar($cantidadMovimientos) . ' en esta página' : 'Página actual · ' . $escapar($cantidadMovimientos) . ' registros en esta página'; ?></p><div class="bandeja-paginacion-controles"><a class="bandeja-pagina bandeja-anterior" aria-disabled="true" data-pagina-anterior>Anterior</a><?php if ($resultado->siguiente_cursor !== null): $parametrosSiguiente['cursor'] = $resultado->siguiente_cursor; ?><a class="bandeja-pagina bandeja-siguiente" data-pagina-siguiente href="<?php echo $escapar(RUTA_WEB . '?' . http_build_query($parametrosSiguiente, '', '&', PHP_QUERY_RFC3986)); ?>">Siguiente</a><?php else: ?><a class="bandeja-pagina bandeja-siguiente" aria-disabled="true">Siguiente</a><?php endif; ?></div></nav>
        </section>
        <div class="bandeja-detalle-fondo" data-detalle-fondo hidden></div><aside class="bandeja-detalle" role="dialog" aria-modal="true" aria-labelledby="bandeja-detalle-titulo" data-panel-detalle hidden><header class="bandeja-detalle-cabecera"><h2 id="bandeja-detalle-titulo">Detalle del movimiento</h2><button type="button" aria-label="Cerrar detalle" data-cerrar-detalle>×</button></header><div data-detalle-cuerpo></div></aside>
        <?php else: ?>
            <section class="bandeja-panel bandeja-estado bandeja-estado-inicial" data-contenido-bandeja><span class="bandeja-estado-icono" aria-hidden="true">→</span><div><h2>Elegí una cuenta y un período</h2><p>Seleccioná un contexto disponible para consultar sus movimientos.</p><button type="button" data-seleccionar-contexto>Seleccionar cuenta y período</button></div></section>
        <?php endif; ?>
    </div>
</div>
</main>
