BEGIN;

-- permite conceder un permiso sin obligar a crear un grupo para un solo usuario
CREATE TABLE IF NOT EXISTS global_prod.hub_permisos_usuario (
    id bigserial PRIMARY KEY,
    usuario bigint NOT NULL,
    permiso integer NOT NULL,
    descripcion varchar(50),
    fecha_creacion timestamptz,
    fecha_actualizacion timestamptz,
    ignorado timestamptz,
    origen varchar(50) NOT NULL DEFAULT 'global_prod',
    origen_id varchar(100) NOT NULL,
    CONSTRAINT hub_permisos_usuario_origen_uk UNIQUE (origen, origen_id),
    CONSTRAINT hub_permisos_usuario_usuario_fk FOREIGN KEY (usuario)
        REFERENCES global_prod.rrhh_login(id),
    CONSTRAINT hub_permisos_usuario_permiso_fk FOREIGN KEY (permiso)
        REFERENCES global_prod.hub_permisos(id)
);

CREATE INDEX IF NOT EXISTS hub_permisos_usuario_usuario_idx
    ON global_prod.hub_permisos_usuario(usuario);
CREATE INDEX IF NOT EXISTS hub_permisos_usuario_permiso_idx
    ON global_prod.hub_permisos_usuario(permiso);

-- vincula permisos del hub con grupos que ya se administran en zweb
CREATE TABLE IF NOT EXISTS global_prod.hub_permisos_grupo_zweb (
    id bigserial PRIMARY KEY,
    grupo_zweb bigint NOT NULL,
    permiso integer NOT NULL,
    descripcion varchar(50),
    fecha_creacion timestamptz,
    fecha_actualizacion timestamptz,
    ignorado timestamptz,
    origen varchar(50) NOT NULL DEFAULT 'global_prod',
    origen_id varchar(100) NOT NULL,
    CONSTRAINT hub_permisos_grupo_zweb_origen_uk UNIQUE (origen, origen_id),
    CONSTRAINT hub_permisos_grupo_zweb_grupo_fk FOREIGN KEY (grupo_zweb)
        REFERENCES public.seg_grupo(grupo),
    CONSTRAINT hub_permisos_grupo_zweb_permiso_fk FOREIGN KEY (permiso)
        REFERENCES global_prod.hub_permisos(id)
);

CREATE INDEX IF NOT EXISTS hub_permisos_grupo_zweb_grupo_idx
    ON global_prod.hub_permisos_grupo_zweb(grupo_zweb);
CREATE INDEX IF NOT EXISTS hub_permisos_grupo_zweb_permiso_idx
    ON global_prod.hub_permisos_grupo_zweb(permiso);

-- esta vista conserva por que camino llego cada permiso para poder diagnosticarlo
CREATE OR REPLACE VIEW global_prod.hub_permisos_rutas_usuario AS
SELECT
    rl.id AS usuario,
    hpg.permiso,
    'grupo_hub'::varchar(20) AS ruta,
    hpg.id AS asignacion,
    hug.id AS membresia,
    hug.grupo
FROM global_prod.rrhh_login rl
INNER JOIN global_prod.hub_usuarios_grupo hug
    ON hug.usuario = rl.id
   AND hug.ignorado IS NULL
INNER JOIN global_prod.hub_grupos hg
    ON hg.id = hug.grupo
   AND hg.ignorado IS NULL
INNER JOIN global_prod.hub_permisos_grupo hpg
    ON hpg.grupo = hug.grupo
   AND hpg.ignorado IS NULL
INNER JOIN global_prod.hub_permisos hp
    ON hp.id = hpg.permiso
   AND hp.ignorado IS NULL
INNER JOIN global_prod.hub_aplicaciones ha
    ON ha.id = hp.aplicacion
   AND ha.ignorado IS NULL
INNER JOIN global_prod.hub_tipo_permiso htp
    ON htp.id = hp.tipo_permiso
   AND htp.ignorado IS NULL
INNER JOIN global_prod.hub_niveles hn
    ON hn.id = hp.nivel
   AND hn.ignorado IS NULL
WHERE rl.habilitado IS TRUE
  AND rl.fecha_eliminacion IS NULL

UNION ALL

SELECT
    rl.id AS usuario,
    hpu.permiso,
    'usuario'::varchar(20) AS ruta,
    hpu.id AS asignacion,
    NULL::bigint AS membresia,
    NULL::bigint AS grupo
FROM global_prod.rrhh_login rl
INNER JOIN global_prod.hub_permisos_usuario hpu
    ON hpu.usuario = rl.id
   AND hpu.ignorado IS NULL
INNER JOIN global_prod.hub_permisos hp
    ON hp.id = hpu.permiso
   AND hp.ignorado IS NULL
INNER JOIN global_prod.hub_aplicaciones ha
    ON ha.id = hp.aplicacion
   AND ha.ignorado IS NULL
INNER JOIN global_prod.hub_tipo_permiso htp
    ON htp.id = hp.tipo_permiso
   AND htp.ignorado IS NULL
INNER JOIN global_prod.hub_niveles hn
    ON hn.id = hp.nivel
   AND hn.ignorado IS NULL
WHERE rl.habilitado IS TRUE
  AND rl.fecha_eliminacion IS NULL

UNION ALL

SELECT
    rl.id AS usuario,
    hpgz.permiso,
    'grupo_zweb'::varchar(20) AS ruta,
    hpgz.id AS asignacion,
    sgu.grupo_usuario AS membresia,
    hpgz.grupo_zweb AS grupo
FROM global_prod.rrhh_login rl
INNER JOIN LATERAL (
    SELECT min(su.usuario) AS usuario_zweb
    FROM public.seg_usuario su
    WHERE lower(su.alias) = lower(rl.usuario)
      AND su.estado = 1
    HAVING count(*) = 1
) uz ON true
INNER JOIN public.seg_grupo_usuario sgu
    ON sgu.usuario = uz.usuario_zweb
INNER JOIN global_prod.hub_permisos_grupo_zweb hpgz
    ON hpgz.grupo_zweb = sgu.grupo
   AND hpgz.ignorado IS NULL
INNER JOIN global_prod.hub_permisos hp
    ON hp.id = hpgz.permiso
   AND hp.ignorado IS NULL
INNER JOIN global_prod.hub_aplicaciones ha
    ON ha.id = hp.aplicacion
   AND ha.ignorado IS NULL
INNER JOIN global_prod.hub_tipo_permiso htp
    ON htp.id = hp.tipo_permiso
   AND htp.ignorado IS NULL
INNER JOIN global_prod.hub_niveles hn
    ON hn.id = hp.nivel
   AND hn.ignorado IS NULL
WHERE rl.habilitado IS TRUE
  AND rl.fecha_eliminacion IS NULL;

-- este es el contrato minimo que consumen las aplicaciones
CREATE OR REPLACE VIEW global_prod.hub_permisos_efectivos_usuario AS
SELECT DISTINCT
    rutas.usuario,
    rutas.permiso
FROM global_prod.hub_permisos_rutas_usuario rutas;

COMMIT;
