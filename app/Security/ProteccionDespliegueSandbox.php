<?php

namespace AppBancos\Security;

use PDO;
use RuntimeException;

final class ProteccionDespliegueSandbox
{
    public static function verificar(PDO $pdo, $modo, $entorno)
    {
        if ($modo !== 'sandbox' || $entorno !== 'prod') {
            return;
        }

        $sql = "SELECT current_user AS usuario,
                       COALESCE((SELECT usesuper FROM pg_user WHERE usename = current_user), false) AS superusuario,
                       pg_has_role(current_user, 'app_bancos_debug_runtime', 'member') AS miembro_rol,
                       has_table_privilege(current_user, 'global_temp.bancos_movimiento_extracto', 'INSERT') AS escribe_sandbox,
                       has_table_privilege(current_user, 'global_prod.bancos_movimiento_extracto', 'INSERT') AS escribe_bancos_productivo,
                       has_table_privilege(current_user, 'public.valor', 'UPDATE') AS escribe_erp_productivo";
        $estado = $pdo->query($sql)->fetch(PDO::FETCH_ASSOC);
        if (!$estado
            || self::booleano($estado['superusuario'])
            || !self::booleano($estado['miembro_rol'])
            || !self::booleano($estado['escribe_sandbox'])
            || self::booleano($estado['escribe_bancos_productivo'])
            || self::booleano($estado['escribe_erp_productivo'])
        ) {
            throw new RuntimeException(
                'La conexión no cumple el perfil restringido del sandbox publicado.'
            );
        }
    }

    private static function booleano($valor)
    {
        return $valor === true || $valor === 1 || $valor === '1' || $valor === 't';
    }
}
