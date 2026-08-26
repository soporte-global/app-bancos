BEGIN;

CREATE TABLE IF NOT EXISTS global_prod.bancos_estado (
    id smallserial PRIMARY KEY,
    codigo varchar(30) NOT NULL,
    descripcion varchar(120),
    activo boolean NOT NULL DEFAULT true,
    CONSTRAINT bancos_estado_codigo_uk UNIQUE (codigo)
);

INSERT INTO global_prod.bancos_estado (id, codigo, descripcion)
VALUES
    (1, 'ABIERTO', 'Registro en construcción'),
    (2, 'PARA_CERRAR', 'Registro listo para cierre'),
    (3, 'CERRADO', 'Registro finalizado')
ON CONFLICT (codigo) DO NOTHING;

CREATE TABLE IF NOT EXISTS global_prod.bancos_configuracion (
    id bigserial PRIMARY KEY,
    alcance varchar(12) NOT NULL CHECK (alcance IN ('GLOBAL', 'CUENTA')),
    banco_zetti_id bigint,
    nodo_zetti_id integer,
    moneda smallint,
    activo boolean NOT NULL DEFAULT true,
    observacion varchar(200),
    fecha_creacion timestamptz NOT NULL DEFAULT now(),
    usuario_creacion bigint,
    fecha_modificacion timestamptz NOT NULL DEFAULT now(),
    usuario_modificacion bigint,
    CONSTRAINT bancos_configuracion_usuario_creacion_fk FOREIGN KEY (usuario_creacion)
        REFERENCES global_prod.rrhh_login(id),
    CONSTRAINT bancos_configuracion_usuario_modificacion_fk FOREIGN KEY (usuario_modificacion)
        REFERENCES global_prod.rrhh_login(id)
);

CREATE INDEX IF NOT EXISTS bancos_configuracion_activo_idx
    ON global_prod.bancos_configuracion(activo);

CREATE TABLE IF NOT EXISTS global_prod.bancos_configuracion_cuenta (
    id bigserial PRIMARY KEY,
    configuracion_id bigint NOT NULL,
    cuenta_bancaria_zetti_id bigint NOT NULL,
    fecha_creacion timestamptz NOT NULL DEFAULT now(),
    usuario_creacion bigint,
    fecha_modificacion timestamptz NOT NULL DEFAULT now(),
    usuario_modificacion bigint,
    CONSTRAINT bancos_configuracion_cuenta_configuracion_fk FOREIGN KEY (configuracion_id)
        REFERENCES global_prod.bancos_configuracion(id),
    CONSTRAINT bancos_configuracion_cuenta_cuenta_bancaria_fk FOREIGN KEY (cuenta_bancaria_zetti_id)
        REFERENCES public.cuenta_bancaria(id),
    CONSTRAINT bancos_configuracion_cuenta_usuario_creacion_fk FOREIGN KEY (usuario_creacion)
        REFERENCES global_prod.rrhh_login(id),
    CONSTRAINT bancos_configuracion_cuenta_usuario_modificacion_fk FOREIGN KEY (usuario_modificacion)
        REFERENCES global_prod.rrhh_login(id),
    CONSTRAINT bancos_configuracion_cuenta_uk UNIQUE (configuracion_id, cuenta_bancaria_zetti_id)
);

CREATE TABLE IF NOT EXISTS global_prod.bancos_regla_clasificacion (
    id bigserial PRIMARY KEY,
    configuracion_id bigint NOT NULL,
    subtipo_valor_zetti_id smallint NOT NULL,
    sentido char(1) NOT NULL CHECK (sentido IN ('C', 'D')),
    codigo_extracto varchar(60),
    validar_automaticamente boolean NOT NULL DEFAULT true,
    observacion varchar(200),
    fecha_creacion timestamptz NOT NULL DEFAULT now(),
    usuario_creacion bigint,
    fecha_modificacion timestamptz NOT NULL DEFAULT now(),
    usuario_modificacion bigint,
    CONSTRAINT bancos_regla_clasificacion_configuracion_fk FOREIGN KEY (configuracion_id)
        REFERENCES global_prod.bancos_configuracion(id),
    CONSTRAINT bancos_regla_clasificacion_usuario_creacion_fk FOREIGN KEY (usuario_creacion)
        REFERENCES global_prod.rrhh_login(id),
    CONSTRAINT bancos_regla_clasificacion_usuario_modificacion_fk FOREIGN KEY (usuario_modificacion)
        REFERENCES global_prod.rrhh_login(id)
);

CREATE UNIQUE INDEX IF NOT EXISTS bancos_regla_clasificacion_uk
    ON global_prod.bancos_regla_clasificacion(configuracion_id, subtipo_valor_zetti_id, sentido, COALESCE(codigo_extracto, ''));

CREATE TABLE IF NOT EXISTS global_prod.bancos_mapeo_cuenta_contable (
    id bigserial PRIMARY KEY,
    configuracion_id bigint NOT NULL,
    subtipo_valor_zetti_id smallint NOT NULL,
    cuenta_zetti_id bigint NOT NULL,
    regla_orden smallint,
    observacion varchar(200),
    fecha_creacion timestamptz NOT NULL DEFAULT now(),
    usuario_creacion bigint,
    fecha_modificacion timestamptz NOT NULL DEFAULT now(),
    usuario_modificacion bigint,
    CONSTRAINT bancos_mapeo_cuenta_contable_configuracion_fk FOREIGN KEY (configuracion_id)
        REFERENCES global_prod.bancos_configuracion(id),
    CONSTRAINT bancos_mapeo_cuenta_contable_cuenta_fk FOREIGN KEY (cuenta_zetti_id)
        REFERENCES public.cuenta(id),
    CONSTRAINT bancos_mapeo_cuenta_contable_usuario_creacion_fk FOREIGN KEY (usuario_creacion)
        REFERENCES global_prod.rrhh_login(id),
    CONSTRAINT bancos_mapeo_cuenta_contable_usuario_modificacion_fk FOREIGN KEY (usuario_modificacion)
        REFERENCES global_prod.rrhh_login(id),
    CONSTRAINT bancos_mapeo_cuenta_contable_uk UNIQUE (configuracion_id, subtipo_valor_zetti_id, cuenta_zetti_id)
);

CREATE TABLE IF NOT EXISTS global_prod.bancos_regla_asignacion_usuario (
    id bigserial PRIMARY KEY,
    configuracion_id bigint NOT NULL,
    subtipo_valor_zetti_id smallint NOT NULL,
    usuario bigint NOT NULL,
    activo boolean NOT NULL DEFAULT true,
    observacion varchar(200),
    fecha_creacion timestamptz NOT NULL DEFAULT now(),
    usuario_creacion bigint,
    fecha_modificacion timestamptz NOT NULL DEFAULT now(),
    usuario_modificacion bigint,
    CONSTRAINT bancos_regla_asignacion_usuario_configuracion_fk FOREIGN KEY (configuracion_id)
        REFERENCES global_prod.bancos_configuracion(id),
    CONSTRAINT bancos_regla_asignacion_usuario_usuario_fk FOREIGN KEY (usuario)
        REFERENCES global_prod.rrhh_login(id),
    CONSTRAINT bancos_regla_asignacion_usuario_usuario_creacion_fk FOREIGN KEY (usuario_creacion)
        REFERENCES global_prod.rrhh_login(id),
    CONSTRAINT bancos_regla_asignacion_usuario_usuario_modificacion_fk FOREIGN KEY (usuario_modificacion)
        REFERENCES global_prod.rrhh_login(id),
    CONSTRAINT bancos_regla_asignacion_usuario_uk UNIQUE (configuracion_id, subtipo_valor_zetti_id, usuario)
);

CREATE TABLE IF NOT EXISTS global_prod.bancos_importacion_extracto (
    id bigserial PRIMARY KEY,
    configuracion_id bigint NOT NULL,
    cuenta_bancaria_zetti_id bigint NOT NULL,
    inicio_periodo date NOT NULL,
    total_movimientos integer,
    estado_id smallint NOT NULL DEFAULT 1 REFERENCES global_prod.bancos_estado(id),
    archivo_origen varchar(255),
    hash_origen char(64),
    version_origen varchar(50),
    observacion varchar(255),
    fecha_creacion timestamptz NOT NULL DEFAULT now(),
    usuario_creacion bigint,
    fecha_modificacion timestamptz NOT NULL DEFAULT now(),
    usuario_modificacion bigint,
    CONSTRAINT bancos_importacion_extracto_configuracion_fk FOREIGN KEY (configuracion_id)
        REFERENCES global_prod.bancos_configuracion(id),
    CONSTRAINT bancos_importacion_extracto_cuenta_bancaria_fk FOREIGN KEY (cuenta_bancaria_zetti_id)
        REFERENCES public.cuenta_bancaria(id),
    CONSTRAINT bancos_importacion_importante_usuario_creacion_fk FOREIGN KEY (usuario_creacion)
        REFERENCES global_prod.rrhh_login(id),
    CONSTRAINT bancos_importacion_importante_usuario_modificacion_fk FOREIGN KEY (usuario_modificacion)
        REFERENCES global_prod.rrhh_login(id)
);

CREATE INDEX IF NOT EXISTS bancos_importacion_extracto_periodo_idx
    ON global_prod.bancos_importacion_extracto(cuenta_bancaria_zetti_id, inicio_periodo);

CREATE UNIQUE INDEX IF NOT EXISTS bancos_importacion_extracto_uk
    ON global_prod.bancos_importacion_extracto(cuenta_bancaria_zetti_id, inicio_periodo, COALESCE(hash_origen, ''), COALESCE(version_origen, ''));

CREATE TABLE IF NOT EXISTS global_prod.bancos_movimiento_extracto (
    id bigserial PRIMARY KEY,
    importacion_id bigint NOT NULL,
    id_periodo integer NOT NULL,
    serial_seq bigint NOT NULL,
    numero_fila_origen integer NOT NULL,
    fecha_operacion date,
    referencia varchar(200),
    descripcion varchar(500),
    codigo_extracto varchar(60),
    credito numeric(20,5) DEFAULT 0 NOT NULL,
    debito numeric(20,5) DEFAULT 0 NOT NULL,
    moneda smallint,
    subtipo_valor_zetti_id smallint,
    fecha_creacion timestamptz NOT NULL DEFAULT now(),
    usuario_creacion bigint,
    fecha_modificacion timestamptz NOT NULL DEFAULT now(),
    usuario_modificacion bigint,
    CONSTRAINT bancos_movimiento_extracto_importacion_fk FOREIGN KEY (importacion_id)
        REFERENCES global_prod.bancos_importacion_extracto(id),
    CONSTRAINT bancos_movimiento_extracto_usuario_creacion_fk FOREIGN KEY (usuario_creacion)
        REFERENCES global_prod.rrhh_login(id),
    CONSTRAINT bancos_movimiento_extracto_usuario_modificacion_fk FOREIGN KEY (usuario_modificacion)
        REFERENCES global_prod.rrhh_login(id),
    CONSTRAINT bancos_movimiento_extracto_importacion_num_fila_uk UNIQUE (importacion_id, numero_fila_origen),
    CONSTRAINT bancos_movimiento_extracto_clave_heredada_uk UNIQUE (id_periodo, serial_seq),
    CONSTRAINT bancos_movimiento_extracto_importe_chk CHECK ((credito = 0 AND debito >= 0) OR (debito = 0 AND credito >= 0)),
    CONSTRAINT bancos_movimiento_extracto_importe_no_cero_chk CHECK (credito > 0 OR debito > 0)
);

CREATE INDEX IF NOT EXISTS bancos_movimiento_extracto_importacion_idx
    ON global_prod.bancos_movimiento_extracto(importacion_id);

CREATE TABLE IF NOT EXISTS global_prod.bancos_historial_asignacion (
    id bigserial PRIMARY KEY,
    movimiento_id bigint NOT NULL,
    estado_id smallint NOT NULL REFERENCES global_prod.bancos_estado(id),
    usuario_hub_id bigint NOT NULL,
    motivo varchar(200),
    observacion text,
    registrado_en timestamptz NOT NULL DEFAULT now(),
    CONSTRAINT bancos_historial_asignacion_movimiento_fk FOREIGN KEY (movimiento_id)
        REFERENCES global_prod.bancos_movimiento_extracto(id),
    CONSTRAINT bancos_historial_asignacion_usuario_fk FOREIGN KEY (usuario_hub_id)
        REFERENCES global_prod.rrhh_login(id)
);

CREATE INDEX IF NOT EXISTS bancos_historial_asignacion_movimiento_idx
    ON global_prod.bancos_historial_asignacion(movimiento_id);

CREATE INDEX IF NOT EXISTS bancos_historial_asignacion_estado_idx
    ON global_prod.bancos_historial_asignacion(estado_id);

CREATE TABLE IF NOT EXISTS global_prod.bancos_borrador_asiento (
    id bigserial PRIMARY KEY,
    movimiento_id bigint NOT NULL,
    nodo_zetti_id integer NOT NULL,
    fecha_contable date NOT NULL,
    estado_id smallint NOT NULL REFERENCES global_prod.bancos_estado(id),
    operacion_zetti_id bigint,
    asiento_zetti_id bigint,
    clave_idempotencia varchar(120),
    modelo varchar(80),
    activo boolean NOT NULL DEFAULT true,
    observacion varchar(200),
    fecha_creacion timestamptz NOT NULL DEFAULT now(),
    usuario_creacion bigint,
    fecha_modificacion timestamptz NOT NULL DEFAULT now(),
    usuario_modificacion bigint,
    CONSTRAINT bancos_borrador_asiento_movimiento_fk FOREIGN KEY (movimiento_id)
        REFERENCES global_prod.bancos_movimiento_extracto(id),
    CONSTRAINT bancos_borrador_asiento_nodo_fk FOREIGN KEY (nodo_zetti_id)
        REFERENCES public.nodo(id),
    CONSTRAINT bancos_borrador_asiento_operacion_zetti_fk FOREIGN KEY (operacion_zetti_id)
        REFERENCES public.operacion(id),
    CONSTRAINT bancos_borrador_asiento_asiento_zetti_fk FOREIGN KEY (asiento_zetti_id)
        REFERENCES public.asiento(id),
    CONSTRAINT bancos_borrador_asiento_usuario_creacion_fk FOREIGN KEY (usuario_creacion)
        REFERENCES global_prod.rrhh_login(id),
    CONSTRAINT bancos_borrador_asiento_usuario_modificacion_fk FOREIGN KEY (usuario_modificacion)
        REFERENCES global_prod.rrhh_login(id),
    CONSTRAINT bancos_borrador_asiento_idempotencia_uk UNIQUE (clave_idempotencia),
    CONSTRAINT bancos_borrador_asiento_usuario_creacion_chk CHECK (nodo_zetti_id > 0)
);

CREATE TABLE IF NOT EXISTS global_prod.bancos_linea_borrador_asiento (
    id bigserial PRIMARY KEY,
    borrador_asiento_id bigint NOT NULL,
    cuenta_zetti_id bigint NOT NULL,
    debe numeric(20,5) NOT NULL DEFAULT 0,
    haber numeric(20,5) NOT NULL DEFAULT 0,
    observacion varchar(200),
    CONSTRAINT bancos_linea_borrador_asiento_borrador_fk FOREIGN KEY (borrador_asiento_id)
        REFERENCES global_prod.bancos_borrador_asiento(id),
    CONSTRAINT bancos_linea_borrador_asiento_cuenta_fk FOREIGN KEY (cuenta_zetti_id)
        REFERENCES public.cuenta(id),
    CONSTRAINT bancos_linea_borrador_asiento_monto_chk CHECK (
        (debe >= 0 AND haber >= 0) AND (debe <> 0 OR haber <> 0) AND NOT (debe > 0 AND haber > 0)
    )
);

CREATE TABLE IF NOT EXISTS global_prod.bancos_asociacion_movimiento (
    id bigserial PRIMARY KEY,
    movimiento_id bigint NOT NULL,
    valor_zetti_id bigint,
    asiento_zetti_id bigint,
    borrador_asiento_id bigint,
    monto_asociado numeric(20,5),
    activo boolean NOT NULL DEFAULT true,
    observacion text,
    fecha_creacion timestamptz NOT NULL DEFAULT now(),
    usuario_creacion bigint,
    CONSTRAINT bancos_asociacion_movimiento_movimiento_fk FOREIGN KEY (movimiento_id)
        REFERENCES global_prod.bancos_movimiento_extracto(id),
    CONSTRAINT bancos_asociacion_movimiento_valor_fk FOREIGN KEY (valor_zetti_id)
        REFERENCES public.valor(id),
    CONSTRAINT bancos_asociacion_movimiento_asiento_fk FOREIGN KEY (asiento_zetti_id)
        REFERENCES public.asiento(id),
    CONSTRAINT bancos_asociacion_movimiento_borrador_fk FOREIGN KEY (borrador_asiento_id)
        REFERENCES global_prod.bancos_borrador_asiento(id),
    CONSTRAINT bancos_asociacion_movimiento_usuario_fk FOREIGN KEY (usuario_creacion)
        REFERENCES global_prod.rrhh_login(id),
    CONSTRAINT bancos_asociacion_movimiento_unico_destino_chk CHECK (
        (CASE WHEN valor_zetti_id IS NOT NULL THEN 1 ELSE 0 END)
      + (CASE WHEN asiento_zetti_id IS NOT NULL THEN 1 ELSE 0 END)
      + (CASE WHEN borrador_asiento_id IS NOT NULL THEN 1 ELSE 0 END) = 1
    ),
    CONSTRAINT bancos_asociacion_movimiento_monto_chk CHECK (
        monto_asociado IS NULL OR monto_asociado > 0
    )
);

CREATE UNIQUE INDEX IF NOT EXISTS bancos_asociacion_movimiento_valor_activo_uk
    ON global_prod.bancos_asociacion_movimiento(valor_zetti_id)
    WHERE activo AND valor_zetti_id IS NOT NULL;

CREATE UNIQUE INDEX IF NOT EXISTS bancos_asociacion_movimiento_asiento_activo_uk
    ON global_prod.bancos_asociacion_movimiento(asiento_zetti_id)
    WHERE activo AND asiento_zetti_id IS NOT NULL;

CREATE TABLE IF NOT EXISTS global_prod.bancos_reserva_recurso (
    id bigserial PRIMARY KEY,
    movimiento_id bigint NOT NULL,
    valor_zetti_id bigint,
    asiento_zetti_id bigint,
    borrador_asiento_id bigint,
    activo boolean NOT NULL DEFAULT true,
    reservado_por bigint,
    reservado_hasta timestamptz,
    motivo varchar(200),
    observacion text,
    fecha_creacion timestamptz NOT NULL DEFAULT now(),
    CONSTRAINT bancos_reserva_recurso_movimiento_fk FOREIGN KEY (movimiento_id)
        REFERENCES global_prod.bancos_movimiento_extracto(id),
    CONSTRAINT bancos_reserva_recurso_valor_fk FOREIGN KEY (valor_zetti_id)
        REFERENCES public.valor(id),
    CONSTRAINT bancos_reserva_recurso_asiento_fk FOREIGN KEY (asiento_zetti_id)
        REFERENCES public.asiento(id),
    CONSTRAINT bancos_reserva_recurso_borrador_fk FOREIGN KEY (borrador_asiento_id)
        REFERENCES global_prod.bancos_borrador_asiento(id),
    CONSTRAINT bancos_reserva_recurso_usuario_fk FOREIGN KEY (reservado_por)
        REFERENCES global_prod.rrhh_login(id),
    CONSTRAINT bancos_reserva_recurso_unico_destino_chk CHECK (
        (CASE WHEN valor_zetti_id IS NOT NULL THEN 1 ELSE 0 END)
      + (CASE WHEN asiento_zetti_id IS NOT NULL THEN 1 ELSE 0 END)
      + (CASE WHEN borrador_asiento_id IS NOT NULL THEN 1 ELSE 0 END) = 1
    )
);

CREATE UNIQUE INDEX IF NOT EXISTS bancos_reserva_recurso_valor_activo_uk
    ON global_prod.bancos_reserva_recurso(valor_zetti_id)
    WHERE activo AND valor_zetti_id IS NOT NULL;

CREATE UNIQUE INDEX IF NOT EXISTS bancos_reserva_recurso_asiento_activo_uk
    ON global_prod.bancos_reserva_recurso(asiento_zetti_id)
    WHERE activo AND asiento_zetti_id IS NOT NULL;

CREATE TABLE IF NOT EXISTS global_prod.bancos_mensaje_movimiento (
    id bigserial PRIMARY KEY,
    movimiento_id bigint NOT NULL,
    emisor_hub_id bigint NOT NULL,
    tipo_mensaje varchar(60) NOT NULL,
    cuerpo text NOT NULL,
    emitido_en timestamptz NOT NULL DEFAULT now(),
    CONSTRAINT bancos_mensaje_movimiento_movimiento_fk FOREIGN KEY (movimiento_id)
        REFERENCES global_prod.bancos_movimiento_extracto(id),
    CONSTRAINT bancos_mensaje_movimiento_emisor_fk FOREIGN KEY (emisor_hub_id)
        REFERENCES global_prod.rrhh_login(id)
);

CREATE TABLE IF NOT EXISTS global_prod.bancos_recepcion_mensaje (
    id bigserial PRIMARY KEY,
    mensaje_id bigint NOT NULL,
    receptor_hub_id bigint NOT NULL,
    leido_en timestamptz,
    CONSTRAINT bancos_recepcion_mensaje_mensaje_fk FOREIGN KEY (mensaje_id)
        REFERENCES global_prod.bancos_mensaje_movimiento(id),
    CONSTRAINT bancos_recepcion_mensaje_receptor_fk FOREIGN KEY (receptor_hub_id)
        REFERENCES global_prod.rrhh_login(id),
    CONSTRAINT bancos_recepcion_mensaje_unq UNIQUE (mensaje_id, receptor_hub_id)
);

CREATE INDEX IF NOT EXISTS bancos_recepcion_mensaje_leido_idx
    ON global_prod.bancos_recepcion_mensaje(mensaje_id, leido_en);

CREATE TABLE IF NOT EXISTS global_prod.bancos_conciliacion_cheque (
    id bigserial PRIMARY KEY,
    movimiento_id bigint,
    estado_id smallint NOT NULL REFERENCES global_prod.bancos_estado(id),
    operador_hub_id bigint NOT NULL,
    valor_origen_zetti_id bigint NOT NULL,
    operacion_zetti_id bigint NOT NULL,
    valor_resultante_zetti_id bigint NOT NULL,
    asiento_zetti_id bigint,
    clave_idempotencia varchar(120) NOT NULL,
    motivo text,
    forzada boolean NOT NULL DEFAULT false,
    evidencia_origen varchar(255),
    conciliado_en timestamptz NOT NULL DEFAULT now(),
    CONSTRAINT bancos_conciliacion_cheque_movimiento_fk FOREIGN KEY (movimiento_id)
        REFERENCES global_prod.bancos_movimiento_extracto(id),
    CONSTRAINT bancos_conciliacion_cheque_estado_fk FOREIGN KEY (estado_id)
        REFERENCES global_prod.bancos_estado(id),
    CONSTRAINT bancos_conciliacion_cheque_operador_fk FOREIGN KEY (operador_hub_id)
        REFERENCES global_prod.rrhh_login(id),
    CONSTRAINT bancos_conciliacion_cheque_valor_origen_fk FOREIGN KEY (valor_origen_zetti_id)
        REFERENCES public.valor(id),
    CONSTRAINT bancos_conciliacion_cheque_operacion_fk FOREIGN KEY (operacion_zetti_id)
        REFERENCES public.operacion(id),
    CONSTRAINT bancos_conciliacion_cheque_valor_resultante_fk FOREIGN KEY (valor_resultante_zetti_id)
        REFERENCES public.valor(id),
    CONSTRAINT bancos_conciliacion_cheque_asiento_fk FOREIGN KEY (asiento_zetti_id)
        REFERENCES public.asiento(id),
    CONSTRAINT bancos_conciliacion_cheque_idmov_idperiodo_chk CHECK (movimiento_id IS NOT NULL OR clave_idempotencia IS NOT NULL),
    CONSTRAINT bancos_conciliacion_cheque_uk UNIQUE (clave_idempotencia)
);

CREATE INDEX IF NOT EXISTS bancos_conciliacion_cheque_estado_idx
    ON global_prod.bancos_conciliacion_cheque(estado_id);

COMMIT;




