BEGIN;

DROP TABLE IF EXISTS global_temp.bancos_evento_auditoria;
DROP TABLE IF EXISTS global_temp.bancos_solicitud_idempotente;
DROP TABLE IF EXISTS global_prod.bancos_evento_auditoria;
DROP TABLE IF EXISTS global_prod.bancos_solicitud_idempotente;

COMMIT;
