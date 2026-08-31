-- Ejecutar en otra conexión mientras corre 012. No modifica datos.
SELECT fase, estado, inicio_en, fin_en,
       coalesce(fin_en, now()) - inicio_en AS duracion,
       filas_afectadas, detalle
FROM global_prod.bancos_migracion_ejecucion
WHERE migracion = '012'
ORDER BY fase;

SELECT pid, state, wait_event_type, wait_event,
       now() - query_start AS duracion, pg_blocking_pids(pid) AS bloqueadores
FROM pg_stat_activity
WHERE application_name = 'codex_migracion_bancos';
