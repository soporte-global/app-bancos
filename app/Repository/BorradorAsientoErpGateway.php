<?php
namespace AppBancos\Repository;

use AppBancos\Application\RecursoNoDisponibleException;
use AppBancos\Infrastructure\EsquemaBancos;
use PDO;
use RuntimeException;

final class BorradorAsientoErpGateway
{
    private $pdo;
    private $borradores;
    private $lineas;
    private $estados;
    private $asientos;
    private $movimientos;
    private $secuenciaAsiento;
    private $secuenciaMovimiento;

    public function __construct(PDO $pdo, EsquemaBancos $esquema)
    {
        $this->pdo = $pdo;
        $this->borradores = $esquema->tablaBancos('bancos_borrador_asiento');
        $this->lineas = $esquema->tablaBancos('bancos_linea_borrador_asiento');
        $this->estados = $esquema->tablaBancos('bancos_estado');
        $this->asientos = $esquema->tablaEscrituraErp('asiento');
        $this->movimientos = $esquema->tablaEscrituraErp('movimiento');
        $this->secuenciaAsiento = $esquema->secuenciaEscrituraErp('asiento_sq');
        $this->secuenciaMovimiento = $esquema->secuenciaEscrituraErp('movimiento_sq');
    }

    public function materializar($borradorId, $usuarioId)
    {
        if (!$this->pdo->inTransaction()) {
            throw new RuntimeException('La materializacion del borrador debe ejecutarse dentro de una transaccion.');
        }
        $consulta = $this->pdo->prepare(
            "SELECT id::text, nodo_zetti_id, fecha_contable, modelo, activo, asiento_zetti_id::text
             FROM {$this->borradores}
             WHERE id=:id FOR UPDATE"
        );
        $consulta->execute([':id' => (int) $borradorId]);
        $borrador = $consulta->fetch(PDO::FETCH_ASSOC);
        if ($borrador === false || !$this->verdadero($borrador['activo'])) {
            throw new RecursoNoDisponibleException('El borrador asociado no existe o ya no esta activo.');
        }

        $consulta = $this->pdo->prepare(
            "SELECT id, cuenta_zetti_id::text, debe, haber
             FROM {$this->lineas}
             WHERE borrador_asiento_id=:id ORDER BY id FOR UPDATE"
        );
        $consulta->execute([':id' => (int) $borradorId]);
        $lineas = $consulta->fetchAll(PDO::FETCH_ASSOC);
        $this->validarLineas($lineas);

        if ($borrador['asiento_zetti_id'] !== null) {
            $this->validarAsientoExistente($borrador['asiento_zetti_id'], $lineas);
            return [
                'borrador_id' => (int) $borradorId,
                'asiento_zetti_id' => (string) $borrador['asiento_zetti_id'],
                'cantidad_lineas' => count($lineas),
                'modificado' => false,
            ];
        }

        $consulta = $this->pdo->prepare(
            "INSERT INTO {$this->asientos}
                (id,fecha,nodo_creacion,nodo_modificacion,usuario_creacion,usuario_modificacion,
                 fecha_creacion,fecha_modificacion,numero,nombre,rev)
             VALUES
                (nextval('{$this->secuenciaAsiento}'),:fecha,:nodo,:nodo,NULL,NULL,
                 current_timestamp,current_timestamp,NULL,:nombre,false)
             RETURNING id::text"
        );
        $consulta->execute([
            ':fecha' => $borrador['fecha_contable'],
            ':nodo' => (int) $borrador['nodo_zetti_id'],
            ':nombre' => (string) $borrador['modelo'],
        ]);
        $asientoId = $consulta->fetchColumn();
        if ($asientoId === false) {
            throw new RuntimeException('No se pudo crear el asiento del borrador.');
        }

        $insertar = $this->pdo->prepare(
            "INSERT INTO {$this->movimientos} (id,asiento,cuenta,debita,monto)
             VALUES (nextval('{$this->secuenciaMovimiento}'),:asiento,:cuenta,:debita,:monto)"
        );
        foreach ($lineas as $linea) {
            $esDebe = (float) $linea['debe'] > 0;
            $insertar->execute([
                ':asiento' => (string) $asientoId,
                ':cuenta' => (string) $linea['cuenta_zetti_id'],
                ':debita' => $esDebe ? 'true' : 'false',
                ':monto' => $esDebe ? $linea['debe'] : $linea['haber'],
            ]);
        }

        $consulta = $this->pdo->prepare(
            "SELECT id FROM {$this->estados} WHERE codigo='CERRADO' AND activo IS TRUE"
        );
        $consulta->execute();
        $estadoId = $consulta->fetchColumn();
        if ($estadoId === false) {
            throw new RuntimeException('El estado CERRADO no esta configurado para el borrador.');
        }
        $consulta = $this->pdo->prepare(
            "UPDATE {$this->borradores}
             SET asiento_zetti_id=:asiento, estado_id=:estado, fecha_modificacion=current_timestamp,
                 usuario_modificacion=:usuario
             WHERE id=:id AND asiento_zetti_id IS NULL
             RETURNING id"
        );
        $consulta->execute([
            ':asiento' => (string) $asientoId,
            ':estado' => (int) $estadoId,
            ':usuario' => (int) $usuarioId,
            ':id' => (int) $borradorId,
        ]);
        if ($consulta->fetchColumn() === false) {
            throw new RecursoNoDisponibleException('El borrador fue materializado por otra operacion.');
        }

        return [
            'borrador_id' => (int) $borradorId,
            'asiento_zetti_id' => (string) $asientoId,
            'cantidad_lineas' => count($lineas),
            'modificado' => true,
        ];
    }

    private function validarLineas(array $lineas)
    {
        if (count($lineas) < 2 || count($lineas) > 200) {
            throw new RecursoNoDisponibleException('El borrador no contiene una cantidad valida de lineas.');
        }
        $debe = '0.00000';
        $haber = '0.00000';
        foreach ($lineas as $linea) {
            $debe = bcadd($debe, (string) $linea['debe'], 5);
            $haber = bcadd($haber, (string) $linea['haber'], 5);
        }
        if ($debe !== $haber) {
            throw new RecursoNoDisponibleException('El borrador dejo de estar balanceado.');
        }
    }

    private function validarAsientoExistente($asientoId, array $lineasBorrador)
    {
        $consulta = $this->pdo->prepare(
            "SELECT cuenta::text, debita, monto
             FROM {$this->movimientos} WHERE asiento=:id ORDER BY cuenta,debita,monto,id"
        );
        $consulta->execute([':id' => (string) $asientoId]);
        $lineasAsiento = $consulta->fetchAll(PDO::FETCH_ASSOC);
        $esperadas = [];
        foreach ($lineasBorrador as $linea) {
            $esDebe = (float) $linea['debe'] > 0;
            $esperadas[] = $linea['cuenta_zetti_id'] . '|' . ($esDebe ? '1' : '0') . '|'
                . ($esDebe ? $linea['debe'] : $linea['haber']);
        }
        $actuales = array_map(function (array $linea) {
            return $linea['cuenta'] . '|' . ($this->verdadero($linea['debita']) ? '1' : '0') . '|'
                . $linea['monto'];
        }, $lineasAsiento);
        sort($esperadas, SORT_STRING);
        sort($actuales, SORT_STRING);
        if ($esperadas !== $actuales) {
            throw new RecursoNoDisponibleException('El asiento materializado no coincide con el borrador.');
        }
    }

    private function verdadero($valor)
    {
        return in_array($valor, [true, 1, '1', 't'], true);
    }
}
