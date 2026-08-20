# Relevamiento legado: `BANCOS_MENSUAL`

Fecha: 2026-08-20. Ubicación: `gestion_usuarios/aplicaciones/bancos_mensual`. Alcance: revisión estática; no se ejecutaron operaciones contra la base de datos.

## Propósito y arquitectura

Aplicación PHP procedimental para gestionar extractos bancarios mensuales. Tiene dos aplicaciones web: `bancos_mensual_admin`, para carga, configuración, asignación y cierre; y `bancos_mensual_user`, para el tratamiento de movimientos asignados. Ambas usan HTML/PHP, JavaScript con jQuery y endpoints AJAX con acceso directo a PostgreSQL `ftweb`; no se identificaron servicios de dominio, API versionada, jobs ni colas.

## Flujo identificado

1. SGUA abre la aplicación con `idu` por POST. La app user verifica el permiso para `bancos_mensual_admin`; la app admin sólo verifica que exista el usuario.
2. El administrador importa extractos por cuenta y período mensual. El nodo y banco se derivan de la cuenta conocida, no del texto importado.
3. Se aplican reglas de clasificación, validación automática y RAAU (reglas de asignación automática de usuarios).
4. Cada movimiento puede asociarse a un valor ERP, a un asiento existente o a un asiento nuevo; se registran exclusiones para intentar evitar reutilización.
5. Los responsables trabajan estados —al menos `ABIERTO` y `PARA CERRAR`— e intercambian mensajes por movimiento. El administrador efectúa cierre y operaciones contables avanzadas, incluida fusión de asientos.

## Endpoints representativos

| Área | Endpoints PHP |
| --- | --- |
| períodos | `carga_masiva_periodos_guardar`, `guardar_periodos`, `trae_movimientos_periodo`, `limpiar_periodos` |
| asignación/estado | `guardar_raau`, `asignar_auto_raau`, `upsert_usuario_estado`, `cerrar_movimiento`, `cerrar_estados` |
| valores/asientos | `buscar_valores`, `upsert_valor`, `buscar_asientos`, `upsert_asiento`, `insertar_asientos`, `modificar_asiento`, `fusion_asientos` |
| mensajería | `enviar_mensaje`, `actualiza_mensajes` |

## Datos e integraciones

Usa tablas ERP (`valor`, `operacion`, `asiento`, `movimiento`, `cuenta`, `entidad`, `nodo`) y auxiliares: `bancos_mes_periodos_cargados_cuenta`, `bancos_mes_movimientos_cargados_periodo`, asignaciones de valor/asiento/usuario, `bancos_mes_raau`, `bancos_mes_exclusiones` y `bancos_mensajeria`. Los scripts de creación se ejecutan desde la aplicación; el de configuración admin se declara obsoleto. La única integración identificada es SGUA, que entrega `idu` y cuyas tablas de usuarios/permisos se consultan directamente.

## Reglas inferidas

- Una autoasignación requiere que la regla esté habilitada para validar, haya referencia y exista un único candidato.
- La búsqueda compara cuenta, monto, referencia y subtipos. Los subtipos `13`, `14`, `10070` y `10071` se consideran cheques y admiten valores no conciliados; el resto busca valores conciliados.
- Una asignación automática exitosa deja el movimiento `PARA CERRAR`; una RAAU sin asociación contable lo deja `ABIERTO`.
- Fusionar asientos revierte los asientos origen (`rev = true`) y genera uno nuevo con los movimientos resultantes.

## Riesgos y dudas

- Operaciones compuestas sin transacción; estado, asignación, exclusión y ERP pueden quedar divergentes.
- Identidad/rol enviados o deducidos desde el cliente y controles administrativos desiguales entre variantes.
- El identificador de movimiento depende de la posición de fila importada: reimportar o reordenar puede alterar asociaciones.
- SQL interpolado, CSRF ausente, usuario ERP fijo `16529`, scripts backup/obsoletos y restricciones auxiliares insuficientes.
- Deben validarse con negocio el catálogo de estados, cierres, reversas, fusiones y reglas de exclusión antes de migrar.
