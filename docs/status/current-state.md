# Estado actual

Descubrimiento estático completado para BANCOS, GESTION_USUARIOS y BANCOS_MENSUAL, más las referencias `nueva_app` y `hQuery`. El 2026-08-25 se completó además un relevamiento de solo lectura del catálogo productivo de las tablas `bancos_*` y `bancos_mes_*`; la evidencia y el modelo objetivo propuesto se documentaron en `docs/database/`. No se implementó código nuevo ni se modificaron legados.

Alcance confirmado: BANCOS concilia cheques; BANCOS_MENSUAL gestiona extractos mensuales, asignación, asociación y cierre. La futura identidad/permisos se basará en `nueva_app` y las funciones JavaScript comunes en hQuery; SGUA no se adopta como integración.
