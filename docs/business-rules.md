# Reglas de negocio: evidencia actual

Reglas inferidas que requieren validación de negocio:

- BANCOS se utiliza exclusivamente para conciliar cheques.
- BANCOS_MENSUAL importa extractos por cuenta y período, asigna su resolución y cierra movimientos.
- La asociación automática exige un único candidato válido y considera cuenta, monto, referencia, subtipo y estado.
- Un valor o asiento no debería asignarse a más de un movimiento.
- La exclusividad de valor/asiento se hace cumplir al reservar y asociar dentro de una misma transacción; una lectura previa de disponibilidad no es suficiente ante concurrencia.
- Los movimientos transitan al menos por `ABIERTO` y `PARA CERRAR`.
- Revertir o fusionar asientos requiere trazabilidad y control contable explícito.
- La autorización futura debe aplicarse por aplicación y destino interno después de autenticar la sesión; no se debe confiar en `idu`, cookies legacy ni permisos de SGUA.
- La autoasignación sólo se confirma si el caso de uso obtiene un único candidato que supera el umbral acordado; la consulta devuelve evidencia y puntaje, pero no modifica estado por sí misma.
