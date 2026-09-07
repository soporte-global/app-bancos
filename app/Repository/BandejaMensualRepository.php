<?php
namespace AppBancos\Repository;

use AppBancos\Infrastructure\EsquemaBancos;
use InvalidArgumentException;
use PDO;

final class BandejaMensualRepository
{
    private $pdo;
    private $esquemas;

    public function __construct(PDO $pdo, EsquemaBancos $esquemas)
    {
        $this->pdo = $pdo;
        $this->esquemas = $esquemas;
    }

    public function listar($cuentaBancariaId, $inicioPeriodo, array $cursor = null, $limite = 50)
    {
        return $this->consultar($cuentaBancariaId, $inicioPeriodo, $cursor, $limite, false)['movimientos'];
    }

    public function listarPagina($cuentaBancariaId, $inicioPeriodo, array $cursor = null, $limite = 50)
    {
        return $this->consultar($cuentaBancariaId, $inicioPeriodo, $cursor, $limite, true);
    }

    private function consultar($cuentaBancariaId, $inicioPeriodo, array $cursor = null, $limite = 50, $detectarSiguiente = false)
    {
        $cuentaBancariaId = $this->enteroPositivo($cuentaBancariaId, 'cuentaBancariaId');
        $limite = $this->limite($limite);
        $inicioPeriodo = $this->fecha($inicioPeriodo, 'inicioPeriodo');
        $tieneCursor = $cursor !== null;
        $cursor = $this->cursor($cursor);

        $importacion = $this->esquemas->tablaBancos('bancos_importacion_extracto');
        $movimiento = $this->esquemas->tablaBancos('bancos_movimiento_extracto');
        $historial = $this->esquemas->tablaBancos('bancos_historial_asignacion');
        $asociacion = $this->esquemas->tablaBancos('bancos_asociacion_movimiento');
        $mensaje = $this->esquemas->tablaBancos('bancos_mensaje_movimiento');

        $condicionCursor = '';
        if ($tieneCursor) {
            $condicionCursor = $cursor['fecha'] === null
                ? 'AND m.fecha_operacion IS NULL AND m.id > :cursor_id'
                : 'AND (m.fecha_operacion > :cursor_fecha
                        OR (m.fecha_operacion = :cursor_fecha AND m.id > :cursor_id)
                        OR m.fecha_operacion IS NULL)';
        }

        $sql = sprintf(
            'WITH lote AS (
                SELECT i.id
                FROM %1$s AS i
                WHERE i.cuenta_bancaria_zetti_id = :cuenta_bancaria_id
                  AND i.inicio_periodo = :inicio_periodo
            ), pagina AS (
                SELECT m.id, m.fecha_operacion, m.referencia, m.descripcion,
                       m.credito, m.debito, m.subtipo_valor_zetti_id
                FROM %2$s AS m
                JOIN lote AS l ON l.id = m.importacion_id
                WHERE 1 = 1
                  %6$s
                ORDER BY m.fecha_operacion NULLS LAST, m.id
                LIMIT :limite
            )
            SELECT p.*, h.estado_id, h.usuario_hub_id,
                   a.valor_zetti_id, a.asiento_zetti_id, a.borrador_asiento_id,
                   ultimo_mensaje.emitido_en AS ultimo_mensaje_en
            FROM pagina AS p
            LEFT JOIN LATERAL (
                SELECT ha.estado_id, ha.usuario_hub_id
                FROM %3$s AS ha
                WHERE ha.movimiento_id = p.id
                ORDER BY ha.registrado_en DESC, ha.id DESC
                LIMIT 1
            ) AS h ON true
            LEFT JOIN %4$s AS a
                   ON a.movimiento_id = p.id AND a.activo
            LEFT JOIN LATERAL (
                SELECT mm.emitido_en
                FROM %5$s AS mm
                WHERE mm.movimiento_id = p.id
                ORDER BY mm.emitido_en DESC, mm.id DESC
                LIMIT 1
            ) AS ultimo_mensaje ON true
            ORDER BY p.fecha_operacion NULLS LAST, p.id',
            $importacion,
            $movimiento,
            $historial,
            $asociacion,
            $mensaje,
            $condicionCursor
        );

        $consulta = $this->pdo->prepare($sql);
        $consulta->bindValue(':cuenta_bancaria_id', $cuentaBancariaId, PDO::PARAM_INT);
        $consulta->bindValue(':inicio_periodo', $inicioPeriodo);
        if ($tieneCursor) {
            if ($cursor['fecha'] !== null) {
                $consulta->bindValue(':cursor_fecha', $cursor['fecha']);
            }
            $consulta->bindValue(':cursor_id', $cursor['id'], PDO::PARAM_INT);
        }
        $consulta->bindValue(':limite', $detectarSiguiente ? $limite + 1 : $limite, PDO::PARAM_INT);
        $consulta->execute();
        $movimientos = $consulta->fetchAll(PDO::FETCH_ASSOC);
        $hayMas = $detectarSiguiente && count($movimientos) > $limite;
        if ($hayMas) {
            array_pop($movimientos);
        }

        return [
            'movimientos' => $movimientos,
            'hay_mas' => $hayMas,
        ];
    }

    private function enteroPositivo($valor, $nombre)
    {
        if (filter_var($valor, FILTER_VALIDATE_INT) === false || (int) $valor <= 0) {
            throw new InvalidArgumentException($nombre . ' debe ser un entero positivo.');
        }
        return (int) $valor;
    }

    private function limite($limite)
    {
        $limite = $this->enteroPositivo($limite, 'limite');
        if ($limite > 100) {
            throw new InvalidArgumentException('limite no puede superar 100.');
        }
        return $limite;
    }

    private function fecha($valor, $nombre)
    {
        if (!is_string($valor) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor)) {
            throw new InvalidArgumentException($nombre . ' debe usar el formato YYYY-MM-DD.');
        }
        return $valor;
    }

    private function cursor($cursor)
    {
        if ($cursor === null) {
            return ['fecha' => null, 'id' => null];
        }
        if (!is_array($cursor) || !array_key_exists('fecha', $cursor) || !array_key_exists('id', $cursor)) {
            throw new InvalidArgumentException('cursor debe contener fecha e id.');
        }
        return [
            'fecha' => $cursor['fecha'] === null
                ? null
                : $this->fecha($cursor['fecha'], 'cursor.fecha'),
            'id' => $this->enteroPositivo($cursor['id'], 'cursor.id'),
        ];
    }
}
