<?php
namespace AppBancos\Repository;

use AppBancos\Infrastructure\EsquemaBancos;
use InvalidArgumentException;
use PDO;

final class BandejaMensualRepository
{
    private $pdo;
    private $esquemas;
    private $usuarioHubId;

    public function __construct(PDO $pdo, EsquemaBancos $esquemas, $usuarioHubId = null)
    {
        $this->pdo = $pdo;
        $this->esquemas = $esquemas;
        $this->usuarioHubId = $usuarioHubId === null ? null : (int) $usuarioHubId;
    }

    public function listar($cuentaBancariaId, $inicioPeriodo, array $cursor = null, $limite = 50, array $filtros = [])
    {
        return $this->consultar($cuentaBancariaId, $inicioPeriodo, $cursor, $limite, false, $filtros)['movimientos'];
    }

    public function listarPagina($cuentaBancariaId, $inicioPeriodo, array $cursor = null, $limite = 50, array $filtros = [])
    {
        return $this->consultar($cuentaBancariaId, $inicioPeriodo, $cursor, $limite, true, $filtros);
    }

    private function consultar(
        $cuentaBancariaId,
        $inicioPeriodo,
        array $cursor = null,
        $limite = 50,
        $detectarSiguiente = false,
        array $filtros = []
    ) {
        $cuentaBancariaId = $this->enteroPositivo($cuentaBancariaId, 'cuentaBancariaId');
        $limite = $this->limite($limite);
        $inicioPeriodo = $this->fecha($inicioPeriodo, 'inicioPeriodo');
        $tieneCursor = $cursor !== null;
        $cursor = $this->cursor($cursor);
        $filtros = $this->filtros($filtros);

        $importacion = $this->esquemas->tablaBancos('bancos_importacion_extracto');
        $movimiento = $this->esquemas->tablaBancos('bancos_movimiento_extracto');
        $historial = $this->esquemas->tablaBancos('bancos_historial_asignacion');
        $asociacion = $this->esquemas->tablaBancos('bancos_asociacion_movimiento');
        $mensaje = $this->esquemas->tablaBancos('bancos_mensaje_movimiento');
        $recepcionMensaje = $this->esquemas->tablaBancos('bancos_recepcion_mensaje');
        $estado = $this->esquemas->tablaBancos('bancos_estado');
        $borrador = $this->esquemas->tablaBancos('bancos_borrador_asiento');
        $lineaBorrador = $this->esquemas->tablaBancos('bancos_linea_borrador_asiento');

        $condiciones = [];
        if ($tieneCursor) {
            $condiciones[] = $cursor['fecha'] === null
                ? 'm.fecha_operacion IS NULL AND m.id > :cursor_id'
                : '(m.fecha_operacion > :cursor_fecha
                    OR (m.fecha_operacion = :cursor_fecha AND m.id > :cursor_id)
                    OR m.fecha_operacion IS NULL)';
        }
        if ($filtros['estado'] !== null) {
            $condiciones[] = 'COALESCE(estado_historial.codigo, estado_importacion.codigo) = :filtro_estado';
        }
        if ($filtros['responsable_id'] !== null) {
            $condiciones[] = 'h.usuario_hub_id = :filtro_responsable';
        }
        if ($filtros['asociacion'] !== null) {
            $existe = 'EXISTS (SELECT 1 FROM ' . $asociacion
                . ' asociacion_filtro WHERE asociacion_filtro.movimiento_id = m.id'
                . ' AND asociacion_filtro.activo)';
            $condiciones[] = $filtros['asociacion'] === 'CON' ? $existe : 'NOT ' . $existe;
        }
        if ($filtros['mensajes'] !== null) {
            $existe = 'EXISTS (SELECT 1 FROM ' . $mensaje
                . ' mensaje_filtro WHERE mensaje_filtro.movimiento_id = m.id)';
            $condiciones[] = $filtros['mensajes'] === 'CON' ? $existe : 'NOT ' . $existe;
        }
        $whereAdicional = $condiciones ? 'AND ' . implode("\n                  AND ", $condiciones) : '';

        $sql = sprintf(
            'WITH lote AS (
                SELECT i.id, i.estado_id
                FROM %1$s AS i
                WHERE i.cuenta_bancaria_zetti_id = :cuenta_bancaria_id
                  AND i.inicio_periodo = :inicio_periodo
            ), pagina AS (
                SELECT m.id, m.fecha_operacion, m.referencia, m.descripcion,
                       m.codigo_extracto, m.credito, m.debito, m.moneda,
                       m.subtipo_valor_zetti_id,
                       h.estado_id, h.usuario_hub_id,
                       COALESCE(estado_historial.codigo, estado_importacion.codigo) AS estado_codigo
                FROM %2$s AS m
                JOIN lote AS l ON l.id = m.importacion_id
                LEFT JOIN LATERAL (
                    SELECT ha.estado_id, ha.usuario_hub_id
                    FROM %3$s AS ha
                    WHERE ha.movimiento_id = m.id
                    ORDER BY ha.registrado_en DESC, ha.id DESC
                    LIMIT 1
                ) AS h ON true
                LEFT JOIN %7$s AS estado_historial ON estado_historial.id = h.estado_id
                LEFT JOIN %7$s AS estado_importacion ON estado_importacion.id = l.estado_id
                WHERE 1 = 1
                  %6$s
                ORDER BY m.fecha_operacion NULLS LAST, m.id
                LIMIT :limite
            )
            SELECT p.*,
                   resumen_asociacion.valor_zetti_id,
                   resumen_asociacion.asiento_zetti_id,
                   resumen_asociacion.borrador_asiento_id,
                   ultimo_mensaje.emitido_en AS ultimo_mensaje_en,
                   ultimo_mensaje.tipo_mensaje AS ultimo_mensaje_tipo,
                   ultimo_mensaje.cuerpo AS ultimo_mensaje_cuerpo,
                   resumen_borrador.id AS borrador_id,
                   resumen_borrador.fecha_contable AS borrador_fecha_contable,
                   resumen_borrador.modelo AS borrador_modelo,
                   resumen_borrador.total_debe AS borrador_total_debe,
                   resumen_borrador.total_haber AS borrador_total_haber
            FROM pagina AS p
            LEFT JOIN LATERAL (
                SELECT max(a.valor_zetti_id) AS valor_zetti_id,
                       max(a.asiento_zetti_id) AS asiento_zetti_id,
                       max(a.borrador_asiento_id) AS borrador_asiento_id
                FROM %4$s AS a
                WHERE a.movimiento_id = p.id AND a.activo
            ) AS resumen_asociacion ON true
            LEFT JOIN LATERAL (
                SELECT mm.emitido_en, mm.tipo_mensaje, mm.cuerpo
                FROM %5$s AS mm
                WHERE mm.movimiento_id = p.id
                ORDER BY mm.emitido_en DESC, mm.id DESC
                LIMIT 1
            ) AS ultimo_mensaje ON true
            LEFT JOIN LATERAL (
                SELECT b.id, b.fecha_contable, b.modelo,
                       COALESCE(sum(l.debe), 0) AS total_debe,
                       COALESCE(sum(l.haber), 0) AS total_haber
                FROM %8$s AS b
                LEFT JOIN %9$s AS l ON l.borrador_asiento_id = b.id
                WHERE b.movimiento_id = p.id AND b.activo
                GROUP BY b.id, b.fecha_contable, b.modelo
                ORDER BY b.id DESC
                LIMIT 1
            ) AS resumen_borrador ON true
            ORDER BY p.fecha_operacion NULLS LAST, p.id',
            $importacion,
            $movimiento,
            $historial,
            $asociacion,
            $mensaje,
            $whereAdicional,
            $estado,
            $borrador,
            $lineaBorrador
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
        if ($filtros['estado'] !== null) {
            $consulta->bindValue(':filtro_estado', $filtros['estado']);
        }
        if ($filtros['responsable_id'] !== null) {
            $consulta->bindValue(':filtro_responsable', $filtros['responsable_id'], PDO::PARAM_INT);
        }
        $consulta->bindValue(':limite', $detectarSiguiente ? $limite + 1 : $limite, PDO::PARAM_INT);
        $consulta->execute();
        $movimientos = $consulta->fetchAll(PDO::FETCH_ASSOC);
        $hayMas = $detectarSiguiente && count($movimientos) > $limite;
        if ($hayMas) {
            array_pop($movimientos);
        }

        $this->cargarDetalles(
            $movimientos,
            $asociacion,
            $mensaje,
            $recepcionMensaje,
            $historial,
            $estado,
            $borrador,
            $lineaBorrador
        );

        return ['movimientos' => $movimientos, 'hay_mas' => $hayMas];
    }

    private function cargarDetalles(
        array &$movimientos,
        $asociacion,
        $mensaje,
        $recepcionMensaje,
        $historial,
        $estado,
        $borrador,
        $lineaBorrador
    ) {
        if (!$movimientos) {
            return;
        }
        $indices = [];
        $ids = [];
        foreach ($movimientos as $indice => &$movimiento) {
            $id = (int) $movimiento['id'];
            $indices[$id] = $indice;
            $ids[] = $id;
            $movimiento['asociaciones'] = [];
            $movimiento['mensajes'] = [];
            $movimiento['mensajes_no_leidos'] = 0;
            $movimiento['historial'] = [];
            $movimiento['borradores'] = [];
        }
        unset($movimiento);

        $cuentaErp = $this->esquemas->tablaLecturaErp('cuenta');
        $filas = $this->consultarIds(
            "SELECT a.movimiento_id, a.id, a.valor_zetti_id, a.asiento_zetti_id,
                    a.borrador_asiento_id, a.monto_asociado, a.compartido,
                    a.observacion, a.fecha_creacion
             FROM {$asociacion} AS a
             WHERE a.movimiento_id IN (%IDS%) AND a.activo
             ORDER BY a.movimiento_id, a.fecha_creacion, a.id",
            $ids
        );
        foreach ($filas as $fila) {
            $movimientos[$indices[(int) $fila['movimiento_id']]]['asociaciones'][] = $fila;
        }

        $usuarioHubId = $this->usuarioHubId !== null && $this->usuarioHubId > 0
            ? $this->usuarioHubId
            : 0;
        $filas = $this->consultarIds(
            "SELECT mm.movimiento_id, mm.id, mm.tipo_mensaje, mm.cuerpo,
                    mm.emitido_en, mm.emisor_hub_id, rl.usuario AS emisor,
                    rm.leido_en,
                    (mm.emisor_hub_id = {$usuarioHubId} OR rm.leido_en IS NOT NULL) AS leido
             FROM {$mensaje} AS mm
             LEFT JOIN global_prod.rrhh_login AS rl ON rl.id = mm.emisor_hub_id
             LEFT JOIN {$recepcionMensaje} AS rm
               ON rm.mensaje_id = mm.id AND rm.receptor_hub_id = {$usuarioHubId}
             WHERE mm.movimiento_id IN (%IDS%)
             ORDER BY mm.movimiento_id, mm.emitido_en, mm.id",
            $ids
        );
        foreach ($filas as $fila) {
            $indice = $indices[(int) $fila['movimiento_id']];
            $movimientos[$indice]['mensajes'][] = $fila;
            if (!in_array($fila['leido'], [true, 1, '1', 't'], true)) {
                $movimientos[$indice]['mensajes_no_leidos']++;
            }
        }

        $filas = $this->consultarIds(
            "SELECT h.movimiento_id, h.id, e.codigo AS estado_codigo,
                    h.usuario_hub_id, rl.usuario, h.motivo, h.observacion,
                    h.registrado_en
             FROM {$historial} AS h
             JOIN {$estado} AS e ON e.id = h.estado_id
             LEFT JOIN global_prod.rrhh_login AS rl ON rl.id = h.usuario_hub_id
             WHERE h.movimiento_id IN (%IDS%)
             ORDER BY h.movimiento_id, h.registrado_en, h.id",
            $ids
        );
        foreach ($filas as $fila) {
            $movimientos[$indices[(int) $fila['movimiento_id']]]['historial'][] = $fila;
        }

        $filas = $this->consultarIds(
            "SELECT b.movimiento_id, b.id AS borrador_id, b.fecha_contable,
                    b.modelo, b.observacion AS borrador_observacion,
                    e.codigo AS estado_codigo, b.operacion_zetti_id,
                    b.asiento_zetti_id, l.id AS linea_id, l.cuenta_zetti_id,
                    c.codigo AS cuenta_codigo, c.nombre AS cuenta_nombre,
                    l.debe, l.haber, l.observacion AS linea_observacion
             FROM {$borrador} AS b
             JOIN {$estado} AS e ON e.id = b.estado_id
             LEFT JOIN {$lineaBorrador} AS l ON l.borrador_asiento_id = b.id
             LEFT JOIN {$cuentaErp} AS c ON c.id = l.cuenta_zetti_id
             WHERE b.movimiento_id IN (%IDS%) AND b.activo
             ORDER BY b.movimiento_id, b.id, l.id",
            $ids
        );
        $borradores = [];
        foreach ($filas as $fila) {
            $movimientoId = (int) $fila['movimiento_id'];
            $borradorId = (int) $fila['borrador_id'];
            if (!isset($borradores[$movimientoId][$borradorId])) {
                $borradores[$movimientoId][$borradorId] = [
                    'id' => $borradorId,
                    'fecha_contable' => $fila['fecha_contable'],
                    'modelo' => $fila['modelo'],
                    'observacion' => $fila['borrador_observacion'],
                    'estado_codigo' => $fila['estado_codigo'],
                    'operacion_zetti_id' => $fila['operacion_zetti_id'],
                    'asiento_zetti_id' => $fila['asiento_zetti_id'],
                    'lineas' => [],
                ];
            }
            if ($fila['linea_id'] !== null) {
                $borradores[$movimientoId][$borradorId]['lineas'][] = [
                    'id' => (int) $fila['linea_id'],
                    'cuenta_zetti_id' => (int) $fila['cuenta_zetti_id'],
                    'cuenta_codigo' => $fila['cuenta_codigo'],
                    'cuenta_nombre' => $fila['cuenta_nombre'],
                    'debe' => $fila['debe'],
                    'haber' => $fila['haber'],
                    'observacion' => $fila['linea_observacion'],
                ];
            }
        }
        foreach ($borradores as $movimientoId => $porId) {
            $movimientos[$indices[$movimientoId]]['borradores'] = array_values($porId);
        }
    }

    private function consultarIds($sql, array $ids)
    {
        $marcadores = [];
        foreach ($ids as $indice => $id) {
            $marcadores[] = ':movimiento_' . $indice;
        }
        $consulta = $this->pdo->prepare(str_replace('%IDS%', implode(', ', $marcadores), $sql));
        foreach ($ids as $indice => $id) {
            $consulta->bindValue(':movimiento_' . $indice, $id, PDO::PARAM_INT);
        }
        $consulta->execute();
        return $consulta->fetchAll(PDO::FETCH_ASSOC);
    }

    private function filtros(array $filtros)
    {
        $normalizados = [
            'estado' => $filtros['estado'] ?? null,
            'responsable_id' => $filtros['responsable_id'] ?? null,
            'asociacion' => $filtros['asociacion'] ?? null,
            'mensajes' => $filtros['mensajes'] ?? null,
        ];
        if ($normalizados['estado'] !== null
            && !in_array($normalizados['estado'], ['ABIERTO', 'PARA_CERRAR', 'CERRADO'], true)
        ) {
            throw new InvalidArgumentException('filtros.estado no es válido.');
        }
        if ($normalizados['responsable_id'] !== null) {
            $normalizados['responsable_id'] = $this->enteroPositivo(
                $normalizados['responsable_id'],
                'filtros.responsable_id'
            );
        }
        foreach (['asociacion', 'mensajes'] as $nombre) {
            if ($normalizados[$nombre] !== null
                && !in_array($normalizados[$nombre], ['CON', 'SIN'], true)
            ) {
                throw new InvalidArgumentException('filtros.' . $nombre . ' no es válido.');
            }
        }
        return $normalizados;
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
