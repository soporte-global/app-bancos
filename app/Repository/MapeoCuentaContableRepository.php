<?php
namespace AppBancos\Repository;

use AppBancos\Application\RecursoNoDisponibleException;
use AppBancos\Infrastructure\EsquemaBancos;
use PDO;
use PDOException;
use RuntimeException;

final class MapeoCuentaContableRepository
{
    private $pdo;
    private $configuraciones;
    private $mapeos;
    private $subtipos;
    private $cuentas;
    private $nodos;

    public function __construct(PDO $pdo, EsquemaBancos $esquema)
    {
        $this->pdo = $pdo;
        $this->configuraciones = $esquema->tablaBancos('bancos_configuracion');
        $this->mapeos = $esquema->tablaBancos('bancos_mapeo_cuenta_contable');
        $this->subtipos = $esquema->tablaLecturaErp('subtipo_valor');
        $this->cuentas = $esquema->tablaLecturaErp('cuenta');
        $this->nodos = $esquema->tablaLecturaErp('nodo');
    }

    public function listar($configuracionId)
    {
        $consulta = $this->pdo->prepare(
            "SELECT m.id::text, m.subtipo_valor_zetti_id::text AS subtipo_id,
                    sv.nombre AS subtipo, m.cuenta_zetti_id::text AS cuenta_id,
                    c.codigo AS cuenta_codigo, c.nombre AS cuenta, n.nombre AS nodo,
                    c.imputable, m.version
             FROM {$this->mapeos} m
             JOIN {$this->subtipos} sv ON sv.id=m.subtipo_valor_zetti_id
             JOIN {$this->cuentas} c ON c.id=m.cuenta_zetti_id
             LEFT JOIN {$this->nodos} n ON n.id=c.nodo
             WHERE m.configuracion_id=:configuracion_id AND m.activo IS TRUE
             ORDER BY sv.nombre, m.subtipo_valor_zetti_id"
        );
        $consulta->execute([':configuracion_id' => (string) $configuracionId]);
        return array_map([$this, 'normalizarMapeo'], $consulta->fetchAll(PDO::FETCH_ASSOC));
    }

    public function listarCuentas()
    {
        $consulta = $this->pdo->query(
            "SELECT c.id::text, c.codigo, c.nombre, n.nombre AS nodo, c.imputable
             FROM {$this->cuentas} c
             LEFT JOIN {$this->nodos} n ON n.id=c.nodo
             ORDER BY c.imputable DESC, n.nombre, c.codigo, c.nombre, c.id"
        );
        return array_map([$this, 'normalizarCuenta'], $consulta->fetchAll(PDO::FETCH_ASSOC));
    }

    public function guardar($configuracionId, $mapeoId, $subtipoId, $cuentaId, $operadorId, $motivo)
    {
        $this->bloquearConfiguracion($configuracionId);
        $this->validarCatalogos($subtipoId, $cuentaId);
        if ($mapeoId === null) {
            $nueva = $this->insertar($configuracionId, $subtipoId, $cuentaId, $operadorId, $motivo, null, 1);
            return $this->respuesta(null, $nueva, true);
        }
        $anterior = $this->bloquearMapeo($configuracionId, $mapeoId);
        if ((string) $anterior['subtipo_valor_zetti_id'] === (string) $subtipoId
            && (string) $anterior['cuenta_zetti_id'] === (string) $cuentaId
        ) {
            return $this->respuesta($anterior, $anterior, false);
        }
        $this->desactivar($anterior['id'], $operadorId);
        $nueva = $this->insertar(
            $configuracionId,
            $subtipoId,
            $cuentaId,
            $operadorId,
            $motivo,
            $anterior['id'],
            (int) $anterior['version'] + 1
        );
        return $this->respuesta($anterior, $nueva, true);
    }

    public function retirar($configuracionId, $mapeoId, $operadorId)
    {
        $this->bloquearConfiguracion($configuracionId);
        $mapeo = $this->bloquearMapeo($configuracionId, $mapeoId);
        $this->desactivar($mapeo['id'], $operadorId);
        return $this->respuesta($mapeo, $mapeo, true) + ['activo' => false];
    }

    private function bloquearConfiguracion($configuracionId)
    {
        $consulta = $this->pdo->prepare("SELECT id FROM {$this->configuraciones} WHERE id=:id AND activo IS TRUE FOR UPDATE");
        $consulta->execute([':id' => (string) $configuracionId]);
        if ($consulta->fetchColumn() === false) {
            throw new RecursoNoDisponibleException('La configuracion no existe o no esta activa.');
        }
    }

    private function bloquearMapeo($configuracionId, $mapeoId)
    {
        $consulta = $this->pdo->prepare(
            "SELECT id,configuracion_id,subtipo_valor_zetti_id,cuenta_zetti_id,version
             FROM {$this->mapeos}
             WHERE id=:mapeo_id AND configuracion_id=:configuracion_id AND activo IS TRUE
             FOR UPDATE"
        );
        $consulta->execute([':mapeo_id' => (string) $mapeoId, ':configuracion_id' => (string) $configuracionId]);
        $fila = $consulta->fetch(PDO::FETCH_ASSOC);
        if ($fila === false) {
            throw new RecursoNoDisponibleException('El mapeo no existe o ya no esta activo en esta configuracion.');
        }
        return $fila;
    }

    private function validarCatalogos($subtipoId, $cuentaId)
    {
        $consulta = $this->pdo->prepare("SELECT 1 FROM {$this->subtipos} WHERE id=:id");
        $consulta->execute([':id' => (int) $subtipoId]);
        if ($consulta->fetchColumn() === false) {
            throw new RecursoNoDisponibleException('El subtipo de valor no existe en ERP.');
        }
        $consulta = $this->pdo->prepare("SELECT 1 FROM {$this->cuentas} WHERE id=:id");
        $consulta->execute([':id' => (string) $cuentaId]);
        if ($consulta->fetchColumn() === false) {
            throw new RecursoNoDisponibleException('La cuenta contable no existe en ERP.');
        }
    }

    private function desactivar($mapeoId, $operadorId)
    {
        $consulta = $this->pdo->prepare(
            "UPDATE {$this->mapeos} SET activo=false,fecha_modificacion=current_timestamp,
                    usuario_modificacion=:operador_id WHERE id=:id AND activo IS TRUE"
        );
        $consulta->execute([':operador_id' => (int) $operadorId, ':id' => (string) $mapeoId]);
        if ($consulta->rowCount() !== 1) {
            throw new RuntimeException('No se pudo conservar el historico del mapeo contable.');
        }
    }

    private function insertar($configuracionId, $subtipoId, $cuentaId, $operadorId, $motivo, $reemplazaId, $version)
    {
        try {
            $consulta = $this->pdo->prepare(
                "INSERT INTO {$this->mapeos}
                    (configuracion_id,subtipo_valor_zetti_id,cuenta_zetti_id,observacion,
                     activo,version,reemplaza_mapeo_id,origen,usuario_creacion,usuario_modificacion)
                 VALUES (:configuracion_id,:subtipo_id,:cuenta_id,:motivo,true,:version,
                         :reemplaza_id,'MANUAL',:operador_id,:operador_id)
                 RETURNING id,configuracion_id,subtipo_valor_zetti_id,cuenta_zetti_id,version,reemplaza_mapeo_id"
            );
            $consulta->execute([
                ':configuracion_id' => (string) $configuracionId,
                ':subtipo_id' => (int) $subtipoId,
                ':cuenta_id' => (string) $cuentaId,
                ':motivo' => $motivo,
                ':version' => (int) $version,
                ':reemplaza_id' => $reemplazaId,
                ':operador_id' => (int) $operadorId,
            ]);
        } catch (PDOException $error) {
            if ($error->getCode() === '23505') {
                throw new RecursoNoDisponibleException('El subtipo ya posee un mapeo contable activo en esta configuracion.');
            }
            throw $error;
        }
        $fila = $consulta->fetch(PDO::FETCH_ASSOC);
        if ($fila === false) {
            throw new RuntimeException('No se pudo crear el mapeo contable.');
        }
        return $fila;
    }

    private function respuesta($anterior, array $actual, $cambio)
    {
        return [
            'configuracion_id' => (string) $actual['configuracion_id'],
            'mapeo_id_anterior' => $anterior === null ? null : (string) $anterior['id'],
            'mapeo_id' => (string) $actual['id'],
            'subtipo_valor_zetti_id' => (string) $actual['subtipo_valor_zetti_id'],
            'cuenta_zetti_id' => (string) $actual['cuenta_zetti_id'],
            'version' => (int) $actual['version'],
            'cambio' => (bool) $cambio,
        ];
    }

    private function normalizarMapeo(array $fila)
    {
        $fila['imputable'] = (bool) $fila['imputable'];
        $fila['version'] = (int) $fila['version'];
        $fila['cuenta_etiqueta'] = $this->etiquetaCuenta($fila);
        return $fila;
    }

    private function normalizarCuenta(array $fila)
    {
        $fila['imputable'] = (bool) $fila['imputable'];
        $fila['etiqueta'] = $this->etiquetaCuenta($fila);
        return $fila;
    }

    private function etiquetaCuenta(array $fila)
    {
        return ($fila['nodo'] ?? 'Sin nodo') . ' · ' . ($fila['codigo'] ?? $fila['cuenta_codigo'] ?? 'Sin codigo')
            . ' · ' . ($fila['nombre'] ?? $fila['cuenta'] ?? 'Sin nombre')
            . ' · ' . ((bool) $fila['imputable'] ? 'Imputable' : 'No imputable');
    }
}
