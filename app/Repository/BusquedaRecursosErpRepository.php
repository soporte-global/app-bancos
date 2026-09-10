<?php
namespace AppBancos\Repository;

use AppBancos\Application\MovimientoNoEncontradoException;
use AppBancos\Application\RecursoNoDisponibleException;
use AppBancos\Application\TransicionMovimientoException;
use AppBancos\Infrastructure\EsquemaBancos;
use PDO;

final class BusquedaRecursosErpRepository
{
    private $pdo;
    private $movimientos;
    private $importaciones;
    private $historial;
    private $estados;
    private $asociaciones;
    private $reservas;
    private $valoresErp;
    private $tiposValorErp;
    private $subtiposValorErp;
    private $estadosValorErp;
    private $asientosErp;
    private $movimientosErp;
    private $nodosErp;
    private $cuentasErp;
    private $entidadesErp;

    public function __construct(PDO $pdo, EsquemaBancos $esquema)
    {
        $this->pdo = $pdo;
        $this->movimientos = $esquema->tablaBancos('bancos_movimiento_extracto');
        $this->importaciones = $esquema->tablaBancos('bancos_importacion_extracto');
        $this->historial = $esquema->tablaBancos('bancos_historial_asignacion');
        $this->estados = $esquema->tablaBancos('bancos_estado');
        $this->asociaciones = $esquema->tablaBancos('bancos_asociacion_movimiento');
        $this->reservas = $esquema->tablaBancos('bancos_reserva_recurso');
        $this->valoresErp = $esquema->tablaLecturaErp('valor');
        $this->tiposValorErp = $esquema->tablaLecturaErp('tipo_valor');
        $this->subtiposValorErp = $esquema->tablaLecturaErp('subtipo_valor');
        $this->estadosValorErp = $esquema->tablaLecturaErp('estado_valor');
        $this->asientosErp = $esquema->tablaLecturaErp('asiento');
        $this->movimientosErp = $esquema->tablaLecturaErp('movimiento');
        $this->nodosErp = $esquema->tablaLecturaErp('nodo');
        $this->cuentasErp = $esquema->tablaLecturaErp('cuenta');
        $this->entidadesErp = $esquema->tablaLecturaErp('entidad');
    }

    public function buscarValores($movimientoId, $cuentaBancariaId, $inicioPeriodo, $limite)
    {
        $objetivo = $this->obtenerMovimiento($movimientoId, $cuentaBancariaId, $inicioPeriodo);
        $consulta = $this->pdo->prepare(
            "WITH parametros AS (
                 SELECT CAST(:monto AS numeric) AS monto, CAST(:fecha AS date) AS fecha
             )
             SELECT v.id::text AS id, v.monto_principal, v.fecha_emision, v.codigo_externo,
                    v.comprobante::text AS comprobante, v.estado AS estado_id, ev.nombre AS estado,
                    v.tipo_valor AS tipo_id, tv.nombre AS tipo,
                    v.subtipo_valor AS subtipo_id, sv.nombre AS subtipo
             FROM {$this->valoresErp} v
             CROSS JOIN parametros p
             LEFT JOIN {$this->tiposValorErp} tv ON tv.id = v.tipo_valor
             LEFT JOIN {$this->subtiposValorErp} sv ON sv.id = v.subtipo_valor
             LEFT JOIN {$this->estadosValorErp} ev ON ev.id = v.estado
             WHERE v.entidad = :cuenta_bancaria_id
               AND v.estado NOT IN (7, 20, 36)
               AND abs(v.monto_principal) >= p.monto
               AND NOT EXISTS (
                   SELECT 1 FROM {$this->reservas} r
                   WHERE r.valor_zetti_id = v.id AND r.activo IS TRUE
               )
               AND NOT EXISTS (
                   SELECT 1 FROM {$this->asociaciones} a
                   WHERE a.valor_zetti_id = v.id AND a.activo IS TRUE
               )
             ORDER BY abs(abs(v.monto_principal) - p.monto),
                      abs(COALESCE(v.fecha_emision::date, p.fecha) - p.fecha),
                      v.id DESC
             LIMIT :limite"
        );
        $consulta->bindValue(':cuenta_bancaria_id', (string) $cuentaBancariaId);
        $consulta->bindValue(':monto', $objetivo['monto']);
        $consulta->bindValue(':fecha', $objetivo['fecha_operacion']);
        $consulta->bindValue(':limite', (int) $limite, PDO::PARAM_INT);
        $consulta->execute();
        return [
            'movimiento' => $objetivo,
            'resultados' => $consulta->fetchAll(PDO::FETCH_ASSOC),
        ];
    }

    public function buscarAsientos($movimientoId, $cuentaBancariaId, $inicioPeriodo, $compartido, $limite)
    {
        $objetivo = $this->obtenerMovimiento($movimientoId, $cuentaBancariaId, $inicioPeriodo);
        $consulta = $this->pdo->prepare(
            "WITH parametros AS (
                 SELECT CAST(:fecha AS date) AS fecha,
                        CAST(:monto AS numeric) AS monto,
                        CAST(:compartido AS boolean) AS compartido
             ), asientos_fecha AS (
                 SELECT a.*
                 FROM parametros p
                 JOIN {$this->asientosErp} a
                   ON a.fecha >= p.fecha - 45 AND a.fecha < p.fecha + 46
                 WHERE p.compartido OR EXISTS (
                     SELECT 1 FROM {$this->movimientosErp} mx
                     WHERE mx.asiento = a.id AND abs(mx.monto) = p.monto
                 )
                 ORDER BY abs(a.fecha::date - p.fecha), a.id DESC
                 LIMIT 500
             ), candidatos AS (
                 SELECT a.id, a.fecha, a.numero, a.nombre, a.nodo_creacion,
                        count(m.id) AS cantidad_lineas,
                        sum(CASE WHEN m.debita THEN abs(m.monto) ELSE 0 END) AS total_debe,
                        sum(CASE WHEN NOT m.debita THEN abs(m.monto) ELSE 0 END) AS total_haber,
                        max(abs(m.monto)) AS importe_maximo
                 FROM parametros p
                 CROSS JOIN asientos_fecha a
                 JOIN {$this->movimientosErp} m ON m.asiento = a.id
                 GROUP BY a.id, a.fecha, a.numero, a.nombre, a.nodo_creacion,
                          p.compartido, p.monto
                 HAVING count(m.id) >= 2
                    AND sum(CASE WHEN m.debita THEN abs(m.monto) ELSE 0 END)
                        = sum(CASE WHEN NOT m.debita THEN abs(m.monto) ELSE 0 END)
                    AND (p.compartido OR max(abs(m.monto)) = p.monto)
             )
             SELECT c.id::text AS id, c.fecha, c.numero::text AS numero, c.nombre,
                    c.nodo_creacion, c.cantidad_lineas, c.total_debe,
                    c.total_haber, c.importe_maximo, n.nombre AS nodo,
                    EXISTS (
                        SELECT 1 FROM {$this->asociaciones} a
                        WHERE a.asiento_zetti_id = c.id AND a.activo IS TRUE
                    ) OR EXISTS (
                        SELECT 1 FROM {$this->reservas} r
                        WHERE r.asiento_zetti_id = c.id AND r.activo IS TRUE
                    ) AS tiene_usos,
                    EXISTS (
                        SELECT 1 FROM {$this->asociaciones} a
                        WHERE a.asiento_zetti_id = c.id AND a.activo IS TRUE AND NOT a.compartido
                    ) OR EXISTS (
                        SELECT 1 FROM {$this->reservas} r
                        WHERE r.asiento_zetti_id = c.id AND r.activo IS TRUE AND NOT r.compartido
                    ) AS tiene_uso_exclusivo
             FROM candidatos c
             CROSS JOIN parametros p
             LEFT JOIN {$this->nodosErp} n ON n.id = c.nodo_creacion
             WHERE (p.compartido AND NOT (
                        EXISTS (
                            SELECT 1 FROM {$this->asociaciones} a
                            WHERE a.asiento_zetti_id = c.id AND a.activo IS TRUE AND NOT a.compartido
                        ) OR EXISTS (
                            SELECT 1 FROM {$this->reservas} r
                            WHERE r.asiento_zetti_id = c.id AND r.activo IS TRUE AND NOT r.compartido
                        )
                    ))
                OR (NOT p.compartido AND NOT (
                        EXISTS (
                            SELECT 1 FROM {$this->asociaciones} a
                            WHERE a.asiento_zetti_id = c.id AND a.activo IS TRUE
                        ) OR EXISTS (
                            SELECT 1 FROM {$this->reservas} r
                            WHERE r.asiento_zetti_id = c.id AND r.activo IS TRUE
                        )
                    ))
             ORDER BY abs(c.fecha::date - p.fecha),
                      abs(c.importe_maximo - p.monto), c.id DESC
             LIMIT :limite"
        );
        $consulta->bindValue(':fecha', $objetivo['fecha_operacion']);
        $consulta->bindValue(':monto', $objetivo['monto']);
        $consulta->bindValue(':compartido', $compartido ? 'true' : 'false');
        $consulta->bindValue(':limite', (int) $limite, PDO::PARAM_INT);
        $consulta->execute();
        return [
            'movimiento' => $objetivo,
            'compartido' => (bool) $compartido,
            'ventana_dias' => 45,
            'resultados' => $consulta->fetchAll(PDO::FETCH_ASSOC),
        ];
    }

    public function buscarNodos($movimientoId, $cuentaBancariaId, $inicioPeriodo, $busqueda, $limite)
    {
        $objetivo = $this->obtenerMovimiento($movimientoId, $cuentaBancariaId, $inicioPeriodo);
        $consulta = $this->pdo->prepare(
            "WITH parametros AS (SELECT lower(CAST(:busqueda AS text)) AS busqueda),
                  preferencia AS (
                      SELECT e.nodo_creacion AS nodo_id
                      FROM {$this->entidadesErp} e
                      WHERE e.id = :cuenta_bancaria_id
                  )
             SELECT n.id::text AS id, n.codigo_jerarquico, n.nombre, n.nombre_corto,
                    COALESCE(n.id = p.nodo_id, false) AS preferido
             FROM {$this->nodosErp} n
             CROSS JOIN parametros x
             LEFT JOIN preferencia p ON true
             WHERE x.busqueda = ''
                OR n.id::text = x.busqueda
                OR position(x.busqueda in lower(COALESCE(n.codigo_jerarquico, ''))) > 0
                OR position(x.busqueda in lower(COALESCE(n.nombre, ''))) > 0
                OR position(x.busqueda in lower(COALESCE(n.nombre_corto, ''))) > 0
             ORDER BY COALESCE(n.id = p.nodo_id, false) DESC,
                      CASE WHEN n.id::text = x.busqueda THEN 0 ELSE 1 END,
                      n.codigo_jerarquico NULLS LAST, n.nombre, n.id
             LIMIT :limite"
        );
        $consulta->bindValue(':busqueda', $busqueda);
        $consulta->bindValue(':cuenta_bancaria_id', (string) $cuentaBancariaId);
        $consulta->bindValue(':limite', (int) $limite, PDO::PARAM_INT);
        $consulta->execute();
        return [
            'movimiento' => $objetivo,
            'resultados' => $consulta->fetchAll(PDO::FETCH_ASSOC),
        ];
    }

    public function buscarCuentas(
        $movimientoId,
        $cuentaBancariaId,
        $inicioPeriodo,
        $nodoId,
        $busqueda,
        $limite
    ) {
        $objetivo = $this->obtenerMovimiento($movimientoId, $cuentaBancariaId, $inicioPeriodo);
        $consulta = $this->pdo->prepare("SELECT 1 FROM {$this->nodosErp} WHERE id = :nodo_id");
        $consulta->execute([':nodo_id' => (int) $nodoId]);
        if ($consulta->fetchColumn() === false) {
            throw new RecursoNoDisponibleException('El nodo ERP indicado no existe.');
        }

        $consulta = $this->pdo->prepare(
            "WITH parametros AS (
                 SELECT lower(CAST(:busqueda AS text)) AS busqueda,
                        CAST(:nodo_id AS integer) AS nodo_id
             )
             SELECT c.id::text AS id, c.codigo, c.nombre, c.nodo::text AS nodo_id,
                    n.nombre AS nodo, c.imputable,
                    (c.nodo = p.nodo_id) AS coincide_nodo
             FROM {$this->cuentasErp} c
             CROSS JOIN parametros p
             LEFT JOIN {$this->nodosErp} n ON n.id = c.nodo
             WHERE position(p.busqueda in lower(
                       c.id::text || ' ' || COALESCE(c.codigo, '') || ' ' || COALESCE(c.nombre, '')
                   )) > 0
             ORDER BY (c.nodo = p.nodo_id) DESC, c.imputable DESC NULLS LAST,
                      CASE WHEN lower(COALESCE(c.codigo, '')) = p.busqueda THEN 0 ELSE 1 END,
                      c.codigo NULLS LAST, c.nombre, c.id
             LIMIT :limite"
        );
        $consulta->bindValue(':busqueda', $busqueda);
        $consulta->bindValue(':nodo_id', (int) $nodoId, PDO::PARAM_INT);
        $consulta->bindValue(':limite', (int) $limite, PDO::PARAM_INT);
        $consulta->execute();
        return [
            'movimiento' => $objetivo,
            'nodo_zetti_id' => (int) $nodoId,
            'resultados' => $consulta->fetchAll(PDO::FETCH_ASSOC),
        ];
    }

    private function obtenerMovimiento($movimientoId, $cuentaBancariaId, $inicioPeriodo)
    {
        $consulta = $this->pdo->prepare(
            "SELECT m.id, m.fecha_operacion, i.cuenta_bancaria_zetti_id::text,
                    CASE WHEN m.credito > 0 THEN m.credito ELSE m.debito END AS monto,
                    COALESCE((
                        SELECT eh.codigo
                        FROM {$this->historial} h
                        JOIN {$this->estados} eh ON eh.id = h.estado_id
                        WHERE h.movimiento_id = m.id
                        ORDER BY h.registrado_en DESC, h.id DESC
                        LIMIT 1
                    ), ei.codigo) AS estado
             FROM {$this->movimientos} m
             JOIN {$this->importaciones} i ON i.id = m.importacion_id
             JOIN {$this->estados} ei ON ei.id = i.estado_id
             WHERE m.id = :movimiento_id
               AND i.cuenta_bancaria_zetti_id = :cuenta_bancaria_id
               AND i.inicio_periodo = :inicio_periodo"
        );
        $consulta->execute([
            ':movimiento_id' => (int) $movimientoId,
            ':cuenta_bancaria_id' => (string) $cuentaBancariaId,
            ':inicio_periodo' => $inicioPeriodo,
        ]);
        $movimiento = $consulta->fetch(PDO::FETCH_ASSOC);
        if ($movimiento === false) {
            throw new MovimientoNoEncontradoException(
                'El movimiento no existe dentro de la cuenta y el periodo indicados.'
            );
        }
        if ($movimiento['estado'] !== 'ABIERTO') {
            throw new TransicionMovimientoException(
                'Solo se pueden buscar recursos para un movimiento ABIERTO.'
            );
        }
        return $movimiento;
    }
}
