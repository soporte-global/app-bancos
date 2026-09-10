<?php
namespace AppBancos\Repository;

use AppBancos\Infrastructure\EsquemaBancos;
use PDO;

final class ContextoImportacionRepository
{
    private $pdo;
    private $esquema;

    public function __construct(PDO $pdo, EsquemaBancos $esquema)
    {
        $this->pdo = $pdo;
        $this->esquema = $esquema;
    }

    public function consultar()
    {
        $configuracion = $this->esquema->tablaBancos('bancos_configuracion');
        $vinculo = $this->esquema->tablaBancos('bancos_configuracion_cuenta');
        $lote = $this->esquema->tablaBancos('bancos_importacion_extracto');
        $entidad = $this->esquema->tablaLecturaErp('entidad');
        $cuentaBancaria = $this->esquema->tablaLecturaErp('cuenta_bancaria');
        $tipoCuenta = $this->esquema->tablaLecturaErp('tipo_cuenta_bancaria');
        $nodo = $this->esquema->tablaLecturaErp('nodo');
        $consulta = $this->pdo->query(
            "WITH vinculos AS (
                 SELECT configuracion_id, cuenta_bancaria_zetti_id FROM {$vinculo}
                 UNION
                 SELECT configuracion_id, cuenta_bancaria_zetti_id FROM {$lote}
             )
             SELECT e.id::text AS id, e.codigo, e.nombre AS cuenta,
                    banco.nombre AS banco, tipo.nombre AS tipo, n.nombre AS nodo,
                    count(DISTINCT c.id) AS configuraciones
             FROM vinculos vc
             JOIN {$configuracion} c ON c.id = vc.configuracion_id AND c.activo IS TRUE
             JOIN {$cuentaBancaria} cb ON cb.id = vc.cuenta_bancaria_zetti_id
             JOIN {$entidad} e ON e.id = cb.id
             JOIN {$entidad} banco ON banco.id = cb.banco
             JOIN {$tipoCuenta} tipo ON tipo.id = cb.tipo_cuenta_bancaria
             JOIN {$nodo} n ON n.id = e.nodo_creacion
             WHERE e.codigo IS NOT NULL AND btrim(e.codigo) <> ''
             GROUP BY e.id, e.codigo, e.nombre, banco.nombre, tipo.nombre, n.nombre
             ORDER BY n.nombre, banco.nombre, e.nombre, e.id"
        );
        return array_map(static function (array $fila) {
            $fila['configuraciones'] = (int) $fila['configuraciones'];
            $fila['etiqueta'] = $fila['nodo'] . ' · ' . $fila['banco'] . ' · '
                . $fila['tipo'] . ' · ' . $fila['cuenta'] . ' · ' . $fila['codigo'];
            return $fila;
        }, $consulta->fetchAll(PDO::FETCH_ASSOC));
    }

    public function consultarConfiguraciones()
    {
        $configuracion = $this->esquema->tablaBancos('bancos_configuracion');
        $vinculo = $this->esquema->tablaBancos('bancos_configuracion_cuenta');
        $lote = $this->esquema->tablaBancos('bancos_importacion_extracto');
        $entidad = $this->esquema->tablaLecturaErp('entidad');
        $nodo = $this->esquema->tablaLecturaErp('nodo');
        $consulta = $this->pdo->query(
            "WITH vinculos AS (
                 SELECT configuracion_id, cuenta_bancaria_zetti_id FROM {$vinculo}
                 UNION
                 SELECT configuracion_id, cuenta_bancaria_zetti_id FROM {$lote}
             )
             SELECT DISTINCT vc.cuenta_bancaria_zetti_id::text AS cuenta_bancaria_id,
                    c.id::text AS id, c.alcance,
                    banco.nombre AS banco, n.nombre AS nodo,
                    c.moneda::text AS moneda_id,
                    c.cuenta_contable_zetti_id::text AS cuenta_contable_id
             FROM vinculos vc
             JOIN {$configuracion} c ON c.id = vc.configuracion_id AND c.activo IS TRUE
             LEFT JOIN {$entidad} banco ON banco.id = c.banco_zetti_id
             LEFT JOIN {$nodo} n ON n.id = c.nodo_zetti_id
             ORDER BY vc.cuenta_bancaria_zetti_id::text, c.id::text"
        );
        return array_map(static function (array $fila) {
            $partes = ['Configuración #' . $fila['id'], $fila['alcance']];
            if ($fila['banco'] !== null) {
                $partes[] = 'Banco ' . $fila['banco'];
            }
            if ($fila['nodo'] !== null) {
                $partes[] = 'Nodo ' . $fila['nodo'];
            }
            if ($fila['moneda_id'] !== null) {
                $partes[] = 'Moneda ID ' . $fila['moneda_id'];
            }
            if ($fila['cuenta_contable_id'] !== null) {
                $partes[] = 'Cuenta contable ' . $fila['cuenta_contable_id'];
            }
            $fila['etiqueta'] = implode(' · ', $partes);
            return $fila;
        }, $consulta->fetchAll(PDO::FETCH_ASSOC));
    }
}
