<?php
$datos = $contextoApp['data']->configuraciones ?? (object) [];
$configuraciones = is_array($datos->configuraciones ?? null) ? $datos->configuraciones : [];
$subtipos = is_array($datos->subtipos ?? null) ? $datos->subtipos : [];
$cuentasBancarias = is_array($datos->cuentas_bancarias ?? null) ? $datos->cuentas_bancarias : [];
$cuentasContables = is_array($datos->cuentas_contables ?? null) ? $datos->cuentas_contables : [];
$responsables = is_array($datos->responsables ?? null) ? $datos->responsables : [];
$seleccionada = $datos->seleccionada ?? null;
$habilitada = (bool) ($datos->habilitada ?? false);
$error = $datos->error ?? null;
$escapar = static function ($valor) {
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
};
?>
<main class="afterheader bandeja-page">
<section class="bancos-app configuracion-page" data-configuracion-page>
    <header class="configuracion-encabezado">
        <p class="bandeja-detalle-sobretitulo">Reglas de importación</p>
        <h1>Configuraciones</h1>
        <p>Revisá las reglas existentes y controlá cuáles pueden habilitar una validación automática posterior.</p>
    </header>

    <?php if ($error !== null): ?>
        <section class="bandeja-estado bandeja-estado--error" role="alert"><h2>No se pudo cargar la configuración</h2><p><?php echo $escapar($error); ?></p></section>
    <?php elseif (!$habilitada): ?>
        <section class="bandeja-estado" role="status"><h2>Mantenimiento deshabilitado</h2><p>Los cambios de configuración sólo están disponibles actualmente en el sandbox.</p></section>
    <?php elseif (!$configuraciones): ?>
        <section class="bandeja-estado" role="status"><h2>Sin configuraciones</h2><p>No hay configuraciones activas vinculadas a cuentas en el sandbox.</p></section>
    <?php else: ?>
        <form class="configuracion-selector" method="get">
            <input type="hidden" name="pag" value="configuraciones">
            <label>Configuración
                <select name="configuracion_id" required>
                    <?php foreach ($configuraciones as $configuracion): ?>
                        <option value="<?php echo $escapar($configuracion['id']); ?>" <?php echo $seleccionada !== null && $seleccionada['id'] === $configuracion['id'] ? 'selected' : ''; ?>><?php echo $escapar($configuracion['etiqueta']); ?> · <?php echo $escapar($configuracion['reglas']); ?> reglas</option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button type="submit">Consultar</button>
        </form>

        <?php if ($seleccionada !== null): ?>
            <section class="configuracion-detalle" data-configuracion-id="<?php echo $escapar($seleccionada['id']); ?>">
                <header>
                    <div><p class="bandeja-detalle-sobretitulo">Configuración #<?php echo $escapar($seleccionada['id']); ?></p><h2>Resumen operativo</h2></div>
                    <p><strong data-total-automaticas><?php echo $escapar($seleccionada['automaticas']); ?></strong> de <span data-total-reglas><?php echo $escapar($seleccionada['total_reglas']); ?></span> habilitan validación automática.</p>
                </header>
                <p class="bandeja-accion-estado" role="status" aria-live="polite" data-configuracion-estado></p>
                <?php $cuentasVinculadas = is_array($seleccionada['cuentas'] ?? null) ? $seleccionada['cuentas'] : []; ?>
                <section class="configuracion-cuentas" data-configuracion-cuentas>
                    <header><div><p class="bandeja-detalle-sobretitulo">Alcance operativo</p><h2>Cuentas bancarias vinculadas</h2></div><p><strong data-total-cuentas><?php echo count($cuentasVinculadas); ?></strong> cuentas habilitadas para futuras importaciones.</p></header>
                    <form class="configuracion-vinculo-editor" data-editor-vinculo>
                        <label>Cuenta bancaria
                            <select name="cuenta_bancaria_id" required>
                                <option value="">Seleccionar cuenta</option>
                                <?php foreach ($cuentasBancarias as $cuenta): ?>
                                    <option value="<?php echo $escapar($cuenta['id']); ?>"><?php echo $escapar($cuenta['etiqueta']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>Motivo
                            <input type="text" name="motivo" minlength="3" maxlength="200" required placeholder="Motivo del vínculo">
                        </label>
                        <button type="submit">Vincular cuenta</button>
                    </form>
                    <div class="bandeja-lineas-region">
                        <table class="bandeja-lineas configuracion-vinculos">
                            <thead><tr><th scope="col">Cuenta bancaria</th><th scope="col">Acción</th></tr></thead>
                            <tbody>
                            <?php foreach ($cuentasVinculadas as $cuenta): ?>
                                <tr data-cuenta-vinculada-id="<?php echo $escapar($cuenta['id']); ?>"><td data-etiqueta-cuenta><?php echo $escapar($cuenta['etiqueta']); ?></td><td class="configuracion-acciones-regla"><button type="button" class="configuracion-peligro" data-desvincular-cuenta>Desvincular</button></td></tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <p class="configuracion-ayuda">Desvincular impide nuevas importaciones con esta configuración, pero no elimina lotes ni movimientos históricos.</p>
                </section>
                <?php $mapeos = is_array($seleccionada['mapeos'] ?? null) ? $seleccionada['mapeos'] : []; ?>
                <section class="configuracion-cuentas configuracion-mapeos" data-configuracion-mapeos>
                    <header><div><p class="bandeja-detalle-sobretitulo">Imputación sugerida</p><h2>Mapeos contables</h2></div><p><strong data-total-mapeos><?php echo count($mapeos); ?></strong> subtipos con cuenta definida.</p></header>
                    <form class="configuracion-vinculo-editor configuracion-mapeo-editor" data-editor-mapeo>
                        <input type="hidden" name="mapeo_id">
                        <label>Subtipo de valor
                            <select name="subtipo_valor_zetti_id" required><option value="">Seleccionar subtipo</option><?php foreach ($subtipos as $subtipo): ?><option value="<?php echo $escapar($subtipo['id']); ?>"><?php echo $escapar($subtipo['nombre']); ?> · #<?php echo $escapar($subtipo['id']); ?></option><?php endforeach; ?></select>
                        </label>
                        <label>Cuenta contable
                            <select name="cuenta_zetti_id" required><option value="">Seleccionar cuenta</option><?php foreach ($cuentasContables as $cuenta): ?><option value="<?php echo $escapar($cuenta['id']); ?>"><?php echo $escapar($cuenta['etiqueta']); ?></option><?php endforeach; ?></select>
                        </label>
                        <label>Motivo<input type="text" name="motivo" minlength="3" maxlength="200" required placeholder="Motivo del alta, cambio o retiro"></label>
                        <div class="configuracion-editor-acciones"><button type="submit" data-guardar-mapeo>Crear mapeo</button><button type="button" class="configuracion-secundario" data-cancelar-mapeo>Limpiar</button></div>
                    </form>
                    <div class="bandeja-lineas-region"><table class="bandeja-lineas configuracion-mapeos-tabla"><thead><tr><th scope="col">Subtipo</th><th scope="col">Cuenta contable</th><th scope="col">Versión</th><th scope="col">Acciones</th></tr></thead><tbody>
                    <?php foreach ($mapeos as $mapeo): ?><tr data-mapeo-id="<?php echo $escapar($mapeo['id']); ?>" data-subtipo-mapeo-id="<?php echo $escapar($mapeo['subtipo_id']); ?>" data-cuenta-mapeo-id="<?php echo $escapar($mapeo['cuenta_id']); ?>"><td data-subtipo-mapeo><?php echo $escapar($mapeo['subtipo']); ?> · #<?php echo $escapar($mapeo['subtipo_id']); ?></td><td data-cuenta-mapeo><?php echo $escapar($mapeo['cuenta_etiqueta']); ?></td><td data-version-mapeo><?php echo $escapar($mapeo['version']); ?></td><td class="configuracion-acciones-regla"><button type="button" class="configuracion-secundario" data-editar-mapeo>Editar</button><button type="button" class="configuracion-peligro" data-retirar-mapeo>Retirar</button></td></tr><?php endforeach; ?>
                    </tbody></table></div>
                    <p class="configuracion-ayuda">Cada subtipo admite una única cuenta activa. Editar crea una versión y retirar conserva el histórico.</p>
                </section>
                <?php $asignaciones = is_array($seleccionada['asignaciones'] ?? null) ? $seleccionada['asignaciones'] : []; ?>
                <section class="configuracion-cuentas configuracion-asignaciones" data-configuracion-asignaciones>
                    <header><div><p class="bandeja-detalle-sobretitulo">Distribución de trabajo</p><h2>Responsables automáticos</h2></div><p><strong data-total-asignaciones><?php echo count($asignaciones); ?></strong> subtipos con responsable.</p></header>
                    <form class="configuracion-vinculo-editor configuracion-asignacion-editor" data-editor-asignacion>
                        <input type="hidden" name="regla_asignacion_id">
                        <label>Subtipo de valor<select name="subtipo_valor_zetti_id" required><option value="">Seleccionar subtipo</option><?php foreach ($subtipos as $subtipo): ?><option value="<?php echo $escapar($subtipo['id']); ?>"><?php echo $escapar($subtipo['nombre']); ?> · #<?php echo $escapar($subtipo['id']); ?></option><?php endforeach; ?></select></label>
                        <label>Responsable<select name="usuario_id" required><option value="">Seleccionar usuario</option><?php foreach ($responsables as $responsable): ?><option value="<?php echo $escapar($responsable['id']); ?>"><?php echo $escapar($responsable['usuario']); ?></option><?php endforeach; ?></select></label>
                        <label>Motivo<input type="text" name="motivo" minlength="3" maxlength="200" required placeholder="Motivo del alta, cambio o retiro"></label>
                        <div class="configuracion-editor-acciones"><button type="submit" data-guardar-asignacion>Crear regla</button><button type="button" class="configuracion-secundario" data-cancelar-asignacion>Limpiar</button></div>
                    </form>
                    <div class="bandeja-lineas-region"><table class="bandeja-lineas configuracion-asignaciones-tabla"><thead><tr><th scope="col">Subtipo</th><th scope="col">Responsable</th><th scope="col">Versión</th><th scope="col">Acciones</th></tr></thead><tbody>
                    <?php foreach ($asignaciones as $asignacion): ?><tr data-asignacion-id="<?php echo $escapar($asignacion['id']); ?>" data-subtipo-asignacion-id="<?php echo $escapar($asignacion['subtipo_id']); ?>" data-usuario-asignacion-id="<?php echo $escapar($asignacion['usuario_id']); ?>"><td data-subtipo-asignacion><?php echo $escapar($asignacion['subtipo']); ?> · #<?php echo $escapar($asignacion['subtipo_id']); ?></td><td data-usuario-asignacion><?php echo $escapar($asignacion['usuario']); ?></td><td data-version-asignacion><?php echo $escapar($asignacion['version']); ?></td><td class="configuracion-acciones-regla"><button type="button" class="configuracion-secundario" data-editar-asignacion>Editar</button><button type="button" class="configuracion-peligro" data-retirar-asignacion>Retirar</button></td></tr><?php endforeach; ?>
                    </tbody></table></div>
                    <p class="configuracion-ayuda">Al confirmar una importación, los movimientos clasificados con un subtipo configurado se asignan automáticamente. Sólo aparecen usuarios con acceso efectivo a APP BANCOS.</p>
                </section>
                <div class="configuracion-separador"><p class="bandeja-detalle-sobretitulo">Clasificación</p><h2>Reglas de clasificación</h2></div>
                <form class="configuracion-editor" data-editor-regla>
                    <input type="hidden" name="regla_id">
                    <label>Subtipo de valor
                        <select name="subtipo_valor_zetti_id" required>
                            <option value="">Seleccionar subtipo</option>
                            <?php foreach ($subtipos as $subtipo): ?>
                                <option value="<?php echo $escapar($subtipo['id']); ?>"><?php echo $escapar($subtipo['nombre']); ?> · #<?php echo $escapar($subtipo['id']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Sentido
                        <select name="sentido" required><option value="C">Crédito</option><option value="D">Débito</option><option value="A">Ambos</option></select>
                    </label>
                    <label>Código del extracto
                        <input type="text" name="codigo_extracto" maxlength="60" placeholder="Opcional">
                    </label>
                    <label class="configuracion-check"><input type="checkbox" name="validar_automaticamente"> Validación automática</label>
                    <label>Motivo
                        <input type="text" name="motivo" minlength="3" maxlength="200" required placeholder="Motivo del alta, cambio o retiro">
                    </label>
                    <div class="configuracion-editor-acciones">
                        <button type="submit" data-guardar-regla>Crear regla</button>
                        <button type="button" class="configuracion-secundario" data-cancelar-regla>Limpiar</button>
                    </div>
                </form>
                <div class="bandeja-lineas-region">
                    <table class="bandeja-lineas configuracion-reglas">
                        <thead><tr><th scope="col">Código</th><th scope="col">Sentido</th><th scope="col">Subtipo</th><th scope="col">Versión</th><th scope="col">Validación automática</th><th scope="col">Acciones</th></tr></thead>
                        <tbody>
                        <?php foreach ($seleccionada['reglas'] as $regla): ?>
                            <tr data-regla-id="<?php echo $escapar($regla['id']); ?>" data-subtipo-id="<?php echo $escapar($regla['subtipo_id']); ?>" data-sentido="<?php echo $escapar($regla['sentido']); ?>" data-codigo="<?php echo $escapar($regla['codigo_extracto'] ?? ''); ?>" data-validar-automaticamente="<?php echo $regla['validar_automaticamente'] ? '1' : '0'; ?>">
                                <td data-codigo-regla><?php echo $escapar($regla['codigo_extracto'] ?? 'Sin código'); ?></td>
                                <td data-sentido-regla><?php echo $escapar($regla['sentido']); ?></td>
                                <td data-subtipo-regla><?php echo $escapar($regla['subtipo']); ?> <small>#<?php echo $escapar($regla['subtipo_id']); ?></small></td>
                                <td data-version-regla><?php echo $escapar($regla['version']); ?></td>
                                <td><span class="configuracion-estado-regla" data-estado-regla><?php echo $regla['validar_automaticamente'] ? 'Habilitada' : 'Deshabilitada'; ?></span></td>
                                <td class="configuracion-acciones-regla"><button type="button" class="configuracion-secundario" data-editar-regla>Editar</button><button type="button" class="configuracion-alternar" data-alternar-validacion><?php echo $regla['validar_automaticamente'] ? 'Deshabilitar' : 'Habilitar'; ?></button><button type="button" class="configuracion-peligro" data-retirar-regla>Retirar</button></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <p class="configuracion-ayuda">Editar crea una versión nueva y conserva la anterior inactiva. Retirar aplica baja lógica. El mantenimiento no clasifica retroactivamente movimientos ya importados.</p>
            </section>
        <?php endif; ?>
    <?php endif; ?>
</section>
</main>
