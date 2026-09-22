<?php

namespace AppBancos\Reporting;

use PDO;

final class ConciliacionPostMigracion
{
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function generar()
    {
        $controles = [];

        $controles[] = $this->comparar(
            'deriva-cuentas-legacy',
            'Configuración',
            "SELECT count(*) FROM (
                SELECT num_cuenta AS cuenta FROM public.bancos_mes_periodos_cargados_cuenta
                UNION SELECT cuenta FROM public.bancos_mes_guardado_datos
                UNION SELECT cuenta FROM public.bancos_guardado_datos
            ) origen WHERE cuenta IS NOT NULL AND btrim(cuenta) <> '' AND cuenta <> 'N/A'",
            'SELECT count(*) FROM global_prod.bancos_migracion_cuenta_legacy',
            'Mide cuentas agregadas al legado después de crear la traza.',
            'DERIVA_POSTERIOR'
        );
        $controles[] = $this->comparar(
            'cuentas-resueltas',
            'Configuración',
            "SELECT count(*) FROM global_prod.bancos_migracion_cuenta_legacy WHERE lote_migracion = 'ORIGINAL'",
            "SELECT count(*) FROM global_prod.bancos_migracion_cuenta_legacy
              WHERE lote_migracion = 'ORIGINAL' AND metodo <> 'PENDIENTE'
                AND (
                    (tratamiento = 'CUENTA_UNICA' AND NOT omitir AND cuenta_bancaria_zetti_id IS NOT NULL)
                    OR (tratamiento = 'OMITIR' AND omitir AND cuenta_bancaria_zetti_id IS NULL)
                    OR (tratamiento = 'ALCANCE_GLOBAL' AND NOT omitir AND cuenta_bancaria_zetti_id IS NULL
                        AND EXISTS (
                            SELECT 1 FROM global_prod.bancos_migracion_configuracion_legacy mc
                            WHERE mc.cuenta_legacy = bancos_migracion_cuenta_legacy.cuenta_legacy
                        )
                        AND NOT EXISTS (
                            SELECT 1 FROM global_prod.bancos_migracion_configuracion_legacy mc
                            JOIN global_prod.bancos_configuracion c ON c.id = mc.configuracion_id
                            WHERE mc.cuenta_legacy = bancos_migracion_cuenta_legacy.cuenta_legacy
                              AND c.alcance <> 'GLOBAL'
                        ))
                    OR (tratamiento = 'MULTICUENTA' AND NOT omitir AND cuenta_bancaria_zetti_id IS NULL
                        AND NOT EXISTS (
                            SELECT 1 FROM global_prod.bancos_migracion_configuracion_legacy mc
                            WHERE mc.cuenta_legacy = bancos_migracion_cuenta_legacy.cuenta_legacy
                              AND (SELECT count(*) FROM global_prod.bancos_configuracion_cuenta cc
                                   WHERE cc.configuracion_id = mc.configuracion_id) < 2
                        ))
                    OR (tratamiento = 'PERIODOS_OMITIDOS' AND NOT omitir AND cuenta_bancaria_zetti_id IS NULL
                        AND EXISTS (
                            SELECT 1 FROM global_prod.bancos_migracion_periodo_legacy p
                            WHERE p.cuenta_legacy = bancos_migracion_cuenta_legacy.cuenta_legacy
                        )
                        AND NOT EXISTS (
                            SELECT 1 FROM global_prod.bancos_migracion_periodo_legacy p
                            WHERE p.cuenta_legacy = bancos_migracion_cuenta_legacy.cuenta_legacy
                              AND (NOT p.omitir OR NULLIF(btrim(p.motivo_omision), '') IS NULL)
                        ))
                )",
            'Cada cuenta del snapshot debe tener un mapeo o tratamiento especial verificable antes del corte.',
            'PENDIENTE_CORTE'
        );
        $controles[] = $this->comparar(
            'configuraciones-mapeadas',
            'Configuración',
            "SELECT count(*) FROM global_prod.bancos_migracion_configuracion_legacy WHERE lote_migracion = 'ORIGINAL' AND configuracion_id IS NOT NULL",
            "SELECT count(*) FROM global_prod.bancos_configuracion WHERE observacion LIKE 'MIGRACION-010|%'",
            'Cada combinación de configuración legacy debe apuntar a su configuración canónica.'
        );
        $controles[] = $this->comparar(
            'reglas-clasificacion',
            'Configuración',
            "SELECT count(*) FROM (
                SELECT DISTINCT mc.configuracion_id, r.subtipo::smallint, r.sentido,
                       NULLIF(btrim(r.codigo), ''), r.validar
                FROM (
                    SELECT 'BANCOS'::varchar AS origen, id, cuenta,
                           reglas_idsubtipo_valor AS subtipo,
                           CASE reglas_debehaber WHEN 'CRÉDITO' THEN 'C' WHEN 'DÉBITO' THEN 'D' END AS sentido,
                           reglas_codigo_excel AS codigo, true AS validar
                    FROM public.bancos_guardado_listareglas
                    UNION ALL
                    SELECT 'MENSUAL', id, cuenta, reglas_idsubtipo_valor, 'A',
                           reglas_codigo_excel, COALESCE(validar, false)
                    FROM public.bancos_mes_guardado_listareglas
                ) r
                JOIN global_prod.bancos_migracion_configuracion_legacy mc
                  ON mc.origen = r.origen AND mc.id_legacy = r.id AND mc.cuenta_legacy = r.cuenta
                WHERE r.subtipo ~ '^[0-9]+$' AND r.sentido IS NOT NULL
            ) esperado",
            "SELECT count(*) FROM global_prod.bancos_regla_clasificacion WHERE observacion LIKE 'MIGRACION-010|%' OR observacion LIKE 'MIGRACION-CATCHUP|REGLA|%'",
            'Mide reglas agregadas o retiradas en el legado después del snapshot.',
            'DERIVA_POSTERIOR'
        );
        $controles[] = $this->comparar(
            'mapeos-contables',
            'Configuración',
            "SELECT count(*) FROM (
                SELECT DISTINCT mc.configuracion_id, v.id_stv::smallint, v.id_cuenta_contable::bigint
                FROM (
                    SELECT 'BANCOS'::varchar AS origen, cuenta, id_stv, id_cuenta_contable
                    FROM public.bancos_guardado_cuentasvalores
                    UNION ALL
                    SELECT 'MENSUAL', cuenta, id_stv, id_cuenta_contable
                    FROM public.bancos_mes_guardado_cuentasvalores
                ) v
                JOIN global_prod.bancos_migracion_configuracion_legacy mc
                  ON mc.origen = v.origen AND mc.cuenta_legacy = v.cuenta
                JOIN public.cuenta c ON c.id = v.id_cuenta_contable::bigint
                WHERE v.id_stv ~ '^[0-9]+$' AND v.id_cuenta_contable ~ '^[0-9]+$'
            ) esperado",
            "SELECT count(*) FROM global_prod.bancos_mapeo_cuenta_contable WHERE observacion LIKE 'MIGRACION-010|%' OR observacion LIKE 'MIGRACION-CATCHUP|MAPEO|%'",
            'Mide mapeos agregados o retirados en el legado después del snapshot.',
            'DERIVA_POSTERIOR'
        );
        $controles[] = $this->comparar(
            'periodos-trazados',
            'Períodos',
            'SELECT count(*) FROM public.bancos_mes_periodos_cargados_cuenta',
            'SELECT count(*) FROM global_prod.bancos_migracion_periodo_legacy',
            'Mide períodos creados en el legado después del snapshot.',
            'DERIVA_POSTERIOR'
        );
        $controles[] = $this->comparar(
            'periodos-importados',
            'Períodos',
            "SELECT count(*) FROM global_prod.bancos_migracion_periodo_legacy WHERE lote_migracion = 'ORIGINAL' AND NOT omitir",
            "SELECT count(*) FROM global_prod.bancos_importacion_extracto WHERE observacion LIKE 'MIGRACION-011|%'",
            'Todo período no omitido debe producir una importación canónica.'
        );
        $controles[] = $this->comparar(
            'periodos-incrementales-resueltos',
            'Períodos',
            "SELECT count(*) FROM global_prod.bancos_migracion_periodo_legacy WHERE lote_migracion <> 'ORIGINAL' AND NOT omitir",
            "SELECT count(*) FROM global_prod.bancos_migracion_periodo_legacy WHERE lote_migracion <> 'ORIGINAL' AND NOT omitir AND configuracion_id IS NOT NULL AND importacion_id IS NOT NULL",
            'Todo período de catch-up debe resolver configuración e importación.',
            'PENDIENTE_CORTE'
        );
        $controles[] = $this->comparar(
            'periodos-omitidos-documentados',
            'Períodos',
            "SELECT count(*) FROM global_prod.bancos_migracion_periodo_legacy WHERE lote_migracion = 'ORIGINAL' AND omitir",
            "SELECT count(*) FROM global_prod.bancos_migracion_periodo_legacy
              WHERE lote_migracion = 'ORIGINAL' AND omitir AND NULLIF(btrim(motivo_omision), '') IS NOT NULL",
            'Toda omisión de período debe conservar un motivo explícito.'
        );
        $controles[] = $this->comparar(
            'movimientos-trazados',
            'Movimientos',
            'SELECT count(*) FROM public.bancos_mes_movimientos_cargados_periodo',
            'SELECT count(*) FROM global_prod.bancos_migracion_movimiento_legacy',
            'Mide filas de extracto incorporadas al legado después del snapshot.',
            'DERIVA_POSTERIOR'
        );
        $controles[] = $this->comparar(
            'movimientos-canonicos',
            'Movimientos',
            "SELECT count(*) FROM global_prod.bancos_migracion_movimiento_legacy t
              JOIN global_prod.bancos_migracion_periodo_legacy p
                ON p.id_periodo_legacy = t.id_periodo_legacy
             WHERE t.lote_migracion = 'ORIGINAL' AND p.lote_migracion = 'ORIGINAL'
               AND t.es_canonico AND NOT p.omitir",
            "SELECT count(*) FROM global_prod.bancos_movimiento_extracto m
              JOIN global_prod.bancos_importacion_extracto i ON i.id = m.importacion_id
             WHERE i.observacion LIKE 'MIGRACION-011|%'",
            'Sólo las filas canónicas de períodos incluidos deben llegar al destino.'
        );
        $controles[] = $this->comparar(
            'movimientos-traza-resuelta',
            'Movimientos',
            "SELECT count(*) FROM global_prod.bancos_migracion_movimiento_legacy t
              JOIN global_prod.bancos_migracion_periodo_legacy p
                ON p.id_periodo_legacy = t.id_periodo_legacy
             WHERE t.lote_migracion = 'ORIGINAL' AND p.lote_migracion = 'ORIGINAL'
               AND t.es_canonico AND NOT p.omitir",
            "SELECT count(*) FROM global_prod.bancos_migracion_movimiento_legacy t
              JOIN global_prod.bancos_migracion_periodo_legacy p
                ON p.id_periodo_legacy = t.id_periodo_legacy
             WHERE t.lote_migracion = 'ORIGINAL' AND p.lote_migracion = 'ORIGINAL'
               AND t.es_canonico AND NOT p.omitir AND t.movimiento_id IS NOT NULL",
            'Toda fila canónica incluida debe resolver el ID del movimiento destino.'
        );
        $controles[] = $this->comparar(
            'movimientos-incrementales-resueltos',
            'Movimientos',
            "SELECT count(*) FROM global_prod.bancos_migracion_movimiento_legacy WHERE lote_migracion <> 'ORIGINAL' AND es_canonico",
            "SELECT count(*) FROM global_prod.bancos_migracion_movimiento_legacy WHERE lote_migracion <> 'ORIGINAL' AND es_canonico AND movimiento_id IS NOT NULL",
            'Toda fila canónica de catch-up debe resolver su movimiento destino.',
            'PENDIENTE_CORTE'
        );
        $controles[] = $this->comparar(
            'credito-normalizado',
            'Importes',
            "SELECT COALESCE(sum(t.credito_normalizado), 0)::numeric(30,5)
               FROM global_prod.bancos_migracion_movimiento_legacy t
               JOIN global_prod.bancos_migracion_periodo_legacy p
                 ON p.id_periodo_legacy = t.id_periodo_legacy
              WHERE t.lote_migracion = 'ORIGINAL' AND p.lote_migracion = 'ORIGINAL'
                AND t.es_canonico AND NOT p.omitir",
            "SELECT COALESCE(sum(m.credito), 0)::numeric(30,5)
               FROM global_prod.bancos_movimiento_extracto m
               JOIN global_prod.bancos_importacion_extracto i ON i.id = m.importacion_id
              WHERE i.observacion LIKE 'MIGRACION-011|%'",
            'El total de créditos normalizados debe conservarse a cinco decimales.'
        );
        $controles[] = $this->comparar(
            'debito-normalizado',
            'Importes',
            "SELECT COALESCE(sum(t.debito_normalizado), 0)::numeric(30,5)
               FROM global_prod.bancos_migracion_movimiento_legacy t
               JOIN global_prod.bancos_migracion_periodo_legacy p
                 ON p.id_periodo_legacy = t.id_periodo_legacy
              WHERE t.lote_migracion = 'ORIGINAL' AND p.lote_migracion = 'ORIGINAL'
                AND t.es_canonico AND NOT p.omitir",
            "SELECT COALESCE(sum(m.debito), 0)::numeric(30,5)
               FROM global_prod.bancos_movimiento_extracto m
               JOIN global_prod.bancos_importacion_extracto i ON i.id = m.importacion_id
              WHERE i.observacion LIKE 'MIGRACION-011|%'",
            'El total de débitos normalizados debe conservarse a cinco decimales.'
        );
        $controles[] = $this->comparar(
            'snapshot-asociaciones-valor',
            'Asociaciones',
            "SELECT filas_afectadas FROM global_prod.bancos_migracion_ejecucion
              WHERE migracion = '012' AND fase = '02_ASOCIACIONES_VALOR' AND estado = 'OK'",
            "SELECT count(*) FROM global_prod.bancos_asociacion_movimiento
              WHERE observacion = 'MIGRACION-012|VALOR'",
            'La cantidad confirmada por la fase debe coincidir con las asociaciones persistidas.'
        );
        $controles[] = $this->comparar(
            'deriva-asociaciones-valor',
            'Asociaciones',
            "SELECT count(*) FROM public.bancos_mes_valor_asignado_movimiento v
              JOIN global_prod.bancos_migracion_012_movimiento_canonico t
                ON t.id_movimiento_legacy = v.id_movimiento
              JOIN public.valor x ON x.id = v.id_valor
              LEFT JOIN global_prod.bancos_migracion_recurso_omitido o
                ON o.tipo_recurso = 'VALOR' AND o.recurso_zetti_id = v.id_valor
               AND o.id_movimiento_legacy = v.id_movimiento
             WHERE o.id_movimiento_legacy IS NULL",
            "SELECT count(*) FROM global_prod.bancos_asociacion_movimiento
              WHERE activo AND observacion IN ('MIGRACION-012|VALOR','MIGRACION-CATCHUP|VALOR')",
            'Mide asociaciones a valor agregadas al legado después del snapshot.',
            'DERIVA_POSTERIOR'
        );
        $controles[] = $this->comparar(
            'snapshot-asociaciones-asiento',
            'Asociaciones',
            "SELECT filas_afectadas FROM global_prod.bancos_migracion_ejecucion
              WHERE migracion = '012' AND fase = '03_ASOCIACIONES_ASIENTO' AND estado = 'OK'",
            "SELECT count(*) FROM global_prod.bancos_asociacion_movimiento
              WHERE observacion = 'MIGRACION-012|ASIENTO'",
            'La cantidad confirmada por la fase debe coincidir con las asociaciones persistidas.'
        );
        $controles[] = $this->comparar(
            'deriva-asociaciones-asiento',
            'Asociaciones',
            "SELECT count(*) FROM public.bancos_mes_asiento_asignado_movimiento a
              JOIN global_prod.bancos_migracion_012_movimiento_canonico t
                ON t.id_movimiento_legacy = a.id_movimiento
              JOIN public.asiento x ON x.id = a.id_asiento
              LEFT JOIN global_prod.bancos_migracion_recurso_omitido o
                ON o.tipo_recurso = 'ASIENTO' AND o.recurso_zetti_id = a.id_asiento
               AND o.id_movimiento_legacy = a.id_movimiento
             WHERE a.id_asiento <> 0 AND o.id_movimiento_legacy IS NULL",
            "SELECT count(*) FROM global_prod.bancos_asociacion_movimiento
              WHERE activo AND observacion IN ('MIGRACION-012|ASIENTO','MIGRACION-CATCHUP|ASIENTO')",
            'Mide asociaciones a asiento agregadas al legado después del snapshot.',
            'DERIVA_POSTERIOR'
        );
        $controles[] = $this->comparar(
            'snapshot-reservas-valor',
            'Reservas',
            "SELECT filas_afectadas FROM global_prod.bancos_migracion_ejecucion
              WHERE migracion = '012' AND fase = '04_RESERVAS_VALOR' AND estado = 'OK'",
            "SELECT count(*) FROM global_prod.bancos_reserva_recurso
              WHERE observacion = 'MIGRACION-012|EXCLUSION' AND valor_zetti_id IS NOT NULL",
            'La cantidad confirmada por la fase debe coincidir con las reservas persistidas.'
        );
        $controles[] = $this->comparar(
            'deriva-reservas-valor',
            'Reservas',
            "SELECT count(*) FROM public.bancos_mes_exclusiones e
              JOIN global_prod.bancos_migracion_012_movimiento_canonico t
                ON t.id_movimiento_legacy = e.id_movimiento
              JOIN public.valor x ON x.id = e.id_valor
             WHERE e.id_valor IS NOT NULL",
            "SELECT count(*) FROM global_prod.bancos_reserva_recurso z
              JOIN global_prod.bancos_migracion_012_movimiento_canonico t ON t.movimiento_id = z.movimiento_id
             WHERE z.observacion IN ('MIGRACION-012|EXCLUSION','MIGRACION-CATCHUP|EXCLUSION')
               AND z.valor_zetti_id IS NOT NULL AND EXISTS (
                   SELECT 1 FROM public.bancos_mes_exclusiones e
                    WHERE e.id_movimiento = t.id_movimiento_legacy AND e.id_valor = z.valor_zetti_id
               )",
            'Mide exclusiones de valor agregadas al legado después del snapshot.',
            'DERIVA_POSTERIOR'
        );
        $controles[] = $this->comparar(
            'snapshot-reservas-asiento',
            'Reservas',
            "SELECT filas_afectadas FROM global_prod.bancos_migracion_ejecucion
              WHERE migracion = '012' AND fase = '05_RESERVAS_ASIENTO' AND estado = 'OK'",
            "SELECT count(*) FROM global_prod.bancos_reserva_recurso
              WHERE observacion = 'MIGRACION-012|EXCLUSION' AND asiento_zetti_id IS NOT NULL",
            'La cantidad confirmada por la fase debe coincidir con las reservas persistidas.'
        );
        $controles[] = $this->comparar(
            'deriva-reservas-asiento',
            'Reservas',
            "SELECT count(*) FROM public.bancos_mes_exclusiones e
              JOIN global_prod.bancos_migracion_012_movimiento_canonico t
                ON t.id_movimiento_legacy = e.id_movimiento
              JOIN public.asiento a ON a.id = e.id_asiento
             WHERE e.id_asiento IS NOT NULL AND e.id_asiento <> 0",
            "SELECT count(*) FROM global_prod.bancos_reserva_recurso z
              JOIN global_prod.bancos_migracion_012_movimiento_canonico t ON t.movimiento_id = z.movimiento_id
             WHERE z.observacion IN ('MIGRACION-012|EXCLUSION','MIGRACION-CATCHUP|EXCLUSION')
               AND z.asiento_zetti_id IS NOT NULL AND EXISTS (
                   SELECT 1 FROM public.bancos_mes_exclusiones e
                    WHERE e.id_movimiento = t.id_movimiento_legacy AND e.id_asiento = z.asiento_zetti_id
               )",
            'Mide exclusiones de asiento agregadas al legado después del snapshot.',
            'DERIVA_POSTERIOR'
        );
        $controles[] = $this->comparar(
            'snapshot-borradores',
            'Borradores',
            "SELECT count(*) FROM global_prod.bancos_migracion_borrador_legacy WHERE lote_migracion = 'ORIGINAL'",
            "SELECT count(*) FROM global_prod.bancos_borrador_asiento
              WHERE clave_idempotencia LIKE 'MIGRACION-012|BORRADOR|%'",
            'Cada borrador registrado en la traza debe conservar su cabecera.'
        );
        $controles[] = $this->comparar(
            'deriva-borradores',
            'Borradores',
            "SELECT count(*) FROM public.bancos_mes_asientos_creados_movimiento h
              JOIN global_prod.bancos_migracion_012_movimiento_canonico t
                ON t.id_movimiento_legacy = h.id_movimiento
              JOIN public.nodo n ON n.id = h.nodo_creacion::integer",
            "SELECT count(*) FROM global_prod.bancos_borrador_asiento
              WHERE clave_idempotencia LIKE 'MIGRACION-012|BORRADOR|%' OR clave_idempotencia LIKE 'MIGRACION-CATCHUP|BORRADOR|%'",
            'Mide borradores agregados al legado después del snapshot.',
            'DERIVA_POSTERIOR'
        );
        $controles[] = $this->comparar(
            'snapshot-lineas-borrador',
            'Borradores',
            "SELECT filas_afectadas FROM global_prod.bancos_migracion_ejecucion
              WHERE migracion = '012' AND fase = '06_BORRADORES' AND estado = 'OK'",
            "SELECT count(*) FROM global_prod.bancos_linea_borrador_asiento l
              JOIN global_prod.bancos_borrador_asiento b ON b.id = l.borrador_asiento_id
             WHERE b.clave_idempotencia LIKE 'MIGRACION-012|BORRADOR|%'",
            'La cantidad de líneas confirmada por la fase debe coincidir con el destino.'
        );
        $controles[] = $this->comparar(
            'deriva-lineas-borrador',
            'Borradores',
            "WITH lineas AS (
                SELECT l.*,
                       COALESCE(NULLIF(btrim(l.codigo_cuenta), ''), (
                           SELECT min(l2.codigo_cuenta)
                           FROM public.bancos_mes_movimientos_creados_asiento l2
                           WHERE l2.id_interno_asiento = l.id_interno_asiento
                             AND NULLIF(btrim(l2.codigo_cuenta), '') IS NOT NULL
                             AND lower(btrim(l2.nombre)) = lower(btrim(l.nombre))
                             AND l2.debe = l.haber AND l2.haber = l.debe
                           HAVING count(DISTINCT l2.codigo_cuenta) = 1
                       )) AS codigo
                FROM public.bancos_mes_movimientos_creados_asiento l
            )
            SELECT count(*) FROM lineas l
              JOIN global_prod.bancos_migracion_borrador_legacy mb
                ON mb.id_interno_asiento = l.id_interno_asiento
              JOIN public.cuenta c ON c.codigo = l.codigo
             WHERE l.debe <> 0 OR l.haber <> 0",
            "SELECT count(*) FROM global_prod.bancos_linea_borrador_asiento l
              JOIN global_prod.bancos_borrador_asiento b ON b.id = l.borrador_asiento_id
             WHERE b.clave_idempotencia LIKE 'MIGRACION-012|BORRADOR|%' OR b.clave_idempotencia LIKE 'MIGRACION-CATCHUP|BORRADOR|%'",
            'Mide líneas de borrador agregadas al legado después del snapshot.',
            'DERIVA_POSTERIOR'
        );
        $controles[] = $this->comparar(
            'snapshot-mensajes',
            'Mensajes',
            "SELECT filas_afectadas FROM global_prod.bancos_migracion_ejecucion
              WHERE migracion = '012' AND fase = '07_MENSAJES' AND estado = 'OK'",
            'SELECT count(*) FROM global_prod.bancos_mensaje_movimiento',
            'La cantidad de mensajes confirmada por la fase debe coincidir con el destino.'
        );
        $controles[] = $this->comparar(
            'deriva-mensajes',
            'Mensajes',
            "SELECT count(*) FROM public.bancos_mensajeria m
              JOIN global_prod.bancos_migracion_012_movimiento_canonico t
                ON t.id_movimiento_legacy = m.id_movimiento
              JOIN global_prod.bancos_migracion_emisor_legacy e
                ON e.id_usuario_legacy = m.id_usuario_emisor",
            'SELECT count(*) FROM global_prod.bancos_mensaje_movimiento',
            'Mide mensajes agregados al legado después del snapshot.',
            'DERIVA_POSTERIOR'
        );
        $controles[] = $this->comparar(
            'historial-estado-responsable',
            'Historial',
            "SELECT count(*) FROM (
                SELECT DISTINCT t.movimiento_id, e.id, a.id_usuario::bigint
                FROM public.bancos_mes_usuario_asignado_movimiento a
                JOIN global_prod.bancos_migracion_movimiento_legacy t
                  ON t.id_movimiento_legacy = a.id_movimiento AND t.es_canonico
                JOIN global_prod.rrhh_login u ON u.id = a.id_usuario::bigint
                JOIN global_prod.bancos_estado e
                  ON e.codigo = CASE btrim(a.estado)
                      WHEN 'PARA CERRAR' THEN 'PARA_CERRAR' ELSE btrim(a.estado) END
                WHERE a.id_usuario ~ '^[0-9]+$'
            ) esperado",
            "SELECT count(*) FROM global_prod.bancos_historial_asignacion
              WHERE observacion = 'MIGRACION-011|estado actual legacy'",
            'Mide estados o responsables agregados al legado después del snapshot.',
            'DERIVA_POSTERIOR'
        );
        $controles[] = $this->comparar(
            'fases-012-completas',
            'Ejecución',
            "SELECT count(*) FROM (VALUES
                ('00_PREPARAR_CONJUNTOS'), ('01_PREFLIGHT'), ('02_ASOCIACIONES_VALOR'),
                ('03_ASOCIACIONES_ASIENTO'), ('04_RESERVAS_VALOR'),
                ('05_RESERVAS_ASIENTO'), ('06_BORRADORES'), ('07_MENSAJES')
            ) fases(nombre)",
            "SELECT count(*) FROM global_prod.bancos_migracion_ejecucion
              WHERE migracion = '012' AND estado = 'OK'",
            'Las ocho fases de la migración de dependencias deben finalizar en OK.'
        );
        $controles[] = $this->comparar(
            'excepciones-con-motivo',
            'Excepciones',
            "SELECT count(*) FROM global_prod.bancos_migracion_recurso_omitido WHERE lote_migracion = 'ORIGINAL'",
            "SELECT count(*) FROM global_prod.bancos_migracion_recurso_omitido
              WHERE lote_migracion = 'ORIGINAL' AND NULLIF(btrim(motivo), '') IS NOT NULL",
            'Cada recurso omitido debe conservar un motivo auditable.'
        );

        $diferenciasSnapshot = array_filter($controles, static function ($control) {
            return $control['tipo'] === 'PARIDAD_SNAPSHOT' && $control['estado'] !== 'APROBADO';
        });
        $deriva = array_filter($controles, static function ($control) {
            return $control['tipo'] === 'DERIVA_POSTERIOR' && $control['estado'] !== 'APROBADO';
        });
        $pendientesCorte = array_filter($controles, static function ($control) {
            return $control['tipo'] === 'PENDIENTE_CORTE' && $control['estado'] !== 'APROBADO';
        });

        return [
            'generado_en' => $this->pdo->query("SELECT to_char(clock_timestamp(), 'YYYY-MM-DD\"T\"HH24:MI:SSOF')")->fetchColumn(),
            'estado' => count($diferenciasSnapshot) > 0
                ? 'CON_DIFERENCIAS'
                : ((count($deriva) > 0 || count($pendientesCorte) > 0)
                    ? 'REQUIERE_ACTUALIZACION'
                    : 'APROBADO'),
            'controles' => $controles,
            'excepciones' => $this->consultarExcepciones(),
        ];
    }

    private function comparar($id, $dimension, $sqlEsperado, $sqlActual, $detalle, $tipo = 'PARIDAD_SNAPSHOT')
    {
        $consulta = $this->pdo->query(
            'SELECT (' . $sqlEsperado . ')::text AS esperado, (' . $sqlActual . ')::text AS actual'
        );
        $fila = $consulta->fetch(PDO::FETCH_ASSOC);

        return [
            'id' => $id,
            'dimension' => $dimension,
            'tipo' => $tipo,
            'esperado' => $fila['esperado'],
            'actual' => $fila['actual'],
            'diferencia' => $this->diferencia($fila['esperado'], $fila['actual']),
            'estado' => $fila['esperado'] === $fila['actual'] ? 'APROBADO' : 'DIFERENCIA',
            'detalle' => $detalle,
        ];
    }

    private function diferencia($esperado, $actual)
    {
        if (preg_match('/^-?\d+$/', $esperado) && preg_match('/^-?\d+$/', $actual)) {
            return (string) ((int) $actual - (int) $esperado);
        }
        if ($esperado === $actual) {
            return '0';
        }
        return 'n/a';
    }

    private function consultarExcepciones()
    {
        $resumen = [];
        $resumen[] = [
            'tipo' => 'FILAS_DUPLICADAS_NO_CANONICAS',
            'cantidad' => (string) $this->pdo->query(
                "SELECT count(*) FROM global_prod.bancos_migracion_movimiento_legacy WHERE lote_migracion = 'ORIGINAL' AND NOT es_canonico"
            )->fetchColumn(),
            'detalle' => 'Se conserva una sola fila canónica por id_movimiento; las restantes permanecen trazadas.',
        ];
        $resumen[] = [
            'tipo' => 'PERIODOS_OMITIDOS',
            'cantidad' => (string) $this->pdo->query(
                "SELECT count(*) FROM global_prod.bancos_migracion_periodo_legacy WHERE lote_migracion = 'ORIGINAL' AND omitir"
            )->fetchColumn(),
            'detalle' => 'Períodos excluidos con motivo explícito y sin importación canónica.',
        ];
        foreach ($this->pdo->query(
            "SELECT 'RECURSO_' || tipo_recurso AS tipo, count(*)::text AS cantidad
              FROM global_prod.bancos_migracion_recurso_omitido
              WHERE lote_migracion = 'ORIGINAL'
              GROUP BY tipo_recurso ORDER BY tipo_recurso"
        )->fetchAll(PDO::FETCH_ASSOC) as $fila) {
            $resumen[] = [
                'tipo' => $fila['tipo'],
                'cantidad' => $fila['cantidad'],
                'detalle' => 'Recursos legacy excluidos mediante regla explícita y motivo persistido.',
            ];
        }

        return $resumen;
    }
}
