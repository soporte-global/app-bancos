<?php

namespace AppBancos\Migration;

use PDO;
use RuntimeException;
use Throwable;

final class SincronizacionSandbox
{
    private const BLOQUEO = 1742609222;

    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function previsualizar()
    {
        $tablas = [];
        $faltantes = 0;
        $conflictos = 0;
        foreach ($this->definiciones() as $definicion) {
            $estado = $this->comparar($definicion);
            $tablas[] = $estado;
            $faltantes += $estado['faltantes'];
            $conflictos += $estado['conflictos'];
        }

        return [
            'estado' => $conflictos > 0 ? 'REQUIERE_REINICIO_SANDBOX' : 'LISTO',
            'faltantes' => $faltantes,
            'conflictos' => $conflictos,
            'tablas' => $tablas,
        ];
    }

    public function asegurarRangoIds()
    {
        if (!$this->tomarBloqueo()) {
            throw new RuntimeException('Ya existe otra sincronización del sandbox en curso.');
        }
        try {
            $this->pdo->beginTransaction();
            $cantidad = $this->reservarSecuenciasSandbox();
            $this->pdo->commit();
            return ['estado' => 'OK', 'secuencias_ajustadas' => $cantidad];
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $error;
        } finally {
            $this->liberarBloqueo();
        }
    }

    public function ejecutar($clave, $reiniciar = false, $aceptarOrigenConflictos = false)
    {
        $clave = trim((string) $clave);
        if ($clave === '' || strlen($clave) > 80 || !preg_match('/^[a-zA-Z0-9._-]+$/', $clave)) {
            throw new RuntimeException('Clave idempotente de sincronización inválida.');
        }
        if (!$this->tomarBloqueo()) {
            throw new RuntimeException('Ya existe otra sincronización del sandbox en curso.');
        }

        try {
            $anterior = $this->buscarEjecucion($clave);
            if ($anterior && $anterior['estado'] === 'OK') {
                return json_decode($anterior['resumen'], true);
            }

            $vista = $this->previsualizar();
            $this->pdo->beginTransaction();
            try {
                $this->registrarInicio($clave, $vista);
                $respaldo = null;
                if ($reiniciar) {
                    $respaldo = $this->respaldarYReiniciar($clave);
                }
                $copiadas = [];
                $actualizadas = [];
                foreach ($this->definiciones() as $definicion) {
                    $actualizadas[$definicion['destino']] = $aceptarOrigenConflictos
                        ? $this->actualizarConflictos($definicion)
                        : 0;
                    $copiadas[$definicion['destino']] = $this->copiar($definicion);
                }
                $pendientes = $this->previsualizar();
                $estado = $pendientes['faltantes'] === 0 && $pendientes['conflictos'] === 0
                    ? 'OK'
                    : 'REQUIERE_DECISION';
                $resultado = [
                    'estado' => $estado,
                    'clave' => $clave,
                    'copiadas' => $copiadas,
                    'total_copiadas' => array_sum($copiadas),
                    'actualizadas_desde_origen' => array_filter($actualizadas),
                    'total_actualizadas' => array_sum($actualizadas),
                    'conflictos_origen_aceptados' => (bool) $aceptarOrigenConflictos,
                    'sandbox_reiniciado' => (bool) $reiniciar,
                    'esquema_respaldo' => $respaldo,
                    'pendientes' => [
                        'faltantes' => $pendientes['faltantes'],
                        'conflictos' => $pendientes['conflictos'],
                        'tablas' => array_values(array_filter($pendientes['tablas'], static function ($tabla) {
                            return $tabla['faltantes'] > 0 || $tabla['conflictos'] > 0;
                        })),
                    ],
                ];
                if ($reiniciar) {
                    $this->reservarSecuenciasSandbox();
                }
                $this->registrarFin($clave, $resultado);
                $this->pdo->commit();
                return $resultado;
            } catch (Throwable $error) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                throw $error;
            }
        } finally {
            $this->liberarBloqueo();
        }
    }

    private function definiciones()
    {
        return [
            $this->def('public', 'cuenta_bancaria', 'global_temp', 'cuenta_bancaria',
                "s.id IN (SELECT cuenta_bancaria_zetti_id FROM global_prod.bancos_configuracion_cuenta
                           UNION SELECT cuenta_bancaria_zetti_id FROM global_prod.bancos_importacion_extracto)", ['id']),
            $this->def('public', 'cuenta', 'global_temp', 'cuenta',
                "s.id IN (SELECT cuenta_contable_zetti_id FROM global_prod.bancos_configuracion WHERE cuenta_contable_zetti_id IS NOT NULL
                           UNION SELECT cuenta_zetti_id FROM global_prod.bancos_mapeo_cuenta_contable
                           UNION SELECT cuenta_zetti_id FROM global_prod.bancos_linea_borrador_asiento)", ['id']),
            $this->def('public', 'nodo', 'global_temp', 'nodo',
                's.id IN (SELECT nodo_zetti_id FROM global_prod.bancos_borrador_asiento)', ['id']),
            $this->def('public', 'valor', 'global_temp', 'valor',
                "s.id IN (SELECT valor_zetti_id FROM global_prod.bancos_asociacion_movimiento WHERE valor_zetti_id IS NOT NULL
                           UNION SELECT valor_zetti_id FROM global_prod.bancos_reserva_recurso WHERE valor_zetti_id IS NOT NULL)", ['id']),
            $this->def('public', 'asiento', 'global_temp', 'asiento',
                "s.id IN (SELECT asiento_zetti_id FROM global_prod.bancos_asociacion_movimiento WHERE asiento_zetti_id IS NOT NULL
                           UNION SELECT asiento_zetti_id FROM global_prod.bancos_reserva_recurso WHERE asiento_zetti_id IS NOT NULL)", ['id']),
            $this->def('global_prod', 'bancos_estado', 'global_temp', 'bancos_estado'),
            $this->def('global_prod', 'bancos_configuracion', 'global_temp', 'bancos_configuracion'),
            $this->def('global_prod', 'bancos_configuracion_cuenta', 'global_temp', 'bancos_configuracion_cuenta'),
            $this->def('global_prod', 'bancos_regla_clasificacion', 'global_temp', 'bancos_regla_clasificacion'),
            $this->def('global_prod', 'bancos_mapeo_cuenta_contable', 'global_temp', 'bancos_mapeo_cuenta_contable'),
            $this->def('global_prod', 'bancos_regla_asignacion_usuario', 'global_temp', 'bancos_regla_asignacion_usuario'),
            $this->def('global_prod', 'bancos_importacion_extracto', 'global_temp', 'bancos_importacion_extracto'),
            $this->def('global_prod', 'bancos_movimiento_extracto', 'global_temp', 'bancos_movimiento_extracto'),
            $this->def('global_prod', 'bancos_borrador_asiento', 'global_temp', 'bancos_borrador_asiento'),
            $this->def('global_prod', 'bancos_linea_borrador_asiento', 'global_temp', 'bancos_linea_borrador_asiento'),
            $this->def('global_prod', 'bancos_mensaje_movimiento', 'global_temp', 'bancos_mensaje_movimiento'),
            $this->def('global_prod', 'bancos_recepcion_mensaje', 'global_temp', 'bancos_recepcion_mensaje'),
            $this->def('global_prod', 'bancos_historial_asignacion', 'global_temp', 'bancos_historial_asignacion'),
            $this->def('global_prod', 'bancos_asociacion_movimiento', 'global_temp', 'bancos_asociacion_movimiento'),
            $this->def('global_prod', 'bancos_reserva_recurso', 'global_temp', 'bancos_reserva_recurso'),
            $this->def('global_prod', 'bancos_conciliacion_cheque', 'global_temp', 'bancos_conciliacion_cheque'),
        ];
    }

    private function def($origenEsquema, $origenTabla, $destinoEsquema, $destinoTabla, $filtro = 'true', array $claves = [])
    {
        return [
            'origen' => $origenEsquema . '.' . $origenTabla,
            'destino' => $destinoEsquema . '.' . $destinoTabla,
            'origen_esquema' => $origenEsquema,
            'origen_tabla' => $origenTabla,
            'destino_esquema' => $destinoEsquema,
            'destino_tabla' => $destinoTabla,
            'filtro' => $filtro,
            'claves' => $claves,
        ];
    }

    private function comparar(array $definicion)
    {
        $columnas = $this->columnasComunes($definicion);
        $claves = $this->claves($definicion);
        $union = $this->union($claves);
        $diferencias = [];
        foreach ($columnas as $columna) {
            $q = $this->identificador($columna);
            $diferencias[] = "s.$q IS DISTINCT FROM t.$q";
        }
        $primera = $this->identificador($claves[0]);
        $sql = 'SELECT '
            . '(SELECT count(*) FROM ' . $definicion['origen'] . ' s WHERE ' . $definicion['filtro'] . ') AS origen, '
            . '(SELECT count(*) FROM ' . $definicion['origen'] . ' s JOIN ' . $definicion['destino'] . ' t ON ' . $union
            . ' WHERE ' . $definicion['filtro'] . ') AS presentes, '
            . '(SELECT count(*) FROM ' . $definicion['origen'] . ' s JOIN ' . $definicion['destino'] . ' t ON ' . $union
            . ' WHERE ' . $definicion['filtro'] . ' AND (' . implode(' OR ', $diferencias) . ')) AS conflictos';
        $fila = $this->pdo->query($sql)->fetch(PDO::FETCH_ASSOC);

        return [
            'origen' => $definicion['origen'],
            'destino' => $definicion['destino'],
            'esperadas' => (int) $fila['origen'],
            'presentes' => (int) $fila['presentes'],
            'faltantes' => (int) $fila['origen'] - (int) $fila['presentes'],
            'conflictos' => (int) $fila['conflictos'],
        ];
    }

    private function copiar(array $definicion)
    {
        $columnas = $this->columnasComunes($definicion);
        $claves = $this->claves($definicion);
        $lista = implode(', ', array_map([$this, 'identificador'], $columnas));
        $seleccion = implode(', ', array_map(static function ($columna) {
            return 's."' . str_replace('"', '""', $columna) . '"';
        }, $columnas));
        $sql = 'INSERT INTO ' . $definicion['destino'] . ' (' . $lista . ') '
            . 'SELECT ' . $seleccion . ' FROM ' . $definicion['origen'] . ' s '
            . 'WHERE ' . $definicion['filtro'] . ' AND NOT EXISTS (SELECT 1 FROM '
            . $definicion['destino'] . ' t WHERE ' . $this->union($claves) . ') '
            . 'ON CONFLICT DO NOTHING';
        return $this->pdo->exec($sql);
    }

    private function actualizarConflictos(array $definicion)
    {
        $columnas = $this->columnasComunes($definicion);
        $claves = $this->claves($definicion);
        $noClaves = array_values(array_diff($columnas, $claves));
        if (!$noClaves) {
            return 0;
        }
        $asignaciones = implode(', ', array_map(function ($columna) {
            $q = $this->identificador($columna);
            return $q . '=s.' . $q;
        }, $noClaves));
        $diferencias = implode(' OR ', array_map(function ($columna) {
            $q = $this->identificador($columna);
            return 't.' . $q . ' IS DISTINCT FROM s.' . $q;
        }, $columnas));
        $sql = 'UPDATE ' . $definicion['destino'] . ' t SET ' . $asignaciones
            . ' FROM ' . $definicion['origen'] . ' s WHERE ' . $definicion['filtro']
            . ' AND ' . $this->union($claves) . ' AND (' . $diferencias . ')';
        return $this->pdo->exec($sql);
    }

    private function columnasComunes(array $definicion)
    {
        $consulta = $this->pdo->prepare(
            "SELECT o.column_name
               FROM information_schema.columns o
               JOIN information_schema.columns d ON d.column_name = o.column_name
              WHERE o.table_schema = :oe AND o.table_name = :ot
                AND d.table_schema = :de AND d.table_name = :dt
              ORDER BY o.ordinal_position"
        );
        $consulta->execute([
            ':oe' => $definicion['origen_esquema'], ':ot' => $definicion['origen_tabla'],
            ':de' => $definicion['destino_esquema'], ':dt' => $definicion['destino_tabla'],
        ]);
        $columnas = $consulta->fetchAll(PDO::FETCH_COLUMN);
        if (!$columnas) {
            throw new RuntimeException('No hay columnas compatibles para ' . $definicion['destino'] . '.');
        }
        return $columnas;
    }

    private function claves(array $definicion)
    {
        if ($definicion['claves']) {
            return $definicion['claves'];
        }
        $consulta = $this->pdo->prepare(
            "SELECT a.attname
               FROM pg_index i
               JOIN pg_class c ON c.oid = i.indrelid
               JOIN pg_namespace n ON n.oid = c.relnamespace
               JOIN pg_attribute a ON a.attrelid = c.oid AND a.attnum = ANY(i.indkey)
              WHERE i.indisprimary AND n.nspname = :esquema AND c.relname = :tabla
              ORDER BY array_position(i.indkey, a.attnum)"
        );
        $consulta->execute([
            ':esquema' => $definicion['destino_esquema'],
            ':tabla' => $definicion['destino_tabla'],
        ]);
        $claves = $consulta->fetchAll(PDO::FETCH_COLUMN);
        if (!$claves) {
            throw new RuntimeException('La tabla destino no tiene PK: ' . $definicion['destino'] . '.');
        }
        return $claves;
    }

    private function union(array $claves)
    {
        return implode(' AND ', array_map(function ($columna) {
            $q = $this->identificador($columna);
            return "t.$q = s.$q";
        }, $claves));
    }

    private function identificador($nombre)
    {
        if (!preg_match('/^[a-z_][a-z0-9_]*$/', $nombre)) {
            throw new RuntimeException('Identificador de sincronización inválido.');
        }
        return '"' . $nombre . '"';
    }

    private function buscarEjecucion($clave)
    {
        $consulta = $this->pdo->prepare(
            'SELECT estado, resumen::text FROM global_temp.bancos_sandbox_sincronizacion WHERE clave=:clave'
        );
        $consulta->execute([':clave' => $clave]);
        return $consulta->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function registrarInicio($clave, array $vista)
    {
        $consulta = $this->pdo->prepare(
            "INSERT INTO global_temp.bancos_sandbox_sincronizacion(clave,estado,resumen)
             VALUES (:clave,'EN_CURSO',CAST(:resumen AS jsonb))
             ON CONFLICT (clave) DO UPDATE SET estado='EN_CURSO',inicio_en=now(),fin_en=NULL,
                 resumen=EXCLUDED.resumen,error=NULL"
        );
        $consulta->execute([':clave' => $clave, ':resumen' => json_encode($vista)]);
    }

    private function registrarFin($clave, array $resultado)
    {
        $consulta = $this->pdo->prepare(
            "UPDATE global_temp.bancos_sandbox_sincronizacion
                SET estado=:estado,fin_en=now(),resumen=CAST(:resumen AS jsonb),error=NULL
              WHERE clave=:clave"
        );
        $consulta->execute([
            ':clave' => $clave,
            ':estado' => $resultado['estado'],
            ':resumen' => json_encode($resultado),
        ]);
    }

    private function respaldarYReiniciar($clave)
    {
        $esquema = 'bancos_sandbox_backup_' . date('Ymd_His') . '_'
            . substr(hash('sha256', $clave), 0, 8);
        $qEsquema = $this->identificador($esquema);
        $this->pdo->exec('CREATE SCHEMA ' . $qEsquema);

        $tablas = $this->pdo->query(
            "SELECT table_name
               FROM information_schema.tables
              WHERE table_schema='global_temp' AND table_type='BASE TABLE'
                AND table_name <> 'bancos_sandbox_sincronizacion'
              ORDER BY table_name"
        )->fetchAll(PDO::FETCH_COLUMN);
        foreach ($tablas as $tabla) {
            $qTabla = $this->identificador($tabla);
            if ((bool) $this->pdo->query('SELECT EXISTS (SELECT 1 FROM global_temp.' . $qTabla . ' LIMIT 1)')->fetchColumn()) {
                $this->pdo->exec(
                    'CREATE TABLE ' . $qEsquema . '.' . $qTabla
                    . ' AS TABLE global_temp.' . $qTabla
                );
            }
        }

        $destinos = [];
        foreach ($this->definiciones() as $definicion) {
            $destinos[$definicion['destino']] = true;
        }
        $this->pdo->exec('TRUNCATE TABLE ' . implode(', ', array_keys($destinos)) . ' CASCADE');
        return $esquema;
    }

    private function reservarSecuenciasSandbox()
    {
        $secuencias = $this->pdo->query(
            "SELECT DISTINCT c.table_name, c.column_name, pg_get_serial_sequence(
                       quote_ident(c.table_schema) || '.' || quote_ident(c.table_name),
                       c.column_name
                   ) AS secuencia
               FROM information_schema.columns c
              WHERE c.table_schema='global_temp' AND c.data_type='bigint'
                AND c.column_default LIKE 'nextval(%'"
        )->fetchAll(PDO::FETCH_ASSOC);
        $ajustadas = 0;
        foreach ($secuencias as $fila) {
            $secuencia = $fila['secuencia'];
            if ($secuencia === null || $secuencia === '') {
                continue;
            }
            if (!preg_match('/^global_temp\.[a-z_][a-z0-9_]*$/', $secuencia)) {
                throw new RuntimeException('Secuencia sandbox inválida: ' . $secuencia);
            }
            $tabla = $this->identificador($fila['table_name']);
            $columna = $this->identificador($fila['column_name']);
            $maximo = (string) $this->pdo->query(
                'SELECT COALESCE(max(' . $columna . '),0)::text FROM global_temp.' . $tabla
            )->fetchColumn();
            $valor = max(8000000000000000000, (int) $maximo);
            $this->pdo->query("SELECT setval('" . $secuencia . "', " . $valor . ', true)');
            $ajustadas++;
        }
        $erp = [
            'asiento_sq' => 'asiento', 'movimiento_sq' => 'movimiento',
            'operacion_sq' => 'operacion', 'operacion_valor_sq' => 'operacion_valor',
            'valor_sq' => 'valor', 'valor_concepto_sq' => 'valor_concepto',
        ];
        foreach ($erp as $secuencia => $tabla) {
            $maximo = (string) $this->pdo->query(
                'SELECT COALESCE(max(id),0)::text FROM global_temp.' . $this->identificador($tabla)
            )->fetchColumn();
            $valor = max(8000000000000000000, (int) $maximo);
            $this->pdo->query(
                "SELECT setval('global_temp." . $secuencia . "', " . $valor . ", true)"
            );
            $ajustadas++;
        }
        return $ajustadas;
    }

    private function tomarBloqueo()
    {
        $consulta = $this->pdo->query('SELECT pg_try_advisory_lock(' . self::BLOQUEO . ')');
        return (bool) $consulta->fetchColumn();
    }

    private function liberarBloqueo()
    {
        $this->pdo->query('SELECT pg_advisory_unlock(' . self::BLOQUEO . ')');
    }
}
