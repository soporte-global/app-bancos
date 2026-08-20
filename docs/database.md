# Base de datos: estado de descubrimiento

Los legados usan PostgreSQL `ftweb` y escriben en el ERP. BANCOS_MENSUAL añade `bancos_mes_*` y `bancos_mensajeria`; BANCOS añade `bancos_guardado_*`. SGUA y sus tablas no son fuente para la nueva identidad.

La identidad y permisos nuevos deben consumir el Hub de `ftweb.global_prod`, en particular el catálogo y las concesiones `hub_*` y su vista de permisos efectivos. No se deben crear tablas de credenciales equivalentes a `login_users`.

El DDL heredado se ejecuta desde requests y no debe ser el diseño canónico. La migración debe partir del esquema productivo real, con integridad referencial, índices, unicidad, auditoría y migraciones reversibles cuando sea posible.
