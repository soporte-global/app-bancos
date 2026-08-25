# Sesión de descubrimiento — 2026-08-25

Se incorporó BANCOS_MENSUAL y se corrigió el alcance de BANCOS a conciliación de cheques. SGUA fue descartada como integración futura de identidad/permisos: se adopta `nueva_app` como referencia y hQuery como futura librería de funciones comunes.

Se relevó en modo de solo lectura el catálogo productivo de 21 tablas `bancos_*` y `bancos_mes_*`. El hallazgo principal es la falta generalizada de PK/FK y la existencia de 121 restricciones UNIQUE redundantes en `bancos_mes_periodos_cargados_cuenta`. Se documentaron el estado real y el modelo destino propuesto en `docs/database/`. No se tomaron decisiones canónicas sin validación funcional ni se modificó producción.
