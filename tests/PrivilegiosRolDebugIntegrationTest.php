<?php

require_once dirname(__DIR__) . '/src/config.php';

function comprobarPrivilegioDebug($condicion, $mensaje)
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
$rol = 'app_bancos_debug_runtime';

$consultaRol = $pdo->prepare(
    'SELECT rolcanlogin, rolsuper, rolcreatedb, rolcreaterole, rolreplication
       FROM pg_roles
      WHERE rolname = :rol'
);
$consultaRol->execute([':rol' => $rol]);
$atributos = $consultaRol->fetch(PDO::FETCH_ASSOC);
comprobarPrivilegioDebug($atributos !== false, 'No existe el rol restringido de debug.');
foreach ($atributos as $atributo => $valor) {
    comprobarPrivilegioDebug($valor === false, 'El rol debug conserva el atributo peligroso ' . $atributo . '.');
}

foreach (['public', 'global_prod', 'global_temp'] as $esquema) {
    $uso = $pdo->query(
        "SELECT has_schema_privilege('{$rol}', '{$esquema}', 'USAGE')"
    )->fetchColumn();
    $crear = $pdo->query(
        "SELECT has_schema_privilege('{$rol}', '{$esquema}', 'CREATE')"
    )->fetchColumn();
    comprobarPrivilegioDebug($uso === true, 'El rol debug no puede usar el esquema ' . $esquema . '.');
    if ($esquema !== 'public') {
        comprobarPrivilegioDebug($crear === false, 'El rol debug puede crear objetos en ' . $esquema . '.');
    }
}
$crearEnPublic = $pdo->query(
    "SELECT has_schema_privilege('{$rol}', 'public', 'CREATE')"
)->fetchColumn();

$resumenTablas = $pdo->query(
    "SELECT n.nspname AS esquema,
            count(*) AS tablas,
            bool_and(has_table_privilege('{$rol}', c.oid, 'SELECT')) AS puede_leer,
            bool_and(has_table_privilege('{$rol}', c.oid, 'INSERT')) AS puede_insertar_todas,
            bool_or(has_table_privilege('{$rol}', c.oid, 'INSERT')) AS puede_insertar_alguna,
            bool_and(has_table_privilege('{$rol}', c.oid, 'UPDATE')) AS puede_actualizar_todas,
            bool_or(has_table_privilege('{$rol}', c.oid, 'UPDATE')) AS puede_actualizar_alguna,
            bool_and(has_table_privilege('{$rol}', c.oid, 'DELETE')) AS puede_eliminar_todas,
            bool_or(has_table_privilege('{$rol}', c.oid, 'DELETE')) AS puede_eliminar_alguna,
            bool_or(has_table_privilege('{$rol}', c.oid, 'TRUNCATE')) AS puede_truncar_alguna
       FROM pg_class c
       JOIN pg_namespace n ON n.oid = c.relnamespace
      WHERE n.nspname IN ('public', 'global_prod', 'global_temp')
        AND c.relkind IN ('r', 'p')
      GROUP BY n.nspname
      ORDER BY n.nspname"
)->fetchAll(PDO::FETCH_ASSOC);

comprobarPrivilegioDebug(count($resumenTablas) === 3, 'No se pudieron auditar los tres esquemas de datos.');
foreach ($resumenTablas as $resumen) {
    comprobarPrivilegioDebug($resumen['puede_leer'] === true, 'El rol debug no puede leer todas las tablas de ' . $resumen['esquema'] . '.');
    comprobarPrivilegioDebug($resumen['puede_truncar_alguna'] === false, 'El rol debug puede truncar tablas de ' . $resumen['esquema'] . '.');
    if ($resumen['esquema'] === 'global_temp') {
        comprobarPrivilegioDebug(
            $resumen['puede_insertar_todas'] === true
            && $resumen['puede_actualizar_todas'] === true
            && $resumen['puede_eliminar_todas'] === true,
            'El rol debug no posee todo el DML requerido en global_temp.'
        );
    } else {
        comprobarPrivilegioDebug(
            $resumen['puede_insertar_alguna'] === false
            && $resumen['puede_actualizar_alguna'] === false
            && $resumen['puede_eliminar_alguna'] === false,
            'El rol debug conserva DML sobre ' . $resumen['esquema'] . '.'
        );
    }
}

$pdo->beginTransaction();
try {
    $pdo->exec('SET LOCAL ROLE app_bancos_debug_runtime');
    $pdo->exec('UPDATE global_temp.bancos_movimiento_extracto SET id = id WHERE false');
    $pdo->exec('SAVEPOINT verificar_bloqueo_productivo');
    try {
        $pdo->exec('UPDATE global_prod.bancos_movimiento_extracto SET id = id WHERE false');
        throw new RuntimeException('El rol debug pudo ejecutar DML sobre global_prod.');
    } catch (PDOException $error) {
        comprobarPrivilegioDebug(
            $error->getCode() === '42501',
            'El bloqueo productivo devolvio un error inesperado: ' . $error->getMessage()
        );
        $pdo->exec('ROLLBACK TO SAVEPOINT verificar_bloqueo_productivo');
    }
    $pdo->rollBack();
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $error;
}

echo "Privilegios del rol debug OK";
if ($crearEnPublic === true) {
    echo " (advertencia: CREATE en public heredado de PUBLIC requiere decision DBA)";
}
echo "\n";
