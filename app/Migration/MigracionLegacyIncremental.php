<?php

namespace AppBancos\Migration;

use PDO;
use RuntimeException;
use Throwable;

final class MigracionLegacyIncremental
{
    private const LOCK_KEY = 1742609221;

    private $pdo;
    private $savepointSecuencia = 0;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function previsualizar()
    {
        $metricas = [
            'cuentas_nuevas' => $this->entero("WITH cuentas AS (
                SELECT num_cuenta cuenta FROM public.bancos_mes_periodos_cargados_cuenta
                UNION SELECT cuenta FROM public.bancos_mes_guardado_datos
                UNION SELECT cuenta FROM public.bancos_guardado_datos
            ) SELECT count(*) FROM cuentas c
              LEFT JOIN global_prod.bancos_migracion_cuenta_legacy t ON t.cuenta_legacy = c.cuenta
             WHERE t.cuenta_legacy IS NULL AND c.cuenta IS NOT NULL
               AND btrim(c.cuenta) <> '' AND c.cuenta <> 'N/A'"),
            'configuraciones_nuevas' => $this->entero("WITH origen AS (
                SELECT DISTINCT 'BANCOS'::varchar tipo, d.id, d.cuenta, lb.banco_id::bigint banco_id
                  FROM public.bancos_guardado_datos d
                  JOIN public.bancos_guardado_listabancos lb ON lb.id=d.id AND lb.cuenta=d.cuenta
                 WHERE lb.banco_id ~ '^[0-9]+$'
                UNION
                SELECT DISTINCT 'MENSUAL', d.id, d.cuenta, lb.banco_id::bigint
                  FROM public.bancos_mes_guardado_datos d
                  JOIN public.bancos_mes_guardado_listabancos lb ON lb.id=d.id AND lb.cuenta=d.cuenta
                 WHERE lb.banco_id ~ '^[0-9]+$'
            ) SELECT count(*) FROM origen o
              LEFT JOIN global_prod.bancos_migracion_configuracion_legacy t
                ON t.origen=o.tipo AND t.id_legacy=o.id AND t.cuenta_legacy=o.cuenta AND t.banco_zetti_id=o.banco_id
             WHERE t.configuracion_id IS NULL"),
            'periodos_nuevos' => $this->entero("SELECT count(*)
              FROM public.bancos_mes_periodos_cargados_cuenta p
              LEFT JOIN global_prod.bancos_migracion_periodo_legacy t ON t.id_periodo_legacy=p.id_periodo
             WHERE t.id_periodo_legacy IS NULL"),
            'filas_movimiento_nuevas' => $this->entero("SELECT count(*)
              FROM public.bancos_mes_movimientos_cargados_periodo m
              LEFT JOIN global_prod.bancos_migracion_movimiento_legacy t
                ON t.id_periodo_legacy=m.id_periodo AND t.serial_seq_legacy=m.serial_seq
             WHERE t.id_periodo_legacy IS NULL"),
            'asociaciones_valor_pendientes' => $this->entero("SELECT count(*)
              FROM public.bancos_mes_valor_asignado_movimiento v
              JOIN global_prod.bancos_migracion_movimiento_legacy t
                ON t.id_movimiento_legacy=v.id_movimiento AND t.es_canonico AND t.movimiento_id IS NOT NULL
              JOIN public.valor x ON x.id=v.id_valor
              LEFT JOIN global_prod.bancos_migracion_recurso_omitido o
                ON o.tipo_recurso='VALOR' AND o.recurso_zetti_id=v.id_valor AND o.id_movimiento_legacy=v.id_movimiento
             WHERE o.id_movimiento_legacy IS NULL AND NOT EXISTS (
                SELECT 1 FROM global_prod.bancos_asociacion_movimiento z
                 WHERE z.movimiento_id=t.movimiento_id AND z.valor_zetti_id=v.id_valor
             )"),
            'asociaciones_asiento_pendientes' => $this->entero("SELECT count(*)
              FROM public.bancos_mes_asiento_asignado_movimiento a
              JOIN global_prod.bancos_migracion_movimiento_legacy t
                ON t.id_movimiento_legacy=a.id_movimiento AND t.es_canonico AND t.movimiento_id IS NOT NULL
              JOIN public.asiento x ON x.id=a.id_asiento
              LEFT JOIN global_prod.bancos_migracion_recurso_omitido o
                ON o.tipo_recurso='ASIENTO' AND o.recurso_zetti_id=a.id_asiento AND o.id_movimiento_legacy=a.id_movimiento
             WHERE a.id_asiento<>0 AND o.id_movimiento_legacy IS NULL AND NOT EXISTS (
                SELECT 1 FROM global_prod.bancos_asociacion_movimiento z
                 WHERE z.movimiento_id=t.movimiento_id AND z.asiento_zetti_id=a.id_asiento
             )"),
            'reservas_valor_pendientes' => $this->entero("SELECT count(*)
              FROM public.bancos_mes_exclusiones e
              JOIN global_prod.bancos_migracion_movimiento_legacy t
                ON t.id_movimiento_legacy=e.id_movimiento AND t.es_canonico AND t.movimiento_id IS NOT NULL
              JOIN public.valor x ON x.id=e.id_valor
             WHERE e.id_valor IS NOT NULL AND NOT EXISTS (
                SELECT 1 FROM global_prod.bancos_reserva_recurso z
                 WHERE z.movimiento_id=t.movimiento_id AND z.valor_zetti_id=e.id_valor
             )"),
            'reservas_asiento_pendientes' => $this->entero("SELECT count(*)
              FROM public.bancos_mes_exclusiones e
              JOIN global_prod.bancos_migracion_movimiento_legacy t
                ON t.id_movimiento_legacy=e.id_movimiento AND t.es_canonico AND t.movimiento_id IS NOT NULL
              JOIN public.asiento x ON x.id=e.id_asiento
             WHERE e.id_asiento IS NOT NULL AND e.id_asiento<>0 AND NOT EXISTS (
                SELECT 1 FROM global_prod.bancos_reserva_recurso z
                 WHERE z.movimiento_id=t.movimiento_id AND z.asiento_zetti_id=e.id_asiento
             )"),
            'borradores_pendientes' => $this->entero("SELECT count(*)
              FROM public.bancos_mes_asientos_creados_movimiento h
              JOIN global_prod.bancos_migracion_movimiento_legacy t
                ON t.id_movimiento_legacy=h.id_movimiento AND t.es_canonico AND t.movimiento_id IS NOT NULL
              JOIN public.nodo n ON n.id=h.nodo_creacion::integer
              LEFT JOIN global_prod.bancos_migracion_borrador_legacy mb ON mb.id_interno_asiento=h.id_interno_asiento
             WHERE mb.id_interno_asiento IS NULL"),
        ];

        $bloqueos = [];
        $this->agregarBloqueos($bloqueos, 'CUENTA_SIN_EQUIVALENCIA', "WITH cuentas AS (
            SELECT num_cuenta cuenta FROM public.bancos_mes_periodos_cargados_cuenta
            UNION SELECT cuenta FROM public.bancos_mes_guardado_datos
            UNION SELECT cuenta FROM public.bancos_guardado_datos
        ), nuevas AS (
            SELECT c.cuenta FROM cuentas c
            LEFT JOIN global_prod.bancos_migracion_cuenta_legacy t ON t.cuenta_legacy=c.cuenta
            WHERE t.cuenta_legacy IS NULL AND c.cuenta IS NOT NULL AND btrim(c.cuenta)<>'' AND c.cuenta<>'N/A'
        ) SELECT n.cuenta AS referencia, count(cb.id)::text AS detalle
          FROM nuevas n
          LEFT JOIN public.entidad e
            ON ltrim(regexp_replace(e.codigo,'[^[:alnum:]]','','g'),'0')
             = ltrim(regexp_replace(n.cuenta,'[^[:alnum:]]','','g'),'0')
          LEFT JOIN public.cuenta_bancaria cb ON cb.id=e.id
         GROUP BY n.cuenta HAVING count(cb.id)<>1");
        $this->agregarBloqueos($bloqueos, 'PERIODO_CUENTA_PENDIENTE', "SELECT p.id_periodo AS referencia, p.num_cuenta AS detalle
          FROM public.bancos_mes_periodos_cargados_cuenta p
          LEFT JOIN global_prod.bancos_migracion_periodo_legacy tp ON tp.id_periodo_legacy=p.id_periodo
          JOIN global_prod.bancos_migracion_cuenta_legacy c ON c.cuenta_legacy=p.num_cuenta
         WHERE tp.id_periodo_legacy IS NULL AND (c.metodo='PENDIENTE' OR c.omitir)");
        $this->agregarBloqueos($bloqueos, 'PERIODO_CONFIGURACION_AMBIGUA', "WITH nuevos AS (
            SELECT p.id_periodo,p.num_cuenta FROM public.bancos_mes_periodos_cargados_cuenta p
            LEFT JOIN global_prod.bancos_migracion_periodo_legacy t ON t.id_periodo_legacy=p.id_periodo
            WHERE t.id_periodo_legacy IS NULL
        ), candidatos AS (
            SELECT n.id_periodo,count(DISTINCT (lb.id,lb.cuenta,lb.banco_id)) cantidad
            FROM nuevos n JOIN public.bancos_mes_movimientos_cargados_periodo m ON m.id_periodo=n.id_periodo
            JOIN public.bancos_mes_guardado_listabancos lb ON lb.cuenta=n.num_cuenta AND btrim(lb.banco)=btrim(m.banco)
            WHERE lb.banco_id~'^[0-9]+$' GROUP BY n.id_periodo
        ) SELECT n.id_periodo referencia,COALESCE(c.cantidad,0)::text detalle FROM nuevos n LEFT JOIN candidatos c ON c.id_periodo=n.id_periodo
        WHERE COALESCE(c.cantidad,0)<>1");
        $this->agregarBloqueos($bloqueos, 'MAPEO_CONTABLE_CONFLICTIVO', "WITH fuente AS (
            SELECT DISTINCT mc.configuracion_id, v.id_stv::smallint subtipo, v.id_cuenta_contable::bigint cuenta
              FROM (SELECT 'BANCOS'::varchar origen,cuenta,id_stv,id_cuenta_contable FROM public.bancos_guardado_cuentasvalores
                    UNION ALL SELECT 'MENSUAL',cuenta,id_stv,id_cuenta_contable FROM public.bancos_mes_guardado_cuentasvalores) v
              JOIN global_prod.bancos_migracion_configuracion_legacy mc ON mc.origen=v.origen AND mc.cuenta_legacy=v.cuenta
              JOIN public.cuenta c ON c.id=v.id_cuenta_contable::bigint
             WHERE v.id_stv~'^[0-9]+$' AND v.id_cuenta_contable~'^[0-9]+$'
        ) SELECT (f.configuracion_id::text||':'||f.subtipo::text) referencia,
                 ('destino='||m.cuenta_zetti_id::text||',legacy='||f.cuenta::text) detalle
          FROM fuente f JOIN global_prod.bancos_mapeo_cuenta_contable m
            ON m.configuracion_id=f.configuracion_id AND m.subtipo_valor_zetti_id=f.subtipo AND m.activo
         WHERE m.cuenta_zetti_id<>f.cuenta");
        $this->agregarBloqueos($bloqueos, 'EMISOR_SIN_MAPEO', "SELECT DISTINCT m.id_usuario_emisor referencia, 'mensaje legacy' detalle
          FROM public.bancos_mensajeria m
          LEFT JOIN global_prod.bancos_migracion_emisor_legacy e ON e.id_usuario_legacy=m.id_usuario_emisor
         WHERE e.id_usuario_legacy IS NULL");
        $this->agregarBloqueos($bloqueos, 'LINEA_BORRADOR_SIN_CUENTA', "WITH lineas AS (
            SELECT l.id_interno_asiento,l.nombre,l.debe,l.haber,COALESCE(NULLIF(btrim(l.codigo_cuenta),''),(
                SELECT min(l2.codigo_cuenta) FROM public.bancos_mes_movimientos_creados_asiento l2
                WHERE l2.id_interno_asiento=l.id_interno_asiento AND NULLIF(btrim(l2.codigo_cuenta),'') IS NOT NULL
                  AND lower(btrim(l2.nombre))=lower(btrim(l.nombre)) AND l2.debe=l.haber AND l2.haber=l.debe
                HAVING count(DISTINCT l2.codigo_cuenta)=1)) codigo
            FROM public.bancos_mes_movimientos_creados_asiento l
        ) SELECT (l.id_interno_asiento::text||':'||COALESCE(l.codigo,'SIN_CODIGO')) referencia,'cuentas='||count(c.id)::text detalle
          FROM lineas l JOIN public.bancos_mes_asientos_creados_movimiento h ON h.id_interno_asiento=l.id_interno_asiento
          LEFT JOIN global_prod.bancos_migracion_borrador_legacy mb ON mb.id_interno_asiento=h.id_interno_asiento
          LEFT JOIN public.cuenta c ON c.codigo=l.codigo WHERE mb.id_interno_asiento IS NULL AND (l.debe<>0 OR l.haber<>0)
         GROUP BY l.id_interno_asiento,l.codigo HAVING count(c.id)<>1");
        $this->agregarBloqueos($bloqueos, 'VALOR_YA_USADO_POR_OTRO_MOVIMIENTO', "SELECT v.id_valor::text referencia,('destino='||z.movimiento_id::text||',legacy='||t.movimiento_id::text) detalle
          FROM public.bancos_mes_valor_asignado_movimiento v JOIN global_prod.bancos_migracion_movimiento_legacy t ON t.id_movimiento_legacy=v.id_movimiento AND t.es_canonico AND t.movimiento_id IS NOT NULL
          JOIN global_prod.bancos_asociacion_movimiento z ON z.valor_zetti_id=v.id_valor AND z.activo AND z.movimiento_id<>t.movimiento_id
          LEFT JOIN global_prod.bancos_migracion_recurso_omitido o ON o.tipo_recurso='VALOR' AND o.recurso_zetti_id=v.id_valor AND o.id_movimiento_legacy=v.id_movimiento
         WHERE o.id_movimiento_legacy IS NULL");
        $this->agregarBloqueos($bloqueos, 'ASIENTO_DISTINTO_EN_MOVIMIENTO_ASOCIADO', "SELECT t.movimiento_id::text referencia,('destino='||COALESCE(z.asiento_zetti_id::text,'otro tipo')||',legacy='||a.id_asiento::text) detalle
          FROM public.bancos_mes_asiento_asignado_movimiento a
          JOIN global_prod.bancos_migracion_012_movimiento_canonico t ON t.id_movimiento_legacy=a.id_movimiento
          JOIN global_prod.bancos_asociacion_movimiento z ON z.movimiento_id=t.movimiento_id AND z.activo
          LEFT JOIN global_prod.bancos_migracion_recurso_omitido o ON o.tipo_recurso='ASIENTO' AND o.recurso_zetti_id=a.id_asiento AND o.id_movimiento_legacy=a.id_movimiento
         WHERE a.id_asiento<>0 AND o.id_movimiento_legacy IS NULL AND z.asiento_zetti_id IS DISTINCT FROM a.id_asiento
           AND z.observacion NOT LIKE 'MIGRACION-%'
           AND NOT EXISTS (SELECT 1 FROM global_prod.bancos_asociacion_movimiento e WHERE e.movimiento_id=t.movimiento_id AND e.asiento_zetti_id=a.id_asiento AND e.activo)");
        $this->agregarBloqueos($bloqueos, 'MULTIPLES_ASIENTOS_PARA_MOVIMIENTO', "SELECT t.movimiento_id::text referencia,count(DISTINCT a.id_asiento)::text detalle
          FROM public.bancos_mes_asiento_asignado_movimiento a
          JOIN global_prod.bancos_migracion_012_movimiento_canonico t ON t.id_movimiento_legacy=a.id_movimiento
          LEFT JOIN global_prod.bancos_migracion_recurso_omitido o ON o.tipo_recurso='ASIENTO' AND o.recurso_zetti_id=a.id_asiento AND o.id_movimiento_legacy=a.id_movimiento
         WHERE a.id_asiento<>0 AND o.id_movimiento_legacy IS NULL
         GROUP BY t.movimiento_id HAVING count(DISTINCT a.id_asiento)>1");

        return [
            'estado' => count($bloqueos) > 0 ? 'REQUIERE_DECISION' : 'LISTO',
            'metricas' => $metricas,
            'bloqueos' => $bloqueos,
            'advertencias' => $this->pdo->query("SELECT cuenta_legacy referencia,
                'Cuenta histórica pendiente sin períodos nuevos' detalle
              FROM global_prod.bancos_migracion_cuenta_legacy c
             WHERE c.metodo='PENDIENTE' AND NOT EXISTS (
                SELECT 1 FROM public.bancos_mes_periodos_cargados_cuenta p
                LEFT JOIN global_prod.bancos_migracion_periodo_legacy t ON t.id_periodo_legacy=p.id_periodo
                WHERE p.num_cuenta=c.cuenta_legacy AND t.id_periodo_legacy IS NULL
             ) ORDER BY cuenta_legacy")->fetchAll(PDO::FETCH_ASSOC),
        ];
    }

    public function ejecutar($clave)
    {
        if (!is_string($clave) || !preg_match('/^[A-Za-z0-9._:-]{8,80}$/', $clave)) {
            throw new RuntimeException('La clave debe tener entre 8 y 80 caracteres seguros.');
        }
        if (!$this->tomarBloqueo()) {
            throw new RuntimeException('Ya existe otro catch-up legacy en ejecución.');
        }

        $lote = 'CATCHUP-' . strtoupper(substr(sha1($clave), 0, 24));
        $transaccionExterna = $this->pdo->inTransaction();
        try {
            $existente = $this->buscarEjecucion($clave);
            if ($existente && $existente['estado'] === 'OK') {
                return json_decode($existente['resumen'], true);
            }

            $antes = $this->previsualizar();
            if ($antes['estado'] !== 'LISTO') {
                $this->registrarResultado($clave, $lote, 'REQUIERE_DECISION', $antes, null);
                return $antes;
            }
            $this->registrarInicio($clave, $lote, $antes);

            $this->faseConfiguracion($lote);
            $this->fasePeriodosMovimientos($lote);
            $this->faseDependencias($lote);

            $despues = $this->previsualizar();
            $resultado = [
                'estado' => $despues['estado'] === 'LISTO' ? 'OK' : $despues['estado'],
                'lote_migracion' => $lote,
                'antes' => $antes,
                'despues' => $despues,
            ];
            $this->registrarResultado($clave, $lote, $resultado['estado'], $resultado, null);
            return $resultado;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction() && !$transaccionExterna) {
                $this->pdo->rollBack();
            }
            $this->registrarResultado($clave, $lote, 'ERROR', ['estado' => 'ERROR'], $e->getMessage());
            throw $e;
        } finally {
            $this->pdo->query('SELECT pg_advisory_unlock(' . self::LOCK_KEY . ')');
        }
    }

    private function faseConfiguracion($lote)
    {
        $q = $this->pdo->quote($lote);
        $this->transaccion(function () use ($q) {
            $this->pdo->exec("WITH cuentas AS (
                SELECT num_cuenta cuenta FROM public.bancos_mes_periodos_cargados_cuenta
                UNION SELECT cuenta FROM public.bancos_mes_guardado_datos
                UNION SELECT cuenta FROM public.bancos_guardado_datos
            ) INSERT INTO global_prod.bancos_migracion_cuenta_legacy(cuenta_legacy,lote_migracion)
              SELECT c.cuenta,$q FROM cuentas c
              WHERE c.cuenta IS NOT NULL AND btrim(c.cuenta)<>'' AND c.cuenta<>'N/A'
                AND NOT EXISTS (SELECT 1 FROM global_prod.bancos_migracion_cuenta_legacy t WHERE t.cuenta_legacy=c.cuenta)");
            $this->pdo->exec("WITH candidatos AS (
                SELECT m.cuenta_legacy,min(cb.id) id FROM global_prod.bancos_migracion_cuenta_legacy m
                JOIN public.entidad e ON ltrim(regexp_replace(e.codigo,'[^[:alnum:]]','','g'),'0')=ltrim(regexp_replace(m.cuenta_legacy,'[^[:alnum:]]','','g'),'0')
                JOIN public.cuenta_bancaria cb ON cb.id=e.id WHERE m.lote_migracion=$q
                GROUP BY m.cuenta_legacy HAVING count(*)=1
            ) UPDATE global_prod.bancos_migracion_cuenta_legacy m SET cuenta_bancaria_zetti_id=c.id,metodo='AUTOMATICO',actualizado_en=now()
              FROM candidatos c WHERE c.cuenta_legacy=m.cuenta_legacy");
            $this->pdo->exec("WITH origen AS (
                SELECT DISTINCT 'BANCOS'::varchar tipo,d.id,d.cuenta,lb.banco_id::bigint banco_id FROM public.bancos_guardado_datos d JOIN public.bancos_guardado_listabancos lb ON lb.id=d.id AND lb.cuenta=d.cuenta WHERE lb.banco_id~'^[0-9]+$'
                UNION SELECT DISTINCT 'MENSUAL',d.id,d.cuenta,lb.banco_id::bigint FROM public.bancos_mes_guardado_datos d JOIN public.bancos_mes_guardado_listabancos lb ON lb.id=d.id AND lb.cuenta=d.cuenta WHERE lb.banco_id~'^[0-9]+$'
            ) INSERT INTO global_prod.bancos_migracion_configuracion_legacy(origen,id_legacy,cuenta_legacy,banco_zetti_id,lote_migracion)
              SELECT o.tipo,o.id,o.cuenta,o.banco_id,$q FROM origen o WHERE NOT EXISTS (
                SELECT 1 FROM global_prod.bancos_migracion_configuracion_legacy t WHERE t.origen=o.tipo AND t.id_legacy=o.id AND t.cuenta_legacy=o.cuenta AND t.banco_zetti_id=o.banco_id)");
            $this->pdo->exec("INSERT INTO global_prod.bancos_configuracion(alcance,banco_zetti_id,cuenta_contable_zetti_id,observacion)
              SELECT CASE WHEN mc.cuenta_legacy IN ('GENERAL','NNNNN') THEN 'GLOBAL' ELSE 'CUENTA' END,mc.banco_zetti_id,cb.id_cuenta_contable::bigint,
                     'MIGRACION-CATCHUP|CONFIG|'||mc.origen||'|'||mc.id_legacy||'|'||mc.cuenta_legacy||'|'||mc.banco_zetti_id
              FROM global_prod.bancos_migracion_configuracion_legacy mc LEFT JOIN LATERAL (
                SELECT b.id_cuenta_contable FROM (SELECT 'BANCOS'::varchar origen,cuenta,banco_id,id_cuenta_contable FROM public.bancos_guardado_cuentasbanco UNION ALL SELECT 'MENSUAL',cuenta,banco_id,id_cuenta_contable FROM public.bancos_mes_guardado_cuentasbanco) b
                JOIN public.cuenta c ON c.id=b.id_cuenta_contable::bigint WHERE b.origen=mc.origen AND b.cuenta=mc.cuenta_legacy AND b.banco_id=mc.banco_zetti_id::varchar AND b.id_cuenta_contable~'^[0-9]+$' LIMIT 1
              ) cb ON true WHERE mc.lote_migracion=$q AND mc.configuracion_id IS NULL");
            $this->pdo->exec("UPDATE global_prod.bancos_migracion_configuracion_legacy mc SET configuracion_id=c.id FROM global_prod.bancos_configuracion c
              WHERE mc.lote_migracion=$q AND c.observacion='MIGRACION-CATCHUP|CONFIG|'||mc.origen||'|'||mc.id_legacy||'|'||mc.cuenta_legacy||'|'||mc.banco_zetti_id");
            $this->pdo->exec("INSERT INTO global_prod.bancos_configuracion_cuenta(configuracion_id,cuenta_bancaria_zetti_id,origen)
              SELECT mc.configuracion_id,ml.cuenta_bancaria_zetti_id,'MIGRACION' FROM global_prod.bancos_migracion_configuracion_legacy mc
              JOIN global_prod.bancos_migracion_cuenta_legacy ml ON ml.cuenta_legacy=mc.cuenta_legacy
              WHERE mc.lote_migracion=$q AND ml.cuenta_bancaria_zetti_id IS NOT NULL AND NOT ml.omitir
                AND NOT EXISTS (SELECT 1 FROM global_prod.bancos_configuracion_cuenta z WHERE z.configuracion_id=mc.configuracion_id AND z.cuenta_bancaria_zetti_id=ml.cuenta_bancaria_zetti_id)");
            $this->pdo->exec("INSERT INTO global_prod.bancos_regla_clasificacion(configuracion_id,subtipo_valor_zetti_id,sentido,codigo_extracto,validar_automaticamente,observacion)
              SELECT DISTINCT mc.configuracion_id,r.subtipo::smallint,r.sentido,NULLIF(btrim(r.codigo),''),r.validar,'MIGRACION-CATCHUP|REGLA|'||r.origen
              FROM (SELECT 'BANCOS'::varchar origen,id,cuenta,reglas_idsubtipo_valor subtipo,CASE reglas_debehaber WHEN 'CRÉDITO' THEN 'C' WHEN 'DÉBITO' THEN 'D' END sentido,reglas_codigo_excel codigo,true validar FROM public.bancos_guardado_listareglas
                    UNION ALL SELECT 'MENSUAL',id,cuenta,reglas_idsubtipo_valor,'A',reglas_codigo_excel,COALESCE(validar,false) FROM public.bancos_mes_guardado_listareglas) r
              JOIN global_prod.bancos_migracion_configuracion_legacy mc ON mc.origen=r.origen AND mc.id_legacy=r.id AND mc.cuenta_legacy=r.cuenta
              WHERE r.subtipo~'^[0-9]+$' AND r.sentido IS NOT NULL AND NOT EXISTS (
                SELECT 1 FROM global_prod.bancos_regla_clasificacion z WHERE z.activo AND z.configuracion_id=mc.configuracion_id AND z.subtipo_valor_zetti_id=r.subtipo::smallint AND z.sentido=r.sentido AND COALESCE(z.codigo_extracto,'')=COALESCE(NULLIF(btrim(r.codigo),''),''))");
            $this->pdo->exec("INSERT INTO global_prod.bancos_mapeo_cuenta_contable(configuracion_id,subtipo_valor_zetti_id,cuenta_zetti_id,observacion,origen)
              SELECT DISTINCT mc.configuracion_id,v.id_stv::smallint,v.id_cuenta_contable::bigint,'MIGRACION-CATCHUP|MAPEO|'||v.origen,'MIGRACION'
              FROM (SELECT 'BANCOS'::varchar origen,cuenta,id_stv,id_cuenta_contable FROM public.bancos_guardado_cuentasvalores UNION ALL SELECT 'MENSUAL',cuenta,id_stv,id_cuenta_contable FROM public.bancos_mes_guardado_cuentasvalores) v
              JOIN global_prod.bancos_migracion_configuracion_legacy mc ON mc.origen=v.origen AND mc.cuenta_legacy=v.cuenta JOIN public.cuenta c ON c.id=v.id_cuenta_contable::bigint
              WHERE v.id_stv~'^[0-9]+$' AND v.id_cuenta_contable~'^[0-9]+$' AND NOT EXISTS (
                SELECT 1 FROM global_prod.bancos_mapeo_cuenta_contable z WHERE z.activo AND z.configuracion_id=mc.configuracion_id AND z.subtipo_valor_zetti_id=v.id_stv::smallint)");
        });
    }

    private function fasePeriodosMovimientos($lote)
    {
        $q = $this->pdo->quote($lote);
        $this->transaccion(function () use ($q) {
            $this->pdo->exec("INSERT INTO global_prod.bancos_migracion_periodo_legacy(id_periodo_legacy,cuenta_legacy,num_mes,ano,total_movimientos_legacy,lote_migracion)
              SELECT p.id_periodo,p.num_cuenta,p.num_mes,p.ano,p.total_movs,$q FROM public.bancos_mes_periodos_cargados_cuenta p
              WHERE NOT EXISTS (SELECT 1 FROM global_prod.bancos_migracion_periodo_legacy t WHERE t.id_periodo_legacy=p.id_periodo)");
            $this->pdo->exec("WITH candidatos AS (
                SELECT p.id_periodo_legacy,min(mc.configuracion_id) configuracion_id FROM global_prod.bancos_migracion_periodo_legacy p
                JOIN public.bancos_mes_movimientos_cargados_periodo m ON m.id_periodo=p.id_periodo_legacy
                JOIN public.bancos_mes_guardado_listabancos lb ON lb.cuenta=p.cuenta_legacy AND btrim(lb.banco)=btrim(m.banco)
                JOIN global_prod.bancos_migracion_configuracion_legacy mc ON mc.origen='MENSUAL' AND mc.id_legacy=lb.id AND mc.cuenta_legacy=lb.cuenta AND mc.banco_zetti_id::varchar=lb.banco_id
                WHERE p.lote_migracion=$q GROUP BY p.id_periodo_legacy HAVING count(DISTINCT mc.configuracion_id)=1
            ) UPDATE global_prod.bancos_migracion_periodo_legacy p SET configuracion_id=c.configuracion_id FROM candidatos c WHERE c.id_periodo_legacy=p.id_periodo_legacy");
            if ($this->entero("SELECT count(*) FROM global_prod.bancos_migracion_periodo_legacy p JOIN global_prod.bancos_migracion_cuenta_legacy c ON c.cuenta_legacy=p.cuenta_legacy WHERE p.lote_migracion=$q AND (p.configuracion_id IS NULL OR c.cuenta_bancaria_zetti_id IS NULL OR c.omitir)") > 0) {
                throw new RuntimeException('Hay períodos incrementales sin configuración o cuenta ERP unívoca.');
            }
            $this->pdo->exec("WITH nuevos AS (
                SELECT m.* FROM public.bancos_mes_movimientos_cargados_periodo m
                WHERE NOT EXISTS (SELECT 1 FROM global_prod.bancos_migracion_movimiento_legacy t WHERE t.id_periodo_legacy=m.id_periodo AND t.serial_seq_legacy=m.serial_seq)
            ), fuente AS (
                SELECT n.*,old.serial_seq_canonico canonico_anterior,min(n.serial_seq) OVER(PARTITION BY n.id_movimiento) canonico_nuevo,
                       row_number() OVER(PARTITION BY n.id_movimiento ORDER BY n.id_periodo,n.serial_seq) orden
                  FROM nuevos n LEFT JOIN global_prod.bancos_migracion_012_movimiento_canonico old ON old.id_movimiento_legacy=n.id_movimiento
            ) INSERT INTO global_prod.bancos_migracion_movimiento_legacy(id_periodo_legacy,serial_seq_legacy,id_movimiento_legacy,serial_seq_canonico,es_canonico,credito_legacy,debito_legacy,credito_normalizado,debito_normalizado,lote_migracion)
              SELECT f.id_periodo,f.serial_seq,f.id_movimiento,COALESCE(f.canonico_anterior,f.canonico_nuevo),f.canonico_anterior IS NULL AND f.orden=1,
                     f.credito::numeric(20,5),f.debito::numeric(20,5),CASE WHEN f.debito<0 THEN abs(f.debito)::numeric(20,5) WHEN f.credito<0 THEN 0 ELSE f.credito::numeric(20,5) END,
                     CASE WHEN f.credito<0 THEN abs(f.credito)::numeric(20,5) WHEN f.debito<0 THEN 0 ELSE f.debito::numeric(20,5) END,$q FROM fuente f");
            $this->pdo->exec("INSERT INTO global_prod.bancos_importacion_extracto(configuracion_id,cuenta_bancaria_zetti_id,inicio_periodo,total_movimientos,estado_id,archivo_origen,version_origen,observacion)
              SELECT p.configuracion_id,c.cuenta_bancaria_zetti_id,make_date(p.ano,p.num_mes,1),NULL,CASE WHEN src.validado THEN 3 ELSE 1 END,'LEGACY:BANCOS_MENSUAL',p.id_periodo_legacy,'MIGRACION-CATCHUP|PERIODO|'||p.id_periodo_legacy
              FROM global_prod.bancos_migracion_periodo_legacy p JOIN global_prod.bancos_migracion_cuenta_legacy c ON c.cuenta_legacy=p.cuenta_legacy JOIN public.bancos_mes_periodos_cargados_cuenta src ON src.id_periodo=p.id_periodo_legacy
              WHERE p.lote_migracion=$q AND NOT p.omitir AND NOT EXISTS (SELECT 1 FROM global_prod.bancos_importacion_extracto z WHERE z.observacion='MIGRACION-CATCHUP|PERIODO|'||p.id_periodo_legacy)");
            $this->pdo->exec("UPDATE global_prod.bancos_migracion_periodo_legacy p SET importacion_id=i.id FROM global_prod.bancos_importacion_extracto i WHERE p.lote_migracion=$q AND i.observacion='MIGRACION-CATCHUP|PERIODO|'||p.id_periodo_legacy");
            $this->pdo->exec("INSERT INTO global_prod.bancos_movimiento_extracto(importacion_id,id_periodo,serial_seq,numero_fila_origen,fecha_operacion,referencia,descripcion,credito,debito)
              SELECT p.importacion_id,t.id_periodo_legacy,t.serial_seq_legacy,row_number() OVER(PARTITION BY t.id_periodo_legacy ORDER BY t.serial_seq_legacy)::integer,src.fecha,NULLIF(src.referencia,''),NULLIF(src.observacion,''),t.credito_normalizado,t.debito_normalizado
              FROM global_prod.bancos_migracion_movimiento_legacy t JOIN global_prod.bancos_migracion_periodo_legacy p ON p.id_periodo_legacy=t.id_periodo_legacy
              JOIN public.bancos_mes_movimientos_cargados_periodo src ON src.id_periodo=t.id_periodo_legacy AND src.serial_seq=t.serial_seq_legacy
              WHERE t.lote_migracion=$q AND t.es_canonico AND NOT p.omitir AND NOT EXISTS (SELECT 1 FROM global_prod.bancos_movimiento_extracto z WHERE z.id_periodo=t.id_periodo_legacy AND z.serial_seq=t.serial_seq_legacy)");
            $this->pdo->exec("UPDATE global_prod.bancos_migracion_movimiento_legacy t SET movimiento_id=d.id FROM global_prod.bancos_migracion_movimiento_legacy c
              JOIN global_prod.bancos_movimiento_extracto d ON d.id_periodo=c.id_periodo_legacy AND d.serial_seq=c.serial_seq_legacy
              WHERE c.es_canonico AND t.id_movimiento_legacy=c.id_movimiento_legacy AND t.movimiento_id IS NULL");
            $this->pdo->exec("INSERT INTO global_prod.bancos_historial_asignacion(movimiento_id,estado_id,usuario_hub_id,observacion)
              SELECT DISTINCT t.movimiento_id,e.id,a.id_usuario::bigint,'MIGRACION-CATCHUP|estado actual legacy' FROM public.bancos_mes_usuario_asignado_movimiento a
              JOIN global_prod.bancos_migracion_movimiento_legacy t ON t.id_movimiento_legacy=a.id_movimiento AND t.es_canonico
              JOIN global_prod.rrhh_login u ON u.id=a.id_usuario::bigint JOIN global_prod.bancos_estado e ON e.codigo=CASE btrim(a.estado) WHEN 'PARA CERRAR' THEN 'PARA_CERRAR' ELSE btrim(a.estado) END
              WHERE t.lote_migracion=$q AND a.id_usuario~'^[0-9]+$' AND NOT EXISTS (SELECT 1 FROM global_prod.bancos_historial_asignacion z WHERE z.movimiento_id=t.movimiento_id)");
        });
    }

    private function faseDependencias($lote)
    {
        $q = $this->pdo->quote($lote);
        $this->transaccion(function () use ($q) {
            $this->pdo->exec("INSERT INTO global_prod.bancos_migracion_012_movimiento_canonico(id_movimiento_legacy,movimiento_id,id_periodo_legacy,serial_seq_canonico)
              SELECT t.id_movimiento_legacy,t.movimiento_id,t.id_periodo_legacy,t.serial_seq_canonico FROM global_prod.bancos_migracion_movimiento_legacy t
              WHERE t.lote_migracion=$q AND t.es_canonico AND t.movimiento_id IS NOT NULL ON CONFLICT(id_movimiento_legacy) DO UPDATE SET movimiento_id=EXCLUDED.movimiento_id,id_periodo_legacy=EXCLUDED.id_periodo_legacy,serial_seq_canonico=EXCLUDED.serial_seq_canonico");
            $this->pdo->exec("INSERT INTO global_prod.bancos_migracion_recurso_omitido(tipo_recurso,recurso_zetti_id,id_movimiento_legacy,motivo,lote_migracion)
              SELECT DISTINCT 'ASIENTO',a.id_asiento,a.id_movimiento,CASE WHEN i.importe IS NULL THEN 'Asiento sin líneas ERP: no se puede validar la asociación bancaria.' ELSE 'Importe bancario distinto del importe del asiento ERP.' END,$q
              FROM public.bancos_mes_asiento_asignado_movimiento a
              JOIN (SELECT id_asiento,bool_or(COALESCE(mult_asiento,false)) compartido FROM public.bancos_mes_asiento_asignado_movimiento WHERE id_asiento<>0 GROUP BY id_asiento) s ON s.id_asiento=a.id_asiento AND NOT s.compartido
              LEFT JOIN (SELECT asiento id_asiento,max(abs(monto)) importe FROM public.movimiento GROUP BY asiento) i ON i.id_asiento=a.id_asiento
              JOIN public.bancos_mes_movimientos_cargados_periodo m ON m.id_movimiento=a.id_movimiento
              WHERE i.importe IS NULL OR abs(COALESCE(m.debito,0)+COALESCE(m.credito,0))<>i.importe ON CONFLICT DO NOTHING");
            $this->pdo->exec("UPDATE global_prod.bancos_asociacion_movimiento z SET activo=false
              FROM global_prod.bancos_migracion_012_movimiento_canonico t
              WHERE t.movimiento_id=z.movimiento_id AND z.activo AND z.asiento_zetti_id IS NOT NULL AND z.observacion LIKE 'MIGRACION-%'
                AND NOT EXISTS (SELECT 1 FROM public.bancos_mes_asiento_asignado_movimiento a WHERE a.id_movimiento=t.id_movimiento_legacy AND a.id_asiento=z.asiento_zetti_id)");
            $this->pdo->exec("UPDATE global_prod.bancos_reserva_recurso z SET activo=false
              FROM global_prod.bancos_migracion_012_movimiento_canonico t
              WHERE t.movimiento_id=z.movimiento_id AND z.activo AND z.asiento_zetti_id IS NOT NULL AND z.observacion LIKE 'MIGRACION-%'
                AND NOT EXISTS (SELECT 1 FROM public.bancos_mes_exclusiones e WHERE e.id_movimiento=t.id_movimiento_legacy AND e.id_asiento=z.asiento_zetti_id)");
            $this->pdo->exec("INSERT INTO global_prod.bancos_asociacion_movimiento(movimiento_id,valor_zetti_id,monto_asociado,observacion)
              SELECT t.movimiento_id,v.id_valor,NULLIF(abs(v.monto_asignado)::numeric(20,5),0),'MIGRACION-CATCHUP|VALOR' FROM public.bancos_mes_valor_asignado_movimiento v
              JOIN global_prod.bancos_migracion_012_movimiento_canonico t ON t.id_movimiento_legacy=v.id_movimiento JOIN public.valor x ON x.id=v.id_valor
              LEFT JOIN global_prod.bancos_migracion_recurso_omitido o ON o.tipo_recurso='VALOR' AND o.recurso_zetti_id=v.id_valor AND o.id_movimiento_legacy=v.id_movimiento
              WHERE o.id_movimiento_legacy IS NULL AND NOT EXISTS (SELECT 1 FROM global_prod.bancos_asociacion_movimiento z WHERE z.movimiento_id=t.movimiento_id AND z.valor_zetti_id=v.id_valor)");
            $this->pdo->exec("INSERT INTO global_prod.bancos_asociacion_movimiento(movimiento_id,asiento_zetti_id,monto_asociado,compartido,observacion)
              SELECT t.movimiento_id,a.id_asiento,NULLIF(abs(a.monto_asignado)::numeric(20,5),0),s.compartido,'MIGRACION-CATCHUP|ASIENTO' FROM public.bancos_mes_asiento_asignado_movimiento a
              JOIN global_prod.bancos_migracion_012_movimiento_canonico t ON t.id_movimiento_legacy=a.id_movimiento JOIN public.asiento x ON x.id=a.id_asiento
              JOIN (SELECT id_asiento,bool_or(COALESCE(mult_asiento,false)) compartido FROM public.bancos_mes_asiento_asignado_movimiento WHERE id_asiento<>0 GROUP BY id_asiento) s ON s.id_asiento=a.id_asiento
              LEFT JOIN global_prod.bancos_migracion_recurso_omitido o ON o.tipo_recurso='ASIENTO' AND o.recurso_zetti_id=a.id_asiento AND o.id_movimiento_legacy=a.id_movimiento
              WHERE a.id_asiento<>0 AND o.id_movimiento_legacy IS NULL AND NOT EXISTS (SELECT 1 FROM global_prod.bancos_asociacion_movimiento z WHERE z.movimiento_id=t.movimiento_id AND z.asiento_zetti_id=a.id_asiento)");
            $this->pdo->exec("INSERT INTO global_prod.bancos_reserva_recurso(movimiento_id,valor_zetti_id,fecha_creacion,observacion)
              SELECT t.movimiento_id,e.id_valor,COALESCE(e.timestamp AT TIME ZONE 'America/Argentina/Buenos_Aires',now()),'MIGRACION-CATCHUP|EXCLUSION' FROM public.bancos_mes_exclusiones e
              JOIN global_prod.bancos_migracion_012_movimiento_canonico t ON t.id_movimiento_legacy=e.id_movimiento JOIN public.valor x ON x.id=e.id_valor
              WHERE e.id_valor IS NOT NULL AND NOT EXISTS (SELECT 1 FROM global_prod.bancos_reserva_recurso z WHERE z.movimiento_id=t.movimiento_id AND z.valor_zetti_id=e.id_valor)");
            $this->pdo->exec("INSERT INTO global_prod.bancos_reserva_recurso(movimiento_id,asiento_zetti_id,compartido,activo,fecha_creacion,observacion)
              SELECT t.movimiento_id,e.id_asiento,COALESCE(s.compartido,false),COALESCE(s.compartido,false) OR (q.filas=1 AND COALESCE(abs(COALESCE(src.debito,0)+COALESCE(src.credito,0))=i.importe,false)),COALESCE(e.timestamp AT TIME ZONE 'America/Argentina/Buenos_Aires',now()),'MIGRACION-CATCHUP|EXCLUSION'
              FROM public.bancos_mes_exclusiones e JOIN global_prod.bancos_migracion_012_movimiento_canonico t ON t.id_movimiento_legacy=e.id_movimiento JOIN public.asiento a ON a.id=e.id_asiento
              JOIN public.bancos_mes_movimientos_cargados_periodo src ON src.id_periodo=t.id_periodo_legacy AND src.serial_seq=t.serial_seq_canonico
              LEFT JOIN (SELECT id_asiento,bool_or(COALESCE(mult_asiento,false)) compartido FROM public.bancos_mes_asiento_asignado_movimiento WHERE id_asiento<>0 GROUP BY id_asiento) s ON s.id_asiento=e.id_asiento
              LEFT JOIN (SELECT asiento id_asiento,max(abs(monto)) importe FROM public.movimiento GROUP BY asiento) i ON i.id_asiento=e.id_asiento
              LEFT JOIN (SELECT id_asiento,count(*) filas FROM public.bancos_mes_exclusiones WHERE id_asiento IS NOT NULL AND id_asiento<>0 GROUP BY id_asiento) q ON q.id_asiento=e.id_asiento
              WHERE e.id_asiento IS NOT NULL AND e.id_asiento<>0 AND NOT EXISTS (SELECT 1 FROM global_prod.bancos_reserva_recurso z WHERE z.movimiento_id=t.movimiento_id AND z.asiento_zetti_id=e.id_asiento)");
            $this->pdo->exec("INSERT INTO global_prod.bancos_borrador_asiento(movimiento_id,nodo_zetti_id,fecha_contable,estado_id,clave_idempotencia,modelo,observacion)
              SELECT t.movimiento_id,h.nodo_creacion::integer,h.fecha_creacion,1,'MIGRACION-CATCHUP|BORRADOR|'||h.id_interno_asiento,h.asiento_modelo::text,left(h.nombre,200)
              FROM public.bancos_mes_asientos_creados_movimiento h JOIN global_prod.bancos_migracion_012_movimiento_canonico t ON t.id_movimiento_legacy=h.id_movimiento JOIN public.nodo n ON n.id=h.nodo_creacion::integer
              WHERE NOT EXISTS (SELECT 1 FROM global_prod.bancos_migracion_borrador_legacy mb WHERE mb.id_interno_asiento=h.id_interno_asiento)");
            $this->pdo->exec("INSERT INTO global_prod.bancos_migracion_borrador_legacy(id_interno_asiento,borrador_asiento_id,lote_migracion)
              SELECT h.id_interno_asiento,b.id,$q FROM public.bancos_mes_asientos_creados_movimiento h JOIN global_prod.bancos_borrador_asiento b ON b.clave_idempotencia='MIGRACION-CATCHUP|BORRADOR|'||h.id_interno_asiento
              ON CONFLICT(id_interno_asiento) DO NOTHING");
            $this->pdo->exec("WITH lineas AS (SELECT l.*,COALESCE(NULLIF(btrim(l.codigo_cuenta),''),(SELECT min(l2.codigo_cuenta) FROM public.bancos_mes_movimientos_creados_asiento l2 WHERE l2.id_interno_asiento=l.id_interno_asiento AND NULLIF(btrim(l2.codigo_cuenta),'') IS NOT NULL AND lower(btrim(l2.nombre))=lower(btrim(l.nombre)) AND l2.debe=l.haber AND l2.haber=l.debe HAVING count(DISTINCT l2.codigo_cuenta)=1)) codigo FROM public.bancos_mes_movimientos_creados_asiento l)
              INSERT INTO global_prod.bancos_linea_borrador_asiento(borrador_asiento_id,cuenta_zetti_id,debe,haber,observacion)
              SELECT mb.borrador_asiento_id,c.id,CASE WHEN l.haber<0 THEN abs(l.haber)::numeric(20,5) ELSE greatest(l.debe,0)::numeric(20,5) END,CASE WHEN l.debe<0 THEN abs(l.debe)::numeric(20,5) ELSE greatest(l.haber,0)::numeric(20,5) END,left(l.nombre,200)
              FROM lineas l JOIN global_prod.bancos_migracion_borrador_legacy mb ON mb.id_interno_asiento=l.id_interno_asiento JOIN public.cuenta c ON c.codigo=l.codigo
              WHERE mb.lote_migracion=$q AND (l.debe<>0 OR l.haber<>0) AND NOT EXISTS (SELECT 1 FROM global_prod.bancos_linea_borrador_asiento z WHERE z.borrador_asiento_id=mb.borrador_asiento_id AND z.cuenta_zetti_id=c.id AND z.debe=CASE WHEN l.haber<0 THEN abs(l.haber) ELSE greatest(l.debe,0) END AND z.haber=CASE WHEN l.debe<0 THEN abs(l.debe) ELSE greatest(l.haber,0) END AND z.observacion IS NOT DISTINCT FROM left(l.nombre,200))");
            $this->pdo->exec("INSERT INTO global_prod.bancos_mensaje_movimiento(movimiento_id,emisor_hub_id,tipo_mensaje,cuerpo,emitido_en)
              SELECT t.movimiento_id,e.login_id,CASE WHEN e.login_id IS NULL OR m.mensaje_de_sistema<>0 THEN 'SISTEMA' ELSE 'USUARIO' END,m.mensaje,COALESCE(m.emision_time AT TIME ZONE 'America/Argentina/Buenos_Aires',now())
              FROM public.bancos_mensajeria m JOIN global_prod.bancos_migracion_012_movimiento_canonico t ON t.id_movimiento_legacy=m.id_movimiento JOIN global_prod.bancos_migracion_emisor_legacy e ON e.id_usuario_legacy=m.id_usuario_emisor
              WHERE NOT EXISTS (SELECT 1 FROM global_prod.bancos_mensaje_movimiento z WHERE z.movimiento_id=t.movimiento_id AND z.emisor_hub_id IS NOT DISTINCT FROM e.login_id AND z.cuerpo IS NOT DISTINCT FROM m.mensaje AND z.emitido_en=COALESCE(m.emision_time AT TIME ZONE 'America/Argentina/Buenos_Aires',now()))");
        });
    }

    private function transaccion(callable $accion)
    {
        if ($this->pdo->inTransaction()) {
            $savepoint = 'bancos_catchup_' . (++$this->savepointSecuencia);
            $this->pdo->exec('SAVEPOINT ' . $savepoint);
            try {
                $accion();
                $this->pdo->exec('RELEASE SAVEPOINT ' . $savepoint);
            } catch (Throwable $e) {
                $this->pdo->exec('ROLLBACK TO SAVEPOINT ' . $savepoint);
                $this->pdo->exec('RELEASE SAVEPOINT ' . $savepoint);
                throw $e;
            }
            return;
        }
        $this->pdo->beginTransaction();
        try {
            $accion();
            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    private function entero($sql)
    {
        return (int) $this->pdo->query($sql)->fetchColumn();
    }

    private function agregarBloqueos(array &$bloqueos, $codigo, $sql)
    {
        foreach ($this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $fila) {
            $bloqueos[] = ['codigo' => $codigo, 'referencia' => $fila['referencia'], 'detalle' => $fila['detalle']];
        }
    }

    private function tomarBloqueo()
    {
        $valor = $this->pdo->query('SELECT pg_try_advisory_lock(' . self::LOCK_KEY . ')')->fetchColumn();
        return $valor === true || $valor === 't' || $valor === '1' || $valor === 1;
    }

    private function buscarEjecucion($clave)
    {
        $stmt = $this->pdo->prepare('SELECT estado,resumen::text FROM global_prod.bancos_migracion_incremental_ejecucion WHERE clave=:clave');
        $stmt->execute([':clave' => $clave]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return $fila ?: null;
    }

    private function registrarInicio($clave, $lote, array $resumen)
    {
        $stmt = $this->pdo->prepare("INSERT INTO global_prod.bancos_migracion_incremental_ejecucion(clave,lote_migracion,estado,resumen)
            VALUES(:clave,:lote,'EN_CURSO',CAST(:resumen AS jsonb)) ON CONFLICT(clave) DO UPDATE SET estado='EN_CURSO',inicio_en=now(),fin_en=NULL,resumen=EXCLUDED.resumen,error=NULL");
        $stmt->execute([':clave' => $clave, ':lote' => $lote, ':resumen' => json_encode($resumen, JSON_UNESCAPED_UNICODE)]);
    }

    private function registrarResultado($clave, $lote, $estado, array $resumen, $error)
    {
        $stmt = $this->pdo->prepare("INSERT INTO global_prod.bancos_migracion_incremental_ejecucion(clave,lote_migracion,estado,fin_en,resumen,error)
            VALUES(:clave,:lote,:estado,now(),CAST(:resumen AS jsonb),:error) ON CONFLICT(clave) DO UPDATE SET estado=EXCLUDED.estado,fin_en=now(),resumen=EXCLUDED.resumen,error=EXCLUDED.error");
        $stmt->execute([':clave' => $clave, ':lote' => $lote, ':estado' => $estado, ':resumen' => json_encode($resumen, JSON_UNESCAPED_UNICODE), ':error' => $error]);
    }
}
