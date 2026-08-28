BEGIN;

DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM global_prod.bancos_asociacion_movimiento WHERE compartido)
       OR EXISTS (SELECT 1 FROM global_prod.bancos_reserva_recurso WHERE compartido) THEN
        RAISE EXCEPTION 'No se puede revertir 013 mientras existan asientos compartidos.';
    END IF;
END;
$$;

DROP INDEX IF EXISTS global_prod.bancos_asociacion_movimiento_asiento_activo_uk;
CREATE UNIQUE INDEX bancos_asociacion_movimiento_asiento_activo_uk
    ON global_prod.bancos_asociacion_movimiento(asiento_zetti_id)
    WHERE activo AND asiento_zetti_id IS NOT NULL;

DROP INDEX IF EXISTS global_prod.bancos_reserva_recurso_asiento_activo_uk;
CREATE UNIQUE INDEX bancos_reserva_recurso_asiento_activo_uk
    ON global_prod.bancos_reserva_recurso(asiento_zetti_id)
    WHERE activo AND asiento_zetti_id IS NOT NULL;

ALTER TABLE global_prod.bancos_asociacion_movimiento DROP COLUMN compartido;
ALTER TABLE global_prod.bancos_reserva_recurso DROP COLUMN compartido;

COMMIT;
