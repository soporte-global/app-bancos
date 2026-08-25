CREATE TABLE global_prod.hub_categorias_aplicaciones (
    id bigserial PRIMARY KEY,
    nombre varchar(50),
    observacion varchar(50),
    fecha_creacion timestamptz,
    fecha_actualizacion timestamptz,
    ignorado timestamptz,
    origen varchar(50) NOT NULL DEFAULT 'global_prod',
    origen_id varchar(100) NOT NULL,
    CONSTRAINT hub_categorias_aplicaciones_origen_uk UNIQUE (origen, origen_id)
);

CREATE TABLE global_prod.hub_aplicaciones (
    id bigserial PRIMARY KEY,
    nombre varchar(50),
    categoria bigint,
    observacion varchar(50),
    imagen varchar(100),
    url varchar(100),
    fecha_creacion timestamptz,
    fecha_modificacion timestamptz,
    ignorado timestamptz,
    origen varchar(50) NOT NULL DEFAULT 'global_prod',
    origen_id varchar(100) NOT NULL,
    CONSTRAINT hub_aplicaciones_origen_uk UNIQUE (origen, origen_id),
    CONSTRAINT hub_aplicaciones_categoria_fk FOREIGN KEY (categoria)
        REFERENCES global_prod.hub_categorias_aplicaciones(id)
);

CREATE INDEX hub_aplicaciones_categoria_idx
    ON global_prod.hub_aplicaciones(categoria);

CREATE TABLE global_prod.hub_grupos (
    id bigserial PRIMARY KEY,
    nombre varchar(50),
    observacion varchar(50),
    fecha_creacion timestamptz,
    fecha_actualizacion timestamptz,
    ignorado timestamptz,
    zweb_predeterminado bigint,
    origen varchar(50) NOT NULL DEFAULT 'global_prod',
    origen_id varchar(100) NOT NULL,
    CONSTRAINT hub_grupos_origen_uk UNIQUE (origen, origen_id)
);

CREATE TABLE global_prod.hub_niveles (
    id serial PRIMARY KEY,
    nombre varchar(50),
    observacion varchar(50),
    fecha_creacion timestamptz,
    fecha_actualizacion timestamptz,
    ignorado timestamptz,
    origen varchar(50) NOT NULL DEFAULT 'global_prod',
    origen_id varchar(100) NOT NULL,
    CONSTRAINT hub_niveles_origen_uk UNIQUE (origen, origen_id)
);

CREATE TABLE global_prod.hub_tipo_permiso (
    id serial PRIMARY KEY,
    nombre varchar(50),
    observacion varchar(50),
    fecha_creacion timestamptz,
    fecha_actualizacion timestamptz,
    ignorado timestamptz,
    origen varchar(50) NOT NULL DEFAULT 'global_prod',
    origen_id varchar(100) NOT NULL,
    CONSTRAINT hub_tipo_permiso_origen_uk UNIQUE (origen, origen_id)
);

CREATE TABLE global_prod.hub_permisos (
    id serial PRIMARY KEY,
    descripcion varchar(50),
    fecha_creacion timestamptz,
    fecha_actualizacion timestamptz,
    ignorado timestamptz,
    aplicacion bigint NOT NULL,
    tipo_permiso integer NOT NULL,
    nivel integer NOT NULL,
    nombre_interno varchar(50),
    icon varchar(50),
    origen varchar(50) NOT NULL DEFAULT 'global_prod',
    origen_id varchar(100) NOT NULL,
    CONSTRAINT hub_permisos_origen_uk UNIQUE (origen, origen_id),
    CONSTRAINT hub_permisos_aplicacion_fk FOREIGN KEY (aplicacion)
        REFERENCES global_prod.hub_aplicaciones(id),
    CONSTRAINT hub_permisos_tipo_fk FOREIGN KEY (tipo_permiso)
        REFERENCES global_prod.hub_tipo_permiso(id),
    CONSTRAINT hub_permisos_nivel_fk FOREIGN KEY (nivel)
        REFERENCES global_prod.hub_niveles(id)
);

CREATE INDEX hub_permisos_aplicacion_idx
    ON global_prod.hub_permisos(aplicacion);
CREATE INDEX hub_permisos_tipo_idx
    ON global_prod.hub_permisos(tipo_permiso);
CREATE INDEX hub_permisos_nivel_idx
    ON global_prod.hub_permisos(nivel);

CREATE TABLE global_prod.hub_permisos_grupo (
    id bigserial PRIMARY KEY,
    grupo bigint NOT NULL,
    permiso integer NOT NULL,
    descripcion varchar(50),
    fecha_creacion timestamptz,
    fecha_actualizacion timestamptz,
    ignorado timestamptz,
    origen varchar(50) NOT NULL DEFAULT 'global_prod',
    origen_id varchar(100) NOT NULL,
    CONSTRAINT hub_permisos_grupo_origen_uk UNIQUE (origen, origen_id),
    CONSTRAINT hub_permisos_grupo_grupo_fk FOREIGN KEY (grupo)
        REFERENCES global_prod.hub_grupos(id),
    CONSTRAINT hub_permisos_grupo_permiso_fk FOREIGN KEY (permiso)
        REFERENCES global_prod.hub_permisos(id)
);

CREATE INDEX hub_permisos_grupo_grupo_idx
    ON global_prod.hub_permisos_grupo(grupo);
CREATE INDEX hub_permisos_grupo_permiso_idx
    ON global_prod.hub_permisos_grupo(permiso);

CREATE TABLE global_prod.hub_usuarios_grupo (
    id bigserial PRIMARY KEY,
    usuario bigint NOT NULL,
    grupo bigint NOT NULL,
    observacion varchar(50),
    fecha_creacion timestamptz,
    fecha_modificacion timestamptz,
    ignorado timestamptz,
    origen varchar(50) NOT NULL DEFAULT 'global_prod',
    origen_id varchar(100) NOT NULL,
    CONSTRAINT hub_usuarios_grupo_origen_uk UNIQUE (origen, origen_id),
    CONSTRAINT hub_usuarios_grupo_usuario_fk FOREIGN KEY (usuario)
        REFERENCES global_prod.rrhh_login(id),
    CONSTRAINT hub_usuarios_grupo_grupo_fk FOREIGN KEY (grupo)
        REFERENCES global_prod.hub_grupos(id)
);

CREATE INDEX hub_usuarios_grupo_usuario_idx
    ON global_prod.hub_usuarios_grupo(usuario);
CREATE INDEX hub_usuarios_grupo_grupo_idx
    ON global_prod.hub_usuarios_grupo(grupo);

