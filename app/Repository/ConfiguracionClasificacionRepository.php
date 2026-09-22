<?php
namespace AppBancos\Repository;

use AppBancos\Application\RecursoNoDisponibleException;
use AppBancos\Infrastructure\EsquemaBancos;
use PDO;
use PDOException;
use RuntimeException;

final class ConfiguracionClasificacionRepository
{
    private $pdo;
    private $configuraciones;
    private $vinculos;
    private $reglas;
    private $subtipos;
    private $entidades;
    private $nodos;
    private $cuentasBancarias;
    private $tiposCuentaBancaria;

    public function __construct(PDO $pdo, EsquemaBancos $esquema)
    {
        $this->pdo = $pdo;
        $this->configuraciones = $esquema->tablaBancos('bancos_configuracion');
        $this->vinculos = $esquema->tablaBancos('bancos_configuracion_cuenta');
        $this->reglas = $esquema->tablaBancos('bancos_regla_clasificacion');
        $this->subtipos = $esquema->tablaLecturaErp('subtipo_valor');
        $this->entidades = $esquema->tablaLecturaErp('entidad');
        $this->nodos = $esquema->tablaLecturaErp('nodo');
        $this->cuentasBancarias = $esquema->tablaLecturaErp('cuenta_bancaria');
        $this->tiposCuentaBancaria = $esquema->tablaLecturaErp('tipo_cuenta_bancaria');
    }

    public function listarConfiguraciones()
    {
        $consulta = $this->pdo->query(
            "WITH cuentas AS (
                 SELECT configuracion_id, count(DISTINCT cuenta_bancaria_zetti_id) AS cantidad
                 FROM {$this->vinculos}
                 WHERE activo IS TRUE
                 GROUP BY configuracion_id
             ), reglas AS (
                 SELECT configuracion_id, count(*) AS cantidad,
                        count(*) FILTER (WHERE validar_automaticamente IS TRUE) AS automaticas
                 FROM {$this->reglas}
                 WHERE activo IS TRUE
                 GROUP BY configuracion_id
             )
             SELECT c.id::text, c.alcance, banco.nombre AS banco, n.nombre AS nodo,
                    c.moneda::text AS moneda_id,
                    COALESCE(cuentas.cantidad, 0) AS cuentas,
                    COALESCE(reglas.cantidad, 0) AS reglas,
                    COALESCE(reglas.automaticas, 0) AS automaticas
             FROM {$this->configuraciones} c
             LEFT JOIN cuentas ON cuentas.configuracion_id = c.id
             LEFT JOIN reglas ON reglas.configuracion_id = c.id
             LEFT JOIN {$this->entidades} banco ON banco.id = c.banco_zetti_id
             LEFT JOIN {$this->nodos} n ON n.id = c.nodo_zetti_id
             WHERE c.activo IS TRUE
             ORDER BY c.id"
        );
        return array_map([$this, 'normalizarConfiguracion'], $consulta->fetchAll(PDO::FETCH_ASSOC));
    }

    public function consultar($configuracionId)
    {
        $consulta = $this->pdo->prepare(
            "SELECT c.id::text, c.alcance, banco.nombre AS banco, n.nombre AS nodo,
                    c.moneda::text AS moneda_id, c.cuenta_contable_zetti_id::text AS cuenta_contable_id
             FROM {$this->configuraciones} c
             LEFT JOIN {$this->entidades} banco ON banco.id = c.banco_zetti_id
             LEFT JOIN {$this->nodos} n ON n.id = c.nodo_zetti_id
             WHERE c.id = :configuracion_id AND c.activo IS TRUE"
        );
        $consulta->execute([':configuracion_id' => (string) $configuracionId]);
        $configuracion = $consulta->fetch(PDO::FETCH_ASSOC);
        if ($configuracion === false) {
            throw new RecursoNoDisponibleException('La configuracion no existe o no esta activa.');
        }

        $consulta = $this->pdo->prepare(
            "SELECT r.id::text, r.subtipo_valor_zetti_id::text AS subtipo_id,
                    sv.nombre AS subtipo, r.sentido, r.codigo_extracto,
                    r.validar_automaticamente, r.version, r.reemplaza_regla_id::text,
                    r.fecha_modificacion
             FROM {$this->reglas} r
             LEFT JOIN {$this->subtipos} sv ON sv.id = r.subtipo_valor_zetti_id
             WHERE r.configuracion_id = :configuracion_id
               AND r.activo IS TRUE
             ORDER BY COALESCE(r.codigo_extracto, ''), r.sentido, r.subtipo_valor_zetti_id, r.id"
        );
        $consulta->execute([':configuracion_id' => (string) $configuracionId]);
        $reglas = array_map(static function (array $fila) {
            $fila['validar_automaticamente'] = (bool) $fila['validar_automaticamente'];
            $fila['subtipo'] = $fila['subtipo'] === null
                ? 'Subtipo #' . $fila['subtipo_id']
                : $fila['subtipo'];
            return $fila;
        }, $consulta->fetchAll(PDO::FETCH_ASSOC));

        $configuracion['reglas'] = $reglas;
        $configuracion['cuentas'] = $this->listarCuentasVinculadas($configuracionId);
        $configuracion['total_reglas'] = count($reglas);
        $configuracion['automaticas'] = count(array_filter($reglas, static function (array $regla) {
            return $regla['validar_automaticamente'];
        }));
        return $configuracion;
    }

    public function listarSubtipos()
    {
        $consulta = $this->pdo->query(
            "SELECT id::text, nombre
             FROM {$this->subtipos}
             ORDER BY nombre, id"
        );
        return $consulta->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listarCuentasBancarias()
    {
        $consulta = $this->pdo->query($this->sqlCuentaBancaria() . " ORDER BY n.nombre, banco.nombre, e.nombre, e.id");
        return array_map([$this, 'normalizarCuenta'], $consulta->fetchAll(PDO::FETCH_ASSOC));
    }

    public function vincularCuenta($configuracionId, $cuentaId, $operadorId)
    {
        $this->bloquearConfiguracion($configuracionId);
        $cuenta = $this->consultarCuentaErp($cuentaId);
        $consulta = $this->pdo->prepare(
            "SELECT id, activo FROM {$this->vinculos}
             WHERE configuracion_id = :configuracion_id
               AND cuenta_bancaria_zetti_id = :cuenta_id
             FOR UPDATE"
        );
        $consulta->execute([
            ':configuracion_id' => (string) $configuracionId,
            ':cuenta_id' => (string) $cuentaId,
        ]);
        $vinculo = $consulta->fetch(PDO::FETCH_ASSOC);
        if ($vinculo !== false && (bool) $vinculo['activo']) {
            return $this->respuestaCuenta($configuracionId, $cuenta, false, true);
        }
        if ($vinculo === false) {
            $consulta = $this->pdo->prepare(
                "INSERT INTO {$this->vinculos}
                    (configuracion_id, cuenta_bancaria_zetti_id, activo, origen,
                     usuario_creacion, usuario_modificacion)
                 VALUES (:configuracion_id, :cuenta_id, true, 'MANUAL',
                         :operador_id, :operador_id)"
            );
            $consulta->execute([
                ':configuracion_id' => (string) $configuracionId,
                ':cuenta_id' => (string) $cuentaId,
                ':operador_id' => (int) $operadorId,
            ]);
        } else {
            $consulta = $this->pdo->prepare(
                "UPDATE {$this->vinculos}
                 SET activo = true, origen = 'MANUAL', fecha_modificacion = current_timestamp,
                     usuario_modificacion = :operador_id
                 WHERE id = :vinculo_id"
            );
            $consulta->execute([
                ':operador_id' => (int) $operadorId,
                ':vinculo_id' => (string) $vinculo['id'],
            ]);
        }
        if ($consulta->rowCount() !== 1) {
            throw new RuntimeException('No se pudo vincular la cuenta bancaria.');
        }
        return $this->respuestaCuenta($configuracionId, $cuenta, true, true);
    }

    public function desvincularCuenta($configuracionId, $cuentaId, $operadorId)
    {
        $this->bloquearConfiguracion($configuracionId);
        $cuenta = $this->consultarCuentaErp($cuentaId);
        $consulta = $this->pdo->prepare(
            "SELECT id FROM {$this->vinculos}
             WHERE configuracion_id = :configuracion_id
               AND cuenta_bancaria_zetti_id = :cuenta_id
               AND activo IS TRUE
             FOR UPDATE"
        );
        $consulta->execute([
            ':configuracion_id' => (string) $configuracionId,
            ':cuenta_id' => (string) $cuentaId,
        ]);
        $vinculoId = $consulta->fetchColumn();
        if ($vinculoId === false) {
            throw new RecursoNoDisponibleException('La cuenta no esta vinculada activamente a esta configuracion.');
        }
        $consulta = $this->pdo->prepare(
            "UPDATE {$this->vinculos}
             SET activo = false, fecha_modificacion = current_timestamp,
                 usuario_modificacion = :operador_id
             WHERE id = :vinculo_id AND activo IS TRUE"
        );
        $consulta->execute([
            ':operador_id' => (int) $operadorId,
            ':vinculo_id' => (string) $vinculoId,
        ]);
        if ($consulta->rowCount() !== 1) {
            throw new RuntimeException('No se pudo desvincular la cuenta bancaria.');
        }
        return $this->respuestaCuenta($configuracionId, $cuenta, true, false);
    }

    public function actualizarValidacionAutomatica($configuracionId, $reglaId, $validarAutomaticamente, $operadorId)
    {
        $regla = $this->bloquearReglaActiva($configuracionId, $reglaId);
        $anterior = (bool) $regla['validar_automaticamente'];
        if ($anterior === (bool) $validarAutomaticamente) {
            return $this->respuestaRegla($regla, $regla, false) + [
                'validar_automaticamente_anterior' => $anterior,
            ];
        }
        $nueva = $this->reemplazarRegla($regla, [
            'subtipo_valor_zetti_id' => (string) $regla['subtipo_valor_zetti_id'],
            'sentido' => (string) $regla['sentido'],
            'codigo_extracto' => $regla['codigo_extracto'],
            'validar_automaticamente' => (bool) $validarAutomaticamente,
        ], $operadorId, 'Cambio de validacion automatica');
        return $this->respuestaRegla($regla, $nueva, true) + [
            'validar_automaticamente_anterior' => $anterior,
        ];
    }

    public function guardarRegla($configuracionId, $reglaId, array $datos, $operadorId, $motivo)
    {
        $this->bloquearConfiguracion($configuracionId);
        $this->validarSubtipo($datos['subtipo_valor_zetti_id']);
        if ($reglaId === null) {
            $nueva = $this->insertarRegla(
                $configuracionId,
                $datos,
                $operadorId,
                $motivo,
                null,
                1
            );
            return $this->respuestaRegla(null, $nueva, true);
        }

        $anterior = $this->bloquearReglaActiva($configuracionId, $reglaId, false);
        $sinCambios = (string) $anterior['subtipo_valor_zetti_id'] === (string) $datos['subtipo_valor_zetti_id']
            && (string) $anterior['sentido'] === (string) $datos['sentido']
            && $anterior['codigo_extracto'] === $datos['codigo_extracto']
            && (bool) $anterior['validar_automaticamente'] === (bool) $datos['validar_automaticamente'];
        if ($sinCambios) {
            return $this->respuestaRegla($anterior, $anterior, false);
        }
        $nueva = $this->reemplazarRegla($anterior, $datos, $operadorId, $motivo);
        return $this->respuestaRegla($anterior, $nueva, true);
    }

    public function retirarRegla($configuracionId, $reglaId, $operadorId)
    {
        $regla = $this->bloquearReglaActiva($configuracionId, $reglaId);
        $consulta = $this->pdo->prepare(
            "UPDATE {$this->reglas}
             SET activo = false, fecha_modificacion = current_timestamp,
                 usuario_modificacion = :operador_id
             WHERE id = :regla_id AND activo IS TRUE"
        );
        $consulta->execute([':operador_id' => (int) $operadorId, ':regla_id' => (string) $reglaId]);
        if ($consulta->rowCount() !== 1) {
            throw new RuntimeException('No se pudo retirar la regla de clasificacion.');
        }
        return $this->respuestaRegla($regla, $regla, true) + ['activo' => false];
    }

    private function bloquearConfiguracion($configuracionId)
    {
        $consulta = $this->pdo->prepare(
            "SELECT id FROM {$this->configuraciones}
             WHERE id = :configuracion_id AND activo IS TRUE
             FOR UPDATE"
        );
        $consulta->execute([':configuracion_id' => (string) $configuracionId]);
        if ($consulta->fetchColumn() === false) {
            throw new RecursoNoDisponibleException('La configuracion no existe o no esta activa.');
        }
    }

    private function listarCuentasVinculadas($configuracionId)
    {
        $consulta = $this->pdo->prepare(
            $this->sqlCuentaBancaria()
            . " JOIN {$this->vinculos} cc ON cc.cuenta_bancaria_zetti_id = cb.id
                AND cc.configuracion_id = :configuracion_id AND cc.activo IS TRUE
                ORDER BY n.nombre, banco.nombre, e.nombre, e.id"
        );
        $consulta->execute([':configuracion_id' => (string) $configuracionId]);
        return array_map([$this, 'normalizarCuenta'], $consulta->fetchAll(PDO::FETCH_ASSOC));
    }

    private function consultarCuentaErp($cuentaId)
    {
        $consulta = $this->pdo->prepare($this->sqlCuentaBancaria() . ' WHERE cb.id = :cuenta_id');
        $consulta->execute([':cuenta_id' => (string) $cuentaId]);
        $cuenta = $consulta->fetch(PDO::FETCH_ASSOC);
        if ($cuenta === false) {
            throw new RecursoNoDisponibleException('La cuenta bancaria no existe en ERP.');
        }
        return $this->normalizarCuenta($cuenta);
    }

    private function sqlCuentaBancaria()
    {
        return "SELECT cb.id::text, e.codigo, e.nombre AS cuenta,
                       banco.nombre AS banco, tipo.nombre AS tipo, n.nombre AS nodo
                FROM {$this->cuentasBancarias} cb
                JOIN {$this->entidades} e ON e.id = cb.id
                JOIN {$this->entidades} banco ON banco.id = cb.banco
                JOIN {$this->tiposCuentaBancaria} tipo ON tipo.id = cb.tipo_cuenta_bancaria
                JOIN {$this->nodos} n ON n.id = e.nodo_creacion";
    }

    private function normalizarCuenta(array $fila)
    {
        $fila['etiqueta'] = $fila['nodo'] . ' · ' . $fila['banco'] . ' · '
            . $fila['tipo'] . ' · ' . $fila['cuenta'] . ' · ' . ($fila['codigo'] ?? 'Sin codigo');
        return $fila;
    }

    private function respuestaCuenta($configuracionId, array $cuenta, $cambio, $activa)
    {
        return [
            'configuracion_id' => (string) $configuracionId,
            'cuenta_bancaria_id' => (string) $cuenta['id'],
            'etiqueta' => $cuenta['etiqueta'],
            'activa' => (bool) $activa,
            'cambio' => (bool) $cambio,
        ];
    }

    private function bloquearReglaActiva($configuracionId, $reglaId, $bloquearConfiguracion = true)
    {
        if ($bloquearConfiguracion) {
            $this->bloquearConfiguracion($configuracionId);
        }
        $consulta = $this->pdo->prepare(
            "SELECT id, configuracion_id, subtipo_valor_zetti_id, sentido,
                    codigo_extracto, validar_automaticamente, version, reemplaza_regla_id
             FROM {$this->reglas}
             WHERE id = :regla_id AND configuracion_id = :configuracion_id
               AND activo IS TRUE
             FOR UPDATE"
        );
        $consulta->execute([
            ':regla_id' => (string) $reglaId,
            ':configuracion_id' => (string) $configuracionId,
        ]);
        $regla = $consulta->fetch(PDO::FETCH_ASSOC);
        if ($regla === false) {
            throw new RecursoNoDisponibleException('La regla no existe o ya no esta activa en esta configuracion.');
        }
        return $regla;
    }

    private function validarSubtipo($subtipoId)
    {
        $consulta = $this->pdo->prepare("SELECT 1 FROM {$this->subtipos} WHERE id = :id");
        $consulta->execute([':id' => (string) $subtipoId]);
        if ($consulta->fetchColumn() === false) {
            throw new RecursoNoDisponibleException('El subtipo de valor no existe en ERP.');
        }
    }

    private function reemplazarRegla(array $anterior, array $datos, $operadorId, $motivo)
    {
        $consulta = $this->pdo->prepare(
            "UPDATE {$this->reglas}
             SET activo = false, fecha_modificacion = current_timestamp,
                 usuario_modificacion = :operador_id
             WHERE id = :regla_id AND activo IS TRUE"
        );
        $consulta->execute([':operador_id' => (int) $operadorId, ':regla_id' => (string) $anterior['id']]);
        if ($consulta->rowCount() !== 1) {
            throw new RuntimeException('No se pudo conservar la version anterior de la regla.');
        }
        return $this->insertarRegla(
            $anterior['configuracion_id'],
            $datos,
            $operadorId,
            $motivo,
            $anterior['id'],
            (int) $anterior['version'] + 1
        );
    }

    private function insertarRegla($configuracionId, array $datos, $operadorId, $motivo, $reemplazaId, $version)
    {
        try {
            $consulta = $this->pdo->prepare(
                "INSERT INTO {$this->reglas}
                    (configuracion_id, subtipo_valor_zetti_id, sentido, codigo_extracto,
                     validar_automaticamente, observacion, activo, version,
                     reemplaza_regla_id, usuario_creacion, usuario_modificacion)
                 VALUES
                    (:configuracion_id, :subtipo_id, :sentido, :codigo,
                     :validar, :observacion, true, :version,
                     :reemplaza_id, :operador_id, :operador_id)
                 RETURNING id, configuracion_id, subtipo_valor_zetti_id, sentido,
                           codigo_extracto, validar_automaticamente, version, reemplaza_regla_id"
            );
            $consulta->bindValue(':configuracion_id', (string) $configuracionId);
            $consulta->bindValue(':subtipo_id', (int) $datos['subtipo_valor_zetti_id'], PDO::PARAM_INT);
            $consulta->bindValue(':sentido', $datos['sentido']);
            $consulta->bindValue(':codigo', $datos['codigo_extracto']);
            $consulta->bindValue(':validar', (bool) $datos['validar_automaticamente'], PDO::PARAM_BOOL);
            $consulta->bindValue(':observacion', $motivo);
            $consulta->bindValue(':version', (int) $version, PDO::PARAM_INT);
            $consulta->bindValue(':reemplaza_id', $reemplazaId);
            $consulta->bindValue(':operador_id', (int) $operadorId, PDO::PARAM_INT);
            $consulta->execute();
        } catch (PDOException $error) {
            if ($error->getCode() === '23505') {
                throw new RecursoNoDisponibleException(
                    'Ya existe una regla activa con la misma configuracion, subtipo, sentido y codigo.'
                );
            }
            throw $error;
        }
        $regla = $consulta->fetch(PDO::FETCH_ASSOC);
        if ($regla === false) {
            throw new RuntimeException('No se pudo crear la version de la regla.');
        }
        return $regla;
    }

    private function respuestaRegla($anterior, array $actual, $cambio)
    {
        return [
            'configuracion_id' => (string) $actual['configuracion_id'],
            'regla_id_anterior' => $anterior === null ? null : (string) $anterior['id'],
            'regla_id' => (string) $actual['id'],
            'subtipo_valor_zetti_id' => (string) $actual['subtipo_valor_zetti_id'],
            'sentido' => (string) $actual['sentido'],
            'codigo_extracto' => $actual['codigo_extracto'],
            'validar_automaticamente' => (bool) $actual['validar_automaticamente'],
            'version' => (int) $actual['version'],
            'cambio' => (bool) $cambio,
        ];
    }

    private function normalizarConfiguracion(array $fila)
    {
        foreach (['cuentas', 'reglas', 'automaticas'] as $campo) {
            $fila[$campo] = (int) $fila[$campo];
        }
        $partes = ['Configuración #' . $fila['id'], $fila['alcance']];
        if ($fila['banco'] !== null) {
            $partes[] = 'Banco ' . $fila['banco'];
        }
        if ($fila['nodo'] !== null) {
            $partes[] = 'Nodo ' . $fila['nodo'];
        }
        $fila['etiqueta'] = implode(' · ', $partes);
        return $fila;
    }
}
