<?php
namespace AppBancos\Repository;

use AppBancos\Application\RecursoNoDisponibleException;
use AppBancos\Infrastructure\EsquemaBancos;
use PDO;
use RuntimeException;

final class ValorErpGateway
{
    private $pdo;
    private $valoresLectura;
    private $valoresEscritura;
    private $subtiposLectura;

    public function __construct(PDO $pdo, EsquemaBancos $esquema)
    {
        $this->pdo = $pdo;
        $this->valoresLectura = $esquema->tablaLecturaErp('valor');
        $this->valoresEscritura = $esquema->tablaEscrituraErp('valor');
        $this->subtiposLectura = $esquema->tablaLecturaErp('subtipo_valor');
    }

    public function liquidar($valorId)
    {
        if (!$this->pdo->inTransaction()) {
            throw new RuntimeException('La liquidacion del valor debe ejecutarse dentro de una transaccion.');
        }
        $consulta = $this->pdo->prepare(
            "SELECT v.id::text, v.estado, upper(COALESCE(s.nombre, '')) LIKE '%CHEQUE%' AS es_cheque
             FROM {$this->valoresLectura} v
             LEFT JOIN {$this->subtiposLectura} s ON s.id = v.subtipo_valor
             WHERE v.id = :id"
        );
        $consulta->execute([':id' => (string) $valorId]);
        $origen = $consulta->fetch(PDO::FETCH_ASSOC);
        if ($origen === false) {
            throw new RecursoNoDisponibleException('El valor asociado ya no existe en ERP.');
        }
        if (in_array($origen['es_cheque'], [true, 1, '1', 't'], true)) {
            throw new RecursoNoDisponibleException('Los cheques requieren el modulo de conciliacion.');
        }
        if (in_array((int) $origen['estado'], [20, 36], true)) {
            throw new RecursoNoDisponibleException('El estado ERP del valor no admite liquidacion.');
        }

        $consulta = $this->pdo->prepare(
            "SELECT id::text, estado
             FROM {$this->valoresEscritura}
             WHERE id = :id
             FOR UPDATE"
        );
        $consulta->execute([':id' => (string) $valorId]);
        $destino = $consulta->fetch(PDO::FETCH_ASSOC);
        if ($destino === false) {
            throw new RecursoNoDisponibleException(
                'El valor no posee una copia de escritura en el sandbox.'
            );
        }
        if ((int) $destino['estado'] !== (int) $origen['estado']) {
            throw new RecursoNoDisponibleException(
                'La copia de escritura del valor no coincide con su estado ERP actual.'
            );
        }
        if ((int) $destino['estado'] === 7) {
            return [
                'valor_zetti_id' => (string) $valorId,
                'estado_anterior' => 7,
                'estado' => 7,
                'modificado' => false,
            ];
        }

        $consulta = $this->pdo->prepare(
            "UPDATE {$this->valoresEscritura}
             SET estado = 7,
                 fecha_modificacion = current_timestamp
             WHERE id = :id AND estado = :estado_anterior
             RETURNING id::text"
        );
        $consulta->execute([
            ':id' => (string) $valorId,
            ':estado_anterior' => (int) $destino['estado'],
        ]);
        if ($consulta->fetchColumn() === false) {
            throw new RecursoNoDisponibleException('El estado del valor cambio durante el cierre.');
        }
        return [
            'valor_zetti_id' => (string) $valorId,
            'estado_anterior' => (int) $destino['estado'],
            'estado' => 7,
            'modificado' => true,
        ];
    }
}
