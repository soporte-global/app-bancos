<?php
namespace AppBancos\Repository;

use AppBancos\Infrastructure\EsquemaBancos;
use PDO;

final class ContextoBandejaMensualRepository
{
    private $pdo;
    private $esquemas;

    public function __construct(PDO $pdo, EsquemaBancos $esquemas)
    {
        $this->pdo = $pdo;
        $this->esquemas = $esquemas;
    }

    public function consultar()
    {
        $importacion = $this->esquemas->tablaBancos('bancos_importacion_extracto');
        $entidad = $this->esquemas->tablaLecturaErp('entidad');
        $cuentaBancaria = $this->esquemas->tablaLecturaErp('cuenta_bancaria');
        $tipoCuenta = $this->esquemas->tablaLecturaErp('tipo_cuenta_bancaria');
        $nodo = $this->esquemas->tablaLecturaErp('nodo');

        $sqlCuentas = sprintf(
            "SELECT DISTINCT
                    i.cuenta_bancaria_zetti_id AS id,
                    i.inicio_periodo,
                    cuenta.codigo,
                    cuenta.nombre AS cuenta_nombre,
                    banco.nombre AS banco_nombre,
                    tipo.nombre AS tipo_nombre,
                    n.nombre AS nodo_nombre,
                    n.codigo_jerarquico
             FROM %1\$s AS i
             JOIN %2\$s AS cuenta ON cuenta.id = i.cuenta_bancaria_zetti_id
             JOIN %3\$s AS cb ON cb.id = cuenta.id
             JOIN %2\$s AS banco ON banco.id = cb.banco
             JOIN %4\$s AS tipo ON tipo.id = cb.tipo_cuenta_bancaria
             JOIN %5\$s AS n ON n.id = cuenta.nodo_creacion
             WHERE cuenta.codigo IS NOT NULL
               AND btrim(cuenta.codigo::text) <> ''
             ORDER BY n.codigo_jerarquico, banco.nombre, cuenta.nombre,
                      i.inicio_periodo DESC",
            $importacion,
            $entidad,
            $cuentaBancaria,
            $tipoCuenta,
            $nodo
        );
        $filas = $this->pdo->query($sqlCuentas)->fetchAll(PDO::FETCH_ASSOC);
        $cuentas = [];
        foreach ($filas as $fila) {
            $id = (string) $fila['id'];
            if (!isset($cuentas[$id])) {
                $cuentas[$id] = [
                    'id' => (int) $fila['id'],
                    'codigo' => $fila['codigo'],
                    'nombre' => $fila['cuenta_nombre'],
                    'banco' => $fila['banco_nombre'],
                    'tipo' => $fila['tipo_nombre'],
                    'nodo' => $fila['nodo_nombre'],
                    'etiqueta' => $fila['nodo_nombre'] . ' · ' . $fila['banco_nombre'] . ' · '
                        . $fila['tipo_nombre'] . ' · ' . $fila['cuenta_nombre'] . ' · ' . $fila['codigo'],
                    'periodos' => [],
                ];
            }
            $cuentas[$id]['periodos'][] = $fila['inicio_periodo'];
        }

        $sqlResponsables =
            "SELECT DISTINCT rl.id, rl.usuario, lower(rl.usuario) AS orden
             FROM global_prod.rrhh_login AS rl
             WHERE rl.habilitado IS TRUE
               AND rl.fecha_eliminacion IS NULL
               AND EXISTS (
                   SELECT 1
                   FROM global_prod.hub_permisos_efectivos_usuario e
                   JOIN global_prod.hub_permisos p ON p.id = e.permiso
                   WHERE e.usuario = rl.id
                     AND p.aplicacion = " . (int) ID_APLICACION . "
                     AND p.tipo_permiso = 1
                     AND p.ignorado IS NULL
               )
             ORDER BY lower(rl.usuario), rl.id";
        $responsables = $this->pdo->query($sqlResponsables)->fetchAll(PDO::FETCH_ASSOC);

        return [
            'cuentas' => array_values($cuentas),
            'responsables' => array_map(static function (array $fila) {
                return ['id' => (int) $fila['id'], 'usuario' => $fila['usuario']];
            }, $responsables),
        ];
    }
}
