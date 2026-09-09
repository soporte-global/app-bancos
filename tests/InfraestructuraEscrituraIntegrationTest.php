<?php

require_once dirname(__DIR__) . '/src/config.php';
require_once RUTA . '/src/autoload.php';

use AppBancos\Application\ConflictoIdempotenciaException;
use AppBancos\Application\EjecutorComandoIdempotente;
use AppBancos\Infrastructure\EsquemaBancos;
use AppBancos\Repository\AuditoriaRepository;
use AppBancos\Repository\IdempotenciaRepository;

function comprobarInfraestructura($condicion, $mensaje)
{
    if (!$condicion) {
        throw new RuntimeException($mensaje);
    }
}

$pdo = new PDO(
    'pgsql:host=' . HOST . ';port=' . PORT . ';dbname=' . DBASE,
    USER,
    PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$esquema = EsquemaBancos::desdeConfiguracion(['bancos_debug' => true]);
$usuarioId = (int) $pdo->query(
    "SELECT id FROM global_prod.rrhh_login
     WHERE lower(usuario) = 'hvega' AND habilitado IS TRUE AND fecha_eliminacion IS NULL"
)->fetchColumn();
comprobarInfraestructura($usuarioId > 0, 'No se encontro la cuenta hvega para la prueba.');

$clave = 'test-' . bin2hex(random_bytes(12));
$claveFalla = 'test-' . bin2hex(random_bytes(12));
$ejecuciones = 0;
$ejecutor = new EjecutorComandoIdempotente(
    $pdo,
    new IdempotenciaRepository($pdo, $esquema),
    new AuditoriaRepository($pdo, $esquema)
);
$entrada = ['movimiento_id' => 99, 'datos' => ['b' => 2, 'a' => 1]];
$evento = [
    'accion' => 'INFRAESTRUCTURA_PROBAR',
    'recurso_tipo' => 'PRUEBA',
    'recurso_id' => $clave,
    'detalles' => ['origen' => 'test'],
];
$comando = function () use (&$ejecuciones) {
    $ejecuciones++;
    return [
        'codigo_http' => 201,
        'respuesta' => ['id' => 99, 'estado' => 'SIMULADO'],
    ];
};

try {
    $primera = $ejecutor->ejecutar('infraestructura.probar', $clave, $usuarioId, $entrada, $evento, $comando);
    $segunda = $ejecutor->ejecutar(
        'infraestructura.probar',
        $clave,
        $usuarioId,
        ['datos' => ['a' => 1, 'b' => 2], 'movimiento_id' => 99],
        $evento,
        $comando
    );
    comprobarInfraestructura($ejecuciones === 1, 'El reintento ejecuto el comando nuevamente.');
    comprobarInfraestructura($primera['repetida'] === false, 'La primera solicitud fue marcada como repetida.');
    comprobarInfraestructura($segunda['repetida'] === true, 'El reintento no fue reconocido.');
    comprobarInfraestructura($segunda['respuesta'] === $primera['respuesta'], 'El reintento no devolvio la respuesta persistida.');

    try {
        $ejecutor->ejecutar(
            'infraestructura.probar',
            $clave,
            $usuarioId,
            ['movimiento_id' => 100],
            $evento,
            $comando
        );
        throw new RuntimeException('Se reutilizo una clave con otro contenido.');
    } catch (ConflictoIdempotenciaException $esperada) {
    }

    $consulta = $pdo->prepare(
        "SELECT count(*)
         FROM global_temp.bancos_evento_auditoria a
         JOIN global_temp.bancos_solicitud_idempotente s ON s.id = a.solicitud_id
         WHERE s.operacion = 'infraestructura.probar' AND s.clave = :clave"
    );
    $consulta->execute([':clave' => $clave]);
    comprobarInfraestructura((int) $consulta->fetchColumn() === 1, 'La auditoria no es unica por ejecucion efectiva.');

    try {
        $ejecutor->ejecutar(
            'infraestructura.probar',
            $claveFalla,
            $usuarioId,
            ['movimiento_id' => 101],
            $evento,
            function () {
                throw new RuntimeException('Falla simulada dentro del comando.');
            }
        );
        throw new RuntimeException('La falla simulada no fue propagada.');
    } catch (RuntimeException $esperada) {
        comprobarInfraestructura(
            $esperada->getMessage() === 'Falla simulada dentro del comando.',
            'La prueba capturo un error distinto de la falla simulada.'
        );
    }
    $consulta = $pdo->prepare(
        "SELECT count(*) FROM global_temp.bancos_solicitud_idempotente WHERE clave = :clave"
    );
    $consulta->execute([':clave' => $claveFalla]);
    comprobarInfraestructura((int) $consulta->fetchColumn() === 0, 'La transaccion fallida dejo una solicitud parcial.');
} finally {
    $pdo->beginTransaction();
    $borrar = $pdo->prepare(
        "DELETE FROM global_temp.bancos_evento_auditoria
         WHERE solicitud_id IN (
             SELECT id FROM global_temp.bancos_solicitud_idempotente
             WHERE operacion = 'infraestructura.probar' AND clave IN (:clave, :clave_falla)
         )"
    );
    $borrar->execute([':clave' => $clave, ':clave_falla' => $claveFalla]);
    $borrar = $pdo->prepare(
        "DELETE FROM global_temp.bancos_solicitud_idempotente
         WHERE operacion = 'infraestructura.probar' AND clave IN (:clave, :clave_falla)"
    );
    $borrar->execute([':clave' => $clave, ':clave_falla' => $claveFalla]);
    $pdo->commit();
}

echo "Infraestructura de escritura debug OK\n";
