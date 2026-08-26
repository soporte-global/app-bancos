# Sesión de descubrimiento - 2026-08-25

Se incorporó BANCOS_MENSUAL y se corrigió el alcance de BANCOS a conciliación de cheques. SGUA fue descartada como integración futura de identidad/permisos: se adopta `nueva_app` como referencia y hQuery como futura librería de funciones comunes.

En esta sesión también se copió la estructura base de `nueva_app` hacia `app-bancos` manteniendo intacto el material preexistente. Se agregaron todos los componentes de bootstrap, carpetas compartidas y dependencias en modo incremental (sin sobrescritura), de modo que la base está lista para configurar permisos de Hub y comenzar desarrollo funcional.

Se relevó en modo de solo lectura el catálogo productivo de 21 tablas `bancos_*` y `bancos_mes_*`. El hallazgo principal es la falta generalizada de PK/FK y la existencia de 121 restricciones UNIQUE redundantes en `bancos_mes_periodos_cargados_cuenta`.

La validación agregada confirmó que `(id_periodo, serial_seq)` es única, mientras que `id_movimiento` tiene 1.055 valores duplicados; también confirmó los tres estados vigentes, dos decimales de precisión y el uso combinado de configuración global y por cuenta. Se actualizó el modelo destino y se incorporó `docs/database/production-validation.md`. Como convención de diseño se definió que las nuevas tablas estarán en `global_prod`, conservarán el prefijo `bancos_` y usarán nombres en español. Permanecen pendientes sólo las decisiones funcionales que los datos no conservan: transiciones, asociaciones múltiples, reversas/fusiones y retención. No se modificó producción.

Hoy también se materializó y ejecutó correctamente el primer paquete de DDL en `app/sql/migraciones/004_bancos_tablas_auxiliares.sql`, cubriendo tablas para configuración, importación de extractos, movimientos, historial, asociaciones, reservas, borradores, mensajes y conciliación de cheque. La corrida final se realizó el 2026-08-25 contra `ftweb` en `localhost:5500` con usuario `postgres` y finalizó con `MIGRACION_OK`.

También se ejecutó `app/sql/migraciones/005_bancos_campos_zetti.sql` para cambiar nombres de columnas `*_erp_*` a `*_zetti_*` en `global_prod.bancos_*`, y quedó verificado sin columnas ni constraints residuales con `erp` en esos objetos.

También se relevó el catálogo ERP necesario. La relación entre operación y valor es `operacion_valor`; los importes contables ERP son `numeric(20,5)` y los IDs de valor, operación, asiento, cuenta, entidad y cuenta bancaria son `bigint`, mientras que nodo usa `integer`. El modelo objetivo y `docs/database/erp-structure.md` fueron ajustados en consecuencia. No se modificó producción.
