<?php
namespace AppBancos\Repository;

use AppBancos\Application\RecursoNoDisponibleException;
use AppBancos\Infrastructure\EsquemaBancos;
use PDO;
use RuntimeException;

final class ConciliacionChequeErpGateway
{
    private $pdo;
    private $conciliaciones;
    private $estados;
    private $valoresLectura;
    private $nodosLectura;
    private $valores;
    private $operaciones;
    private $operacionesValores;
    private $valoresConceptos;
    private $asientos;
    private $movimientos;
    private $secuenciaValor;
    private $secuenciaOperacion;
    private $secuenciaOperacionValor;
    private $secuenciaValorConcepto;
    private $secuenciaAsiento;
    private $secuenciaMovimiento;

    public function __construct(PDO $pdo, EsquemaBancos $esquema)
    {
        $this->pdo = $pdo;
        $this->conciliaciones = $esquema->tablaBancos('bancos_conciliacion_cheque');
        $this->estados = $esquema->tablaBancos('bancos_estado');
        $this->valoresLectura = $esquema->tablaLecturaErp('valor');
        $this->nodosLectura = $esquema->tablaLecturaErp('nodo');
        $this->valores = $esquema->tablaEscrituraErp('valor');
        $this->operaciones = $esquema->tablaEscrituraErp('operacion');
        $this->operacionesValores = $esquema->tablaEscrituraErp('operacion_valor');
        $this->valoresConceptos = $esquema->tablaEscrituraErp('valor_concepto');
        $this->asientos = $esquema->tablaEscrituraErp('asiento');
        $this->movimientos = $esquema->tablaEscrituraErp('movimiento');
        $this->secuenciaValor = $esquema->secuenciaEscrituraErp('valor_sq');
        $this->secuenciaOperacion = $esquema->secuenciaEscrituraErp('operacion_sq');
        $this->secuenciaOperacionValor = $esquema->secuenciaEscrituraErp('operacion_valor_sq');
        $this->secuenciaValorConcepto = $esquema->secuenciaEscrituraErp('valor_concepto_sq');
        $this->secuenciaAsiento = $esquema->secuenciaEscrituraErp('asiento_sq');
        $this->secuenciaMovimiento = $esquema->secuenciaEscrituraErp('movimiento_sq');
    }

    public function conciliar(array $evaluacion, $usuarioId, $claveIdempotencia)
    {
        if (!$this->pdo->inTransaction()) {
            throw new RuntimeException('La conciliacion debe ejecutarse dentro de una transaccion.');
        }
        if (!$evaluacion['listo_para_conciliar']) {
            throw new RecursoNoDisponibleException('El preflight no habilita una conciliacion sandbox.');
        }
        $movimientoId = (int) $evaluacion['movimiento_id'];
        $cheque = $evaluacion['cheque'];
        $contexto = $evaluacion['contexto_contable'];
        $valorOrigenId = (string) $cheque['valor_zetti_id'];

        $consulta = $this->pdo->prepare('SELECT pg_advisory_xact_lock(CAST(:valor_id AS bigint))');
        $consulta->execute([':valor_id' => $valorOrigenId]);
        $consulta = $this->pdo->prepare(
            "SELECT id FROM {$this->conciliaciones}
             WHERE movimiento_id=:movimiento OR valor_origen_zetti_id=:valor LIMIT 1"
        );
        $consulta->execute([':movimiento' => $movimientoId, ':valor' => $valorOrigenId]);
        if ($consulta->fetchColumn() !== false) {
            throw new RecursoNoDisponibleException('El movimiento o cheque ya posee una conciliacion registrada.');
        }

        $consulta = $this->pdo->prepare(
            "SELECT estado FROM {$this->valores} WHERE id=:id FOR UPDATE"
        );
        $consulta->execute([':id' => $valorOrigenId]);
        $estadoSandbox = $consulta->fetchColumn();
        if ($estadoSandbox === false || (int) $estadoSandbox !== (int) $cheque['estado_erp']) {
            throw new RecursoNoDisponibleException('La copia sandbox del cheque no coincide con ERP.');
        }
        $consulta = $this->pdo->prepare(
            "SELECT estado FROM {$this->valoresLectura} WHERE id=:id"
        );
        $consulta->execute([':id' => $valorOrigenId]);
        $estadoProductivo = $consulta->fetchColumn();
        if ($estadoProductivo === false || (int) $estadoProductivo !== (int) $cheque['estado_erp']) {
            throw new RecursoNoDisponibleException('El estado productivo del cheque cambio despues del preflight.');
        }

        $consulta = $this->pdo->prepare(
            "SELECT codigo_jerarquico FROM {$this->nodosLectura} WHERE id=:id"
        );
        $consulta->execute([':id' => (int) $contexto['nodo_zetti_id']]);
        $codigoNodo = $consulta->fetchColumn();
        if ($codigoNodo === false) {
            throw new RecursoNoDisponibleException('El nodo de la conciliacion ya no existe en ERP.');
        }
        $referencia = trim((string) ($evaluacion['referencia'] ?? ''));
        $codificacion = 'CONCILIACION BANCOS - ' . ($referencia !== '' ? $referencia : $movimientoId);
        $monto = (string) $evaluacion['monto_movimiento'];
        $fecha = (string) $evaluacion['fecha_operacion'];
        $nodoId = (int) $contexto['nodo_zetti_id'];

        $consulta = $this->pdo->prepare(
            "INSERT INTO {$this->operaciones}
                (id,tipo_operacion,fecha,usuario_creacion,usuario_modificacion,nodo_creacion,
                 nodo_modificacion,fecha_creacion,fecha_modificacion,estado_operacion,discriminador,observa)
             VALUES
                (nextval('{$this->secuenciaOperacion}'),101,current_timestamp,NULL,NULL,:nodo,
                 :nodo,current_timestamp,current_timestamp,1,101,:observa)
             RETURNING id::text"
        );
        $consulta->execute([':nodo' => $nodoId, ':observa' => $codificacion]);
        $operacionId = $consulta->fetchColumn();

        $consulta = $this->pdo->prepare(
            "INSERT INTO {$this->valores}
                (id,tipo_valor,entidad,estado,codificacion,moneda,nodo_creacion,nodo_modificacion,
                 usuario_creacion,usuario_modificacion,fecha_creacion,fecha_modificacion,fecha_emision,
                 discriminador,cod_jerarquico_nodo,monto_principal,subtipo_valor,repuesto,version,fecha_actualizacion)
             VALUES
                (nextval('{$this->secuenciaValor}'),124,:entidad,4,:codificacion,:moneda,:nodo,:nodo,
                 NULL,NULL,current_timestamp,current_timestamp,current_timestamp,124,:codigo_nodo,
                 :monto,79,false,0,current_timestamp)
             RETURNING id::text"
        );
        $consulta->execute([
            ':entidad' => (string) $evaluacion['cuenta_bancaria_zetti_id'],
            ':codificacion' => $codificacion,
            ':moneda' => (int) $cheque['moneda'],
            ':nodo' => $nodoId,
            ':codigo_nodo' => (string) $codigoNodo,
            ':monto' => $monto,
        ]);
        $valorResultanteId = $consulta->fetchColumn();

        $insertarOperacionValor = $this->pdo->prepare(
            "INSERT INTO {$this->operacionesValores} (id,estado,valor,operacion,comprobante)
             VALUES (nextval('{$this->secuenciaOperacionValor}'),:estado,:valor,:operacion,:comprobante)"
        );
        $insertarOperacionValor->execute([
            ':estado' => 7, ':valor' => $valorOrigenId,
            ':operacion' => $operacionId, ':comprobante' => 'false',
        ]);
        $insertarOperacionValor->execute([
            ':estado' => 4, ':valor' => $valorResultanteId,
            ':operacion' => $operacionId, ':comprobante' => 'true',
        ]);

        $consulta = $this->pdo->prepare(
            "INSERT INTO {$this->valoresConceptos} (id,valor,concepto,monto,lock)
             VALUES (nextval('{$this->secuenciaValorConcepto}'),:valor,376,:monto,true)"
        );
        $consulta->execute([':valor' => $valorResultanteId, ':monto' => $monto]);

        $consulta = $this->pdo->prepare(
            "INSERT INTO {$this->asientos}
                (id,fecha,nodo_creacion,nodo_modificacion,usuario_creacion,usuario_modificacion,
                 fecha_creacion,fecha_modificacion,numero,nombre,rev)
             VALUES
                (nextval('{$this->secuenciaAsiento}'),:fecha,:nodo,:nodo,NULL,NULL,
                 current_timestamp,current_timestamp,NULL,:nombre,false)
             RETURNING id::text"
        );
        $consulta->execute([':fecha' => $fecha, ':nodo' => $nodoId, ':nombre' => $codificacion]);
        $asientoId = $consulta->fetchColumn();
        $consulta = $this->pdo->prepare("UPDATE {$this->operaciones} SET asiento=:asiento WHERE id=:id");
        $consulta->execute([':asiento' => $asientoId, ':id' => $operacionId]);

        $insertarMovimiento = $this->pdo->prepare(
            "INSERT INTO {$this->movimientos} (id,asiento,cuenta,debita,monto)
             VALUES (nextval('{$this->secuenciaMovimiento}'),:asiento,:cuenta,:debita,:monto)"
        );
        $insertarMovimiento->execute([
            ':asiento' => $asientoId,
            ':cuenta' => $contexto['cuenta_valor']['cuenta_zetti_id'],
            ':debita' => 'true',
            ':monto' => $monto,
        ]);
        $insertarMovimiento->execute([
            ':asiento' => $asientoId,
            ':cuenta' => $contexto['cuenta_banco']['cuenta_zetti_id'],
            ':debita' => 'false',
            ':monto' => $monto,
        ]);

        $consulta = $this->pdo->prepare(
            "UPDATE {$this->valores} SET estado=7,fecha_modificacion=current_timestamp
             WHERE id=:id AND estado=:estado RETURNING id"
        );
        $consulta->execute([':id' => $valorOrigenId, ':estado' => (int) $cheque['estado_erp']]);
        if ($consulta->fetchColumn() === false) {
            throw new RecursoNoDisponibleException('El cheque cambio durante la conciliacion.');
        }

        $estadoCerrado = $this->pdo->query(
            "SELECT id FROM {$this->estados} WHERE codigo='CERRADO' AND activo IS TRUE"
        )->fetchColumn();
        if ($estadoCerrado === false) {
            throw new RuntimeException('El estado CERRADO no esta configurado.');
        }
        $consulta = $this->pdo->prepare(
            "INSERT INTO {$this->conciliaciones}
                (movimiento_id,estado_id,operador_hub_id,valor_origen_zetti_id,operacion_zetti_id,
                 valor_resultante_zetti_id,asiento_zetti_id,clave_idempotencia,motivo,forzada,evidencia_origen)
             VALUES
                (:movimiento,:estado,:operador,:origen,:operacion,:resultante,:asiento,:clave,NULL,false,:evidencia)
             RETURNING id::text,conciliado_en"
        );
        $consulta->execute([
            ':movimiento' => $movimientoId,
            ':estado' => (int) $estadoCerrado,
            ':operador' => (int) $usuarioId,
            ':origen' => $valorOrigenId,
            ':operacion' => $operacionId,
            ':resultante' => $valorResultanteId,
            ':asiento' => $asientoId,
            ':clave' => $claveIdempotencia,
            ':evidencia' => 'PREFLIGHT|CONFIGURACION:' . $contexto['configuracion_id'] . '|MONTO_EXACTO',
        ]);
        $registro = $consulta->fetch(PDO::FETCH_ASSOC);

        return [
            'conciliacion_id' => (string) $registro['id'],
            'movimiento_id' => $movimientoId,
            'valor_origen_zetti_id' => $valorOrigenId,
            'operacion_zetti_id' => (string) $operacionId,
            'valor_resultante_zetti_id' => (string) $valorResultanteId,
            'asiento_zetti_id' => (string) $asientoId,
            'conciliado_en' => (string) $registro['conciliado_en'],
            'efectos_erp' => 8,
            'sandbox' => true,
        ];
    }
}
