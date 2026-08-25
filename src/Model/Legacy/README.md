# Capa legacy

`src/Model/Legacy` conserva por ahora los namespaces, constructores,
propiedades y respuestas que consumen las aplicaciones existentes. También
contiene las reglas que sólo existen para sostener esos contratos, como el
acceso forzado de una aplicación antigua.

El código nuevo no debe agregar casos de uso allí. Debe entrar por
`src/Model/Core`, con dependencias explícitas y contratos nuevos. Cuando un
consumidor legacy deba migrarse, se lo convierte en un adaptador delgado; las
excepciones históricas permanecen aquí hasta traducirlas a reglas
`global_prod.hub_*`.

La mudanza física no cambia los namespaces históricos `HClasses\...` ni
`GlobalApps\Legacy\...`. El autoload mantiene esos nombres para que las
aplicaciones anteriores no tengan que conocer la nueva organización.

El `/_shared` nuevo consume `Sesion` y arma el contexto canónico
`sesion/app/data` después de autorizar el destino y cargar
`app/php/carga_inicial.php`. Antes de publicar el JSON incluye libremente
`app/compatibilidad/contexto.php` en el mismo scope del header y de la página.
Una aplicación histórica puede definir allí `$user`, `$nivel_acceso` u otras
variables PHP que todavía consuma, y también ajustar `$contextoApp` mientras
conserve válido el sobre canónico.

`app/compatibilidad/contexto.js` recibe después una copia de ese contexto. El
archivo de la plantilla es una función identidad; cada aplicación legacy puede
reemplazarla para producir su `vars` histórico exacto sin publicar ambos modelos
a la vez. Los aliases planos que necesita el hQuery actual son temporales y no
forman parte del nuevo contrato.

`GRUPOS_CON_ACCESO` y el acceso forzado sólo se evalúan en esta capa. El Core
nuevo exige que esas excepciones se traduzcan a permisos `global_prod.hub_*`.

`ListadoProcesos`, productos, comisiones y cuenta corriente no forman parte del Core general. Sólo se migrarán si aparece un consumidor real y el caso de uso demuestra que la abstracción es reutilizable. El listado particular del semáforo debe vivir con el semáforo.
