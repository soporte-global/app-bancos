<?php

require_once dirname(__DIR__) . '/src/config.php';
require_once dirname(__DIR__) . '/src/autoload.php';

use AppBancos\Migration\MigracionLegacyIncremental;

function conexionIncremental()
{
    return new PDO(
        'pgsql:host=' . HOST . ';port=' . PORT . ';dbname=' . DBASE,
        USER,
        PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
}

$pdo = conexionIncremental();
$servicio = new MigracionLegacyIncremental($pdo);
$tablas = [
    'bancos_migracion_incremental_ejecucion',
    'bancos_migracion_cuenta_legacy',
    'bancos_migracion_configuracion_legacy',
    'bancos_migracion_periodo_legacy',
    'bancos_migracion_movimiento_legacy',
];
$antes = [];
foreach ($tablas as $tabla) {
    $antes[$tabla] = (int) $pdo->query('SELECT count(*) FROM global_prod.' . $tabla)->fetchColumn();
}

$vista = $servicio->previsualizar();
if (!in_array($vista['estado'], ['LISTO', 'REQUIERE_DECISION'], true)) {
    throw new RuntimeException('Estado de previsualización inválido.');
}
foreach (['cuentas_nuevas', 'configuraciones_nuevas', 'periodos_nuevos', 'filas_movimiento_nuevas'] as $metrica) {
    if (!array_key_exists($metrica, $vista['metricas']) || $vista['metricas'][$metrica] < 0) {
        throw new RuntimeException('Métrica incremental inválida: ' . $metrica);
    }
}
foreach ($tablas as $tabla) {
    $despues = (int) $pdo->query('SELECT count(*) FROM global_prod.' . $tabla)->fetchColumn();
    if ($despues !== $antes[$tabla]) {
        throw new RuntimeException('La previsualización modificó ' . $tabla . '.');
    }
}

$bloqueante = conexionIncremental();
$bloqueante->query('SELECT pg_advisory_lock(1742609221)');
try {
    $fallo = false;
    try {
        $servicio->ejecutar('prueba-bloqueo-incremental');
    } catch (RuntimeException $e) {
        $fallo = strpos($e->getMessage(), 'otro catch-up') !== false;
    }
    if (!$fallo) {
        throw new RuntimeException('No se rechazó una ejecución concurrente.');
    }
} finally {
    $bloqueante->query('SELECT pg_advisory_unlock(1742609221)');
}

if ($vista['estado'] === 'LISTO') {
    $pdo->beginTransaction();
    try {
        $resultado = $servicio->ejecutar('prueba-catchup-rollback-20260922');
        if ($resultado['estado'] !== 'OK') {
            throw new RuntimeException('El catch-up transaccional de prueba no terminó en OK.');
        }
        if ($resultado['despues']['metricas']['filas_movimiento_nuevas'] !== 0
            || $resultado['despues']['metricas']['periodos_nuevos'] !== 0
        ) {
            throw new RuntimeException('El catch-up no agotó el incremento dentro de la prueba.');
        }
        $conteosPrimera = [];
        foreach (['bancos_movimiento_extracto', 'bancos_asociacion_movimiento', 'bancos_reserva_recurso', 'bancos_borrador_asiento'] as $tabla) {
            $conteosPrimera[$tabla] = (int) $pdo->query('SELECT count(*) FROM global_prod.' . $tabla)->fetchColumn();
        }
        $reintento = $servicio->ejecutar('prueba-catchup-rollback-20260922');
        if ($reintento['estado'] !== 'OK' || $reintento['lote_migracion'] !== $resultado['lote_migracion']) {
            throw new RuntimeException('El reintento no devolvió el resultado idempotente original.');
        }
        foreach ($conteosPrimera as $tabla => $cantidad) {
            if ((int) $pdo->query('SELECT count(*) FROM global_prod.' . $tabla)->fetchColumn() !== $cantidad) {
                throw new RuntimeException('El reintento duplicó filas en ' . $tabla . '.');
            }
        }
        $pdo->rollBack();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

echo 'Previsualización incremental sin DML y bloqueo concurrente OK: '
    . $vista['metricas']['filas_movimiento_nuevas'] . " filas pendientes; ejecución completa revertida OK.\n";
