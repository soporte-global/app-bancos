-- Rollback 012 por fases. Una cancelación sólo revierte el bloque activo.
CREATE TABLE IF NOT EXISTS global_prod.bancos_migracion_ejecucion (
 migracion varchar(40) NOT NULL, fase varchar(80) NOT NULL,
 estado varchar(20) NOT NULL CHECK (estado IN ('EN_CURSO','OK','ERROR')),
 inicio_en timestamptz NOT NULL DEFAULT now(), fin_en timestamptz,
 filas_afectadas bigint, detalle text, PRIMARY KEY (migracion,fase)
);

-- El uso de CTE RETURNING registra la cantidad exacta de cada borrado.
INSERT INTO global_prod.bancos_migracion_ejecucion(migracion,fase,estado,inicio_en,detalle) VALUES ('012_RB','01_MENSAJES','EN_CURSO',now(),'Eliminando mensajes de la migración.') ON CONFLICT(migracion,fase) DO UPDATE SET estado='EN_CURSO',inicio_en=now(),fin_en=NULL,filas_afectadas=NULL,detalle=EXCLUDED.detalle;
BEGIN;
WITH del AS (DELETE FROM global_prod.bancos_mensaje_movimiento d WHERE d.tipo_mensaje IN ('SISTEMA','USUARIO') AND EXISTS (SELECT 1 FROM public.bancos_mensajeria m JOIN global_prod.bancos_migracion_012_movimiento_canonico t ON t.id_movimiento_legacy=m.id_movimiento JOIN global_prod.bancos_migracion_emisor_legacy e ON e.id_usuario_legacy=m.id_usuario_emisor WHERE d.movimiento_id=t.movimiento_id AND d.emisor_hub_id IS NOT DISTINCT FROM e.login_id AND d.cuerpo IS NOT DISTINCT FROM m.mensaje AND d.emitido_en=COALESCE(m.emision_time AT TIME ZONE 'America/Argentina/Buenos_Aires',now())) RETURNING 1) UPDATE global_prod.bancos_migracion_ejecucion SET estado='OK',fin_en=now(),filas_afectadas=(SELECT count(*) FROM del),detalle='Mensajes eliminados.' WHERE migracion='012_RB' AND fase='01_MENSAJES'; COMMIT;

INSERT INTO global_prod.bancos_migracion_ejecucion(migracion,fase,estado,inicio_en,detalle) VALUES ('012_RB','02_BORRADORES','EN_CURSO',now(),'Eliminando líneas, mapas y cabeceras de borradores.') ON CONFLICT(migracion,fase) DO UPDATE SET estado='EN_CURSO',inicio_en=now(),fin_en=NULL,filas_afectadas=NULL,detalle=EXCLUDED.detalle;
BEGIN;
WITH del AS (DELETE FROM global_prod.bancos_linea_borrador_asiento l USING global_prod.bancos_migracion_borrador_legacy mb WHERE l.borrador_asiento_id=mb.borrador_asiento_id RETURNING 1) UPDATE global_prod.bancos_migracion_ejecucion SET filas_afectadas=(SELECT count(*) FROM del) WHERE migracion='012_RB' AND fase='02_BORRADORES';
DELETE FROM global_prod.bancos_migracion_borrador_legacy;
DELETE FROM global_prod.bancos_borrador_asiento WHERE clave_idempotencia LIKE 'MIGRACION-012|BORRADOR|%';
UPDATE global_prod.bancos_migracion_ejecucion SET estado='OK',fin_en=now(),detalle='Borradores eliminados.' WHERE migracion='012_RB' AND fase='02_BORRADORES'; COMMIT;

INSERT INTO global_prod.bancos_migracion_ejecucion(migracion,fase,estado,inicio_en,detalle) VALUES ('012_RB','03_RESERVAS','EN_CURSO',now(),'Eliminando reservas.') ON CONFLICT(migracion,fase) DO UPDATE SET estado='EN_CURSO',inicio_en=now(),fin_en=NULL,filas_afectadas=NULL,detalle=EXCLUDED.detalle;
BEGIN;
WITH del AS (DELETE FROM global_prod.bancos_reserva_recurso WHERE observacion='MIGRACION-012|EXCLUSION' RETURNING 1) UPDATE global_prod.bancos_migracion_ejecucion SET estado='OK',fin_en=now(),filas_afectadas=(SELECT count(*) FROM del),detalle='Reservas eliminadas.' WHERE migracion='012_RB' AND fase='03_RESERVAS'; COMMIT;

INSERT INTO global_prod.bancos_migracion_ejecucion(migracion,fase,estado,inicio_en,detalle) VALUES ('012_RB','04_ASOCIACIONES','EN_CURSO',now(),'Eliminando asociaciones.') ON CONFLICT(migracion,fase) DO UPDATE SET estado='EN_CURSO',inicio_en=now(),fin_en=NULL,filas_afectadas=NULL,detalle=EXCLUDED.detalle;
BEGIN;
WITH del AS (DELETE FROM global_prod.bancos_asociacion_movimiento WHERE observacion IN ('MIGRACION-012|VALOR','MIGRACION-012|ASIENTO') RETURNING 1) UPDATE global_prod.bancos_migracion_ejecucion SET estado='OK',fin_en=now(),filas_afectadas=(SELECT count(*) FROM del),detalle='Asociaciones eliminadas.' WHERE migracion='012_RB' AND fase='04_ASOCIACIONES'; COMMIT;

INSERT INTO global_prod.bancos_migracion_ejecucion(migracion,fase,estado,inicio_en,detalle) VALUES ('012_RB','05_AUXILIARES','EN_CURSO',now(),'Eliminando tablas auxiliares y cerrando 012.') ON CONFLICT(migracion,fase) DO UPDATE SET estado='EN_CURSO',inicio_en=now(),fin_en=NULL,filas_afectadas=NULL,detalle=EXCLUDED.detalle;
BEGIN;
DROP TABLE IF EXISTS global_prod.bancos_migracion_012_asiento_exclusion;
DROP TABLE IF EXISTS global_prod.bancos_migracion_012_asiento_importe;
DROP TABLE IF EXISTS global_prod.bancos_migracion_012_asiento_semantica;
DROP TABLE IF EXISTS global_prod.bancos_migracion_012_movimiento_canonico;
DROP TABLE IF EXISTS global_prod.bancos_migracion_recurso_omitido;
DROP TABLE IF EXISTS global_prod.bancos_migracion_emisor_legacy;
DELETE FROM global_prod.bancos_migracion_ejecucion WHERE migracion='012';
ALTER TABLE global_prod.bancos_mensaje_movimiento ALTER COLUMN emisor_hub_id SET NOT NULL;
UPDATE global_prod.bancos_migracion_ejecucion SET estado='OK',fin_en=now(),filas_afectadas=0,detalle='Rollback 012 finalizado.' WHERE migracion='012_RB' AND fase='05_AUXILIARES'; COMMIT;
