(function () {
    const contenido = document.getElementById('contexto-app').textContent;
    const contextoCanonico = JSON.parse(contenido);

    validarContextoCanonico(contextoCanonico);

    if (typeof window.adaptarContextoApp !== 'function') {
        throw new TypeError('No se encontro el adaptador JavaScript de contexto.');
    }

    // el adaptador recibe una copia para que no pueda alterar el contexto compartido
    const contextoCompatible = window.adaptarContextoApp(JSON.parse(contenido));

    if (!contextoCompatible || typeof contextoCompatible !== 'object' || Array.isArray(contextoCompatible)) {
        throw new TypeError('El adaptador de contexto debe devolver un objeto.');
    }

    if (typeof vars === 'undefined' || !vars || typeof vars !== 'object' || Array.isArray(vars)) {
        throw new TypeError('hQuery debe inicializar vars antes de cargar el contexto.');
    }

    Object.defineProperty(window, 'contextoApp', {
        configurable: true,
        enumerable: false,
        value: contextoCanonico,
        writable: false,
    });

    Object.assign(vars, contextoCompatible);

    function validarContextoCanonico(contexto) {
        const esObjeto = contexto && typeof contexto === 'object' && !Array.isArray(contexto);
        const appValida = esObjeto && contexto.app && typeof contexto.app === 'object' && !Array.isArray(contexto.app);
        const dataValida = esObjeto && contexto.data && typeof contexto.data === 'object' && !Array.isArray(contexto.data);

        if (!esObjeto || !Object.prototype.hasOwnProperty.call(contexto, 'sesion') || !appValida || !dataValida) {
            throw new TypeError('El contexto canonico debe contener sesion, app y data.');
        }
    }
})();
