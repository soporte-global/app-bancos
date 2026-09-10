<?php
$datos = $contextoApp['data']->importaciones ?? (object) [];
$cuentas = is_array($datos->cuentas ?? null) ? $datos->cuentas : [];
$configuraciones = is_array($datos->configuraciones ?? null) ? $datos->configuraciones : [];
$habilitada = (bool) ($datos->habilitada ?? false);
$error = $datos->error ?? null;
$escapar = static function ($valor) {
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
};
?>
<main class="afterheader bandeja-page">
<section class="bancos-app importacion-page" data-importacion-page>
    <header class="importacion-encabezado">
        <p class="bandeja-detalle-sobretitulo">Extractos mensuales</p>
        <h1>Importar extracto</h1>
        <p>Validá el archivo completo, revisá el resumen y confirmá la creación del lote y sus movimientos.</p>
    </header>

    <?php if ($error !== null): ?>
        <section class="bandeja-estado bandeja-estado--error" role="alert"><h2>No se pudo cargar el contexto</h2><p><?php echo $escapar($error); ?></p></section>
    <?php elseif (!$habilitada): ?>
        <section class="bandeja-estado" role="status"><h2>Importación deshabilitada</h2><p>El flujo sólo está disponible actualmente en el sandbox.</p></section>
    <?php else: ?>
        <form class="importacion-formulario" enctype="multipart/form-data" data-form-importacion>
            <label>Cuenta bancaria
                <select name="cuenta_bancaria_id" required data-importacion-cuenta>
                    <option value="">Seleccionar cuenta configurada</option>
                    <?php foreach ($cuentas as $cuenta): ?>
                        <option value="<?php echo $escapar($cuenta['id']); ?>"><?php echo $escapar($cuenta['etiqueta']); ?> · <?php echo $escapar($cuenta['configuraciones']); ?> configuraciones</option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Configuración
                <select name="configuracion_id" required data-importacion-configuracion disabled>
                    <option value="">Seleccionar configuración</option>
                    <?php foreach ($configuraciones as $configuracion): ?>
                        <option value="<?php echo $escapar($configuracion['id']); ?>" data-cuenta-id="<?php echo $escapar($configuracion['cuenta_bancaria_id']); ?>" hidden><?php echo $escapar($configuracion['etiqueta']); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Período
                <input type="month" required data-importacion-periodo>
            </label>
            <label>Archivo CSV o TSV
                <input type="file" name="archivo" accept=".csv,.tsv,text/csv,text/tab-separated-values" required>
            </label>
            <button type="submit">Analizar archivo</button>
            <p class="bandeja-accion-estado" role="status" aria-live="polite" data-importacion-estado></p>
        </form>

        <aside class="importacion-formato">
            <h2>Contrato del archivo</h2>
            <p>UTF-8, hasta 5 MB y 10.000 movimientos. Columnas obligatorias: <code>fecha_operacion</code>, <code>descripcion</code>, <code>credito</code> y <code>debito</code>. Opcionales: <code>referencia</code> y <code>codigo_extracto</code>.</p>
            <p>Fechas: AAAA-MM-DD, DD/MM/AAAA o DD-MM-AAAA. Cada fila debe pertenecer al período y tener un importe positivo solamente en crédito o débito, con hasta dos decimales.</p>
        </aside>

        <section class="importacion-resultado" data-importacion-resultado hidden>
            <h2>Resultado de validación</h2>
            <dl class="importacion-metricas" data-importacion-metricas></dl>
            <div data-importacion-errores></div>
            <div class="bandeja-lineas-region" data-importacion-tabla></div>
            <p data-importacion-confirmacion></p>
            <div class="importacion-acciones">
                <button type="button" class="importacion-confirmar" data-confirmar-importacion hidden>Confirmar importación</button>
                <button type="button" class="importacion-descargar" data-descargar-errores hidden>Descargar errores CSV</button>
            </div>
        </section>
    <?php endif; ?>
</section>
</main>
