# Problemas conocidos de los legados

- Identidad y autorización basadas en cookies o `idu` controlado por cliente; controles de rol no homogéneos.
- Escrituras ERP compuestas sin transacciones y con usuario fijo `16529`.
- SQL interpolado, CSRF ausente y DDL ejecutado desde requests.
- En BANCOS_MENSUAL, identificadores derivados de la posición de importación y exclusiones insuficientes para probar seguridad concurrente.
- En SGUA, el borrado masivo exige `IDu=1` pero la cuenta administrativa reparada es `IDu=13`.
