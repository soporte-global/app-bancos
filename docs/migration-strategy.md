# Estrategia de migración: propuesta pendiente de validación

1. Caracterizar flujos de cheque y mensual con ejemplos anonimizados.
2. Crear la aplicación desde la estructura de `nueva_app`, configurar su id del Hub y reutilizar su Core de autenticación/autorización; no migrar SGUA.
3. Modelar períodos, movimientos, asociaciones, mensajes y auditoría con integridad referencial en tablas `global_prod.bancos_*`, con nombres en español.
4. Implementar efectos ERP transaccionales e idempotentes, con pruebas de integración.
5. Incorporar `hQuery` desde una versión publicada y validada como librería común, adaptando sus funciones al contexto canónico de `nueva_app`.
6. Migrar primero consulta/importación y operación asistida; habilitar automatismos y operaciones contables avanzadas tras validación.

No hay autorización para cambios de datos ni migraciones en esta etapa.
