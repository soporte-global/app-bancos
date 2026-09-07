# Estado de migración

Actualizado: 2026-08-31.

Este archivo lleva la cuenta del trabajo sobre `nueva_app`. La primera aplicación
real construida con este core sigue siendo el objetivo; la replicación hacia las
demás aplicaciones comenzará después de validarla.

## Terminado

- contrato `app` versus `_shared` para CSS, HTML, JavaScript, PHP y SQL;
- configuración base versionada y reemplazos locales no versionados;
- migración del login y permisos Hub hacia `ftweb.global_prod`;
- permisos efectivos por grupo Hub, usuario directo y grupo Zweb;
- conexiones PDO, token OAuth de Zetti y cliente de APIS-global v2 separados;
- identidad centralizada y permisos separados bajo `src/Model/Core`, sin dividir el contrato por su origen Hub o RRHH;
- autoload bajo demanda, caché breve de autenticación y medición del request;
- página compartida de diagnóstico, deshabilitable por configuración;
- sincronización diaria RRHH mediante el proceso 34 del semáforo;
- mapeo editable entre empresas RRHH y nodos en `global_temp`;
- soporte de bajas lógicas y físicas del origen RRHH;
- decisión de conservar `lib`, `src` y `vendor` en la raíz;
- protección HTTP transferible mediante `.htaccess` y prueba automatizada de superficie web;
- carga completa del login y circuito POST/rechazo/redirección verificados sin errores JavaScript;
- separación de `rrhh_consulta` y `nueva_app` en repositorios independientes;
- enlace estructural configurable a home cuando existe, deduplicado frente a un permiso con destino `home`.
- home y diagnóstico confirmados manualmente antes de reemplazar la sesión legacy;
- auditoría completa de consumidores y contratos de `/_shared` y `src/Model`;
- modelo nuevo condensado en `Cuenta`, empleado singular, `Sesion`,
  `PermisoAcceso` y `PermisoApp`;
- hash y contraseña encerrados en la autenticación inicial, sin persistencia de credenciales;
- eliminación del experimento sin consumidores que persistía datos personales del dispositivo;
- restauración de sesión por cuenta activa con recarga periódica de identidad y permisos;
- autenticación compartida entre apps nuevas, con autorización evaluada por cada aplicación;
- vigencia absoluta del login por doce horas, separada del refresco de permisos de cinco minutos;
- Zweb resuelto sólo desde la relación explícita del empleado;
- migración preparada para que los permisos por grupo Zweb usen esa misma relación explícita;
- acceso forzado aislado fuera del Core en `src/Model/Legacy`;
- pruebas de caracterización para los contratos HClasses que siguen vigentes;
- autocomplete cerrado por registro de consultas de cada aplicación y transacción de solo lectura;
- correcciones mecánicas de HTML, JavaScript y CSS detectadas por `codigo-claro`;
- login válido y home confirmados manualmente sobre la nueva `Sesion`;
- contrato con y sin identidad Zweb documentado y cubierto por la prueba de entidades;
- `username` de autenticacion separado del `alias` de presentacion en `Cuenta`;
- `criterioCliente` retirado del Core nuevo por ser un artefacto de migracion;
- cliente Zweb incorporado a la sesion con datos de entidad y cuenta corriente;
- cuenta corriente modelada con regla general de `GRUPO GLOBAL`, excepciones por nodo y modos `nunca`, `siempre` y `preguntar`;
- proyeccion segura de la sesion en `vars.sesion`, con raiz `user`, `empleado` y `nivel_acceso`;
- permisos de la app actual publicados por defecto y alcance completo configurable para un Hub;
- cliente e identidad Zweb opcionales anidados bajo `empleado` en la proyeccion web;
- bootstrap del frontend separado del header mediante un parcial JSON y un cargador compartido;
- Core y Legacy reunidos bajo `src/Model`, con namespaces nuevos organizados en `Identidad` y `Acceso` y contratos legacy preservados.
- permisos efectivos reconstruidos por `PermisoAccesoRepository` y política
  configurable con `libre`, `requiere_empleado`, `requiere_cliente` y
  `requiere_zweb`;
- contexto canónico del frontend dividido en `sesion`, `app` y `data`;
- capa por aplicación en `app/compatibilidad`, con adaptadores PHP y JavaScript identidad por defecto;
- carga de negocio concentrada en `app/php/carga_inicial.php`, después de
  autorizar el destino y antes de armar el contexto;
- compatibilidad PHP incluida en el scope del header y la página para reconstruir variables legacy;
- código compartido desacoplado de la forma final de `vars` mediante `window.contextoApp`;
- caso nuevo y caso legacy sin namespace `app` cubiertos por prueba JavaScript.
- renombre de campos `*_erp_*` a `*_zetti_*` en `global_prod` mediante
  `app/sql/migraciones/005_bancos_campos_zetti.sql`, con rollback en
  `app/sql/migraciones/005_bancos_campos_zetti_rollback.sql`;
- renombre de columnas de marca temporal y usuario en `global_prod.bancos_*` mediante
  `app/sql/migraciones/006_bancos_campos_timestamps_zetti.sql`, con rollback en
  `app/sql/migraciones/006_bancos_campos_timestamps_zetti_rollback.sql`;
- carga completa de datos BANCOS/BANCOS_MENSUAL a las tablas canónicas
  `global_prod.bancos_*`, mediante las migraciones `007` a `013`: trazabilidad,
  configuración, períodos, movimientos, historial, asociaciones, reservas,
  borradores, mensajes y asientos compartidos.
- selector de esquema BANCOS y migración `014` ejecutada el 2026-09-04 para
  depurar contra `global_temp`, con 49 tablas `bancos_*` y cinco dependencias
  ERP verificadas; la auditoría del 2026-09-07 confirmó que sus secuencias no
  apuntan a producción. Falta el usuario restringido de depuración para
  completar el aislamiento operativo.
- fixtures `015` aplicados en `global_temp` y repositorio de bandeja mensual
  paginada validado con dos páginas consecutivas; queda exponer la lectura y
  compararla en sombra con el legado.

## Pendiente inmediato

- observar logout y vencimiento durante una jornada real;
- persistir de forma explícita el tipo de cuenta (`empleado`, `operativa` o `tecnica`) sin inferirlo por ausencia de empleado;
- adaptar gradualmente las clases HClasses con consumidores reales para que deleguen en el Core;
- retirar del template las clases sin consumidores confirmados después de revisar cada aplicación;
- desplegar la migración de permisos Zweb por empleado después de su preflight de datos;
- emitir la conciliación formal de la carga `007` a `013`, verificar un respaldo recuperable y acordar el corte controlado de consumidores; los rollbacks se reservan para una reversión explícita;
- adoptar inicialmente sólo lecturas sobre el destino, con comparación en sombra frente al legado; mantener deshabilitadas las escrituras ERP y los automatismos hasta validar contratos, permisos, idempotencia y concurrencia;
- construir la primera pantalla funcional de consulta RRHH con las APIs de liquidaciones;
- crear la nueva versión de hQuery y revisar allí el contrato de sus funciones, sin mezclarlo con esta migración.

## Posterior

- validar la primera aplicación completa sobre el core nuevo;
- preparar el procedimiento de replicación hacia las aplicaciones existentes;
- traducir excepciones legacy de cada aplicación a reglas `global_prod.hub_*`;
- evaluar cabeceras CSP y otras políticas del navegador una vez eliminado el código inline incompatible.
