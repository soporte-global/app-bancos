# Autoridades certificantes

`isrg-root-x1.pem` es el certificado raíz público de Internet Security Research Group utilizado para validar el HTTPS de APIS-global.

Fuente oficial: `https://letsencrypt.org/certs/isrgrootx1.pem`.

Se incluye porque el cURL 7.70 de PHP 7.4 en el WAMP de desarrollo no trae un almacén de autoridades certificantes ni puede usar el almacén nativo de Windows. El transporte mantiene activas `CURLOPT_SSL_VERIFYHOST` y `CURLOPT_SSL_VERIFYPEER`; este archivo permite validar la cadena sin recurrir a conexiones inseguras.

En un servidor que ya tenga un bundle de CA administrado por el sistema, `apis_v2_ca_bundle` puede apuntar a ese archivo o quedar vacío para usar la configuración de PHP.
