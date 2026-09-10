<?php
namespace AppBancos\Repository;

use AppBancos\Application\RecursoNoDisponibleException;
use AppBancos\Infrastructure\EsquemaBancos;
use PDO;
use RuntimeException;

final class ImportacionExtractoRepository
{
    private $pdo;
    private $configuraciones;
    private $configuracionesCuenta;
    private $reglasClasificacion;
    private $lotes;
    private $movimientos;
    private $estados;
    private $cuentasBancarias;

    public function __construct(PDO $pdo, EsquemaBancos $esquema)
    {
        $this->pdo = $pdo;
        $this->configuraciones = $esquema->tablaBancos('bancos_configuracion');
        $this->configuracionesCuenta = $esquema->tablaBancos('bancos_configuracion_cuenta');
        $this->reglasClasificacion = $esquema->tablaBancos('bancos_regla_clasificacion');
        $this->lotes = $esquema->tablaBancos('bancos_importacion_extracto');
        $this->movimientos = $esquema->tablaBancos('bancos_movimiento_extracto');
        $this->estados = $esquema->tablaBancos('bancos_estado');
        $this->cuentasBancarias = $esquema->tablaLecturaErp('cuenta_bancaria');
    }

    public function validarCuentaConfigurada($cuentaBancariaId)
    {
        $consulta = $this->pdo->prepare(
            "WITH vinculos AS (
                 SELECT configuracion_id, cuenta_bancaria_zetti_id FROM {$this->configuracionesCuenta}
                 UNION
                 SELECT configuracion_id, cuenta_bancaria_zetti_id FROM {$this->lotes}
             )
             SELECT count(DISTINCT c.id)
             FROM {$this->cuentasBancarias} cb
             JOIN vinculos cc ON cc.cuenta_bancaria_zetti_id = cb.id
             JOIN {$this->configuraciones} c ON c.id = cc.configuracion_id AND c.activo IS TRUE
             WHERE cb.id = :cuenta_bancaria_id"
        );
        $consulta->execute([':cuenta_bancaria_id' => (string) $cuentaBancariaId]);
        $cantidad = (int) $consulta->fetchColumn();
        if ($cantidad === 0) {
            throw new RecursoNoDisponibleException(
                'La cuenta bancaria no existe o no posee configuraciones activas.'
            );
        }
        return $cantidad;
    }

    public function validarConfiguracionCuenta($configuracionId, $cuentaBancariaId)
    {
        $consulta = $this->pdo->prepare(
            "WITH vinculos AS (
                 SELECT configuracion_id, cuenta_bancaria_zetti_id FROM {$this->configuracionesCuenta}
                 UNION
                 SELECT configuracion_id, cuenta_bancaria_zetti_id FROM {$this->lotes}
             )
             SELECT 1
             FROM vinculos vc
             JOIN {$this->configuraciones} c ON c.id = vc.configuracion_id AND c.activo IS TRUE
             JOIN {$this->cuentasBancarias} cb ON cb.id = vc.cuenta_bancaria_zetti_id
             WHERE c.id = :configuracion_id
               AND cb.id = :cuenta_bancaria_id"
        );
        $consulta->execute([
            ':configuracion_id' => (string) $configuracionId,
            ':cuenta_bancaria_id' => (string) $cuentaBancariaId,
        ]);
        if ($consulta->fetchColumn() === false) {
            throw new RecursoNoDisponibleException(
                'La configuracion seleccionada no esta activa para la cuenta bancaria.'
            );
        }
    }

    public function obtenerReglasClasificacion($configuracionId)
    {
        $consulta = $this->pdo->prepare(
            "SELECT subtipo_valor_zetti_id, sentido, codigo_extracto, validar_automaticamente
             FROM {$this->reglasClasificacion}
             WHERE configuracion_id = :configuracion_id
             ORDER BY id"
        );
        $consulta->execute([':configuracion_id' => (string) $configuracionId]);
        return $consulta->fetchAll(PDO::FETCH_ASSOC);
    }

    public function crearLote(
        $configuracionId,
        $cuentaBancariaId,
        $inicioPeriodo,
        $archivo,
        $hash,
        $version,
        array $filas,
        $usuarioId,
        $claveIdempotencia
    ) {
        if (!$this->pdo->inTransaction()) {
            throw new RuntimeException('La importacion debe ejecutarse dentro de una transaccion.');
        }
        if (count($filas) === 0) {
            throw new RuntimeException('La importacion no contiene movimientos normalizados.');
        }

        $alcanceBloqueo = implode('|', [$cuentaBancariaId, $inicioPeriodo, $hash, $version]);
        $consulta = $this->pdo->prepare('SELECT pg_advisory_xact_lock(hashtext(:alcance))');
        $consulta->execute([':alcance' => $alcanceBloqueo]);
        $this->validarConfiguracionCuenta($configuracionId, $cuentaBancariaId);

        $consulta = $this->pdo->prepare(
            "SELECT id FROM {$this->lotes}
             WHERE cuenta_bancaria_zetti_id = :cuenta_bancaria_id
               AND inicio_periodo = :inicio_periodo
               AND hash_origen = :hash
               AND version_origen = :version"
        );
        $consulta->execute([
            ':cuenta_bancaria_id' => (string) $cuentaBancariaId,
            ':inicio_periodo' => $inicioPeriodo,
            ':hash' => $hash,
            ':version' => $version,
        ]);
        if ($consulta->fetchColumn() !== false) {
            throw new RecursoNoDisponibleException(
                'Este archivo ya fue importado para la cuenta y el periodo seleccionados.'
            );
        }

        $consulta = $this->pdo->query(
            "SELECT id FROM {$this->estados} WHERE codigo = 'ABIERTO' AND activo IS TRUE"
        );
        $estadoId = $consulta->fetchColumn();
        if ($estadoId === false) {
            throw new RuntimeException('El estado ABIERTO no esta configurado.');
        }

        $consulta = $this->pdo->prepare(
            "INSERT INTO {$this->lotes}
                (configuracion_id, cuenta_bancaria_zetti_id, inicio_periodo,
                 total_movimientos, estado_id, archivo_origen, hash_origen,
                 version_origen, observacion, usuario_creacion, usuario_modificacion)
             VALUES
                (:configuracion_id, :cuenta_bancaria_id, :inicio_periodo,
                 :total_movimientos, :estado_id, :archivo, :hash,
                 :version, :observacion, :usuario_id, :usuario_id)
             RETURNING id, fecha_creacion"
        );
        $consulta->execute([
            ':configuracion_id' => (string) $configuracionId,
            ':cuenta_bancaria_id' => (string) $cuentaBancariaId,
            ':inicio_periodo' => $inicioPeriodo,
            ':total_movimientos' => count($filas),
            ':estado_id' => (int) $estadoId,
            ':archivo' => $archivo,
            ':hash' => $hash,
            ':version' => $version,
            ':observacion' => 'API|IMPORTACION|' . $claveIdempotencia,
            ':usuario_id' => (int) $usuarioId,
        ]);
        $lote = $consulta->fetch(PDO::FETCH_ASSOC);
        if ($lote === false) {
            throw new RuntimeException('No se pudo crear el lote de importacion.');
        }

        $idPeriodo = 'IMP-' . $lote['id'];
        $insertar = $this->pdo->prepare(
            "INSERT INTO {$this->movimientos}
                (importacion_id, id_periodo, serial_seq, numero_fila_origen,
                 fecha_operacion, referencia, descripcion, codigo_extracto,
                 credito, debito, subtipo_valor_zetti_id,
                 usuario_creacion, usuario_modificacion)
             VALUES
                (:importacion_id, :id_periodo, :serial_seq, :numero_fila_origen,
                 :fecha_operacion, :referencia, :descripcion, :codigo_extracto,
                 :credito, :debito, :subtipo_valor_zetti_id,
                 :usuario_id, :usuario_id)"
        );
        foreach ($filas as $indice => $fila) {
            $insertar->execute([
                ':importacion_id' => (int) $lote['id'],
                ':id_periodo' => $idPeriodo,
                ':serial_seq' => $indice + 1,
                ':numero_fila_origen' => (int) $fila['numero_fila_origen'],
                ':fecha_operacion' => $fila['fecha_operacion'],
                ':referencia' => $fila['referencia'],
                ':descripcion' => $fila['descripcion'],
                ':codigo_extracto' => $fila['codigo_extracto'],
                ':credito' => $fila['credito'],
                ':debito' => $fila['debito'],
                ':subtipo_valor_zetti_id' => $fila['subtipo_valor_zetti_id'],
                ':usuario_id' => (int) $usuarioId,
            ]);
        }

        return [
            'importacion_id' => (int) $lote['id'],
            'configuracion_id' => (string) $configuracionId,
            'cuenta_bancaria_id' => (string) $cuentaBancariaId,
            'inicio_periodo' => $inicioPeriodo,
            'estado' => 'ABIERTO',
            'total_movimientos' => count($filas),
            'archivo' => $archivo,
            'hash_sha256' => $hash,
            'version_origen' => $version,
            'creada_en' => (string) $lote['fecha_creacion'],
        ];
    }
}
