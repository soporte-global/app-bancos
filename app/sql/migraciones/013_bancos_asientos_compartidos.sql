BEGIN;

ALTER TABLE global_prod.bancos_asociacion_movimiento
    ADD COLUMN IF NOT EXISTS compartido boolean NOT NULL DEFAULT false;

ALTER TABLE global_prod.bancos_reserva_recurso
    ADD COLUMN IF NOT EXISTS compartido boolean NOT NULL DEFAULT false;

DROP INDEX IF EXISTS global_prod.bancos_asociacion_movimiento_asiento_activo_uk;
CREATE UNIQUE INDEX bancos_asociacion_movimiento_asiento_activo_uk
    ON global_prod.bancos_asociacion_movimiento(asiento_zetti_id)
    WHERE activo AND NOT compartido AND asiento_zetti_id IS NOT NULL;

DROP INDEX IF EXISTS global_prod.bancos_reserva_recurso_asiento_activo_uk;
CREATE UNIQUE INDEX bancos_reserva_recurso_asiento_activo_uk
    ON global_prod.bancos_reserva_recurso(asiento_zetti_id)
    WHERE activo AND NOT compartido AND asiento_zetti_id IS NOT NULL;

COMMENT ON COLUMN global_prod.bancos_asociacion_movimiento.compartido IS
    'True permite que un asiento ERP resulte de la fusión de varios movimientos de extracto.';
COMMENT ON COLUMN global_prod.bancos_reserva_recurso.compartido IS
    'True permite una reserva compartida de asiento para una fusión histórica.';

COMMIT;
