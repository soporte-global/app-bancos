'use strict';

const assert = require('assert');
const fs = require('fs');
const path = require('path');
const vm = require('vm');

const raiz = path.resolve(__dirname, '..');
const cargador = fs.readFileSync(path.join(raiz, '_shared/js/contexto-app.js'), 'utf8');
const adaptadorIdentidad = fs.readFileSync(path.join(raiz, 'app/compatibilidad/contexto.js'), 'utf8');
const htmlHead = fs.readFileSync(path.join(raiz, '_shared/html/html-head.html'), 'utf8');
const parcialContexto = fs.readFileSync(path.join(raiz, '_shared/html/contexto-app.php'), 'utf8');
const contexto = {
    sesion: null,
    app: {
        id: 0,
        nombre: 'Nueva App',
        pagina: 'home',
        ruta: 'http://localhost/Desarrollos/nueva_app',
    },
    data: {
        sociedades: [{ id: 20, nombre: 'Global' }],
    },
};

function ejecutar(adaptador, varsIniciales) {
    const contenido = JSON.stringify(contexto);
    const sandbox = {
        console: console,
        document: {
            getElementById: function (id) {
                assert.strictEqual(id, 'contexto-app');
                return { textContent: contenido };
            },
        },
        vars: varsIniciales,
    };
    sandbox.window = sandbox;
    vm.createContext(sandbox);
    vm.runInContext(adaptador, sandbox, { filename: 'app/compatibilidad/contexto.js' });
    vm.runInContext(cargador, sandbox, { filename: '_shared/js/contexto-app.js' });
    return sandbox;
}

const hquery = {
    estados: {
        solicitudes: { estados: {}, activas: {} },
    },
};
const nuevo = ejecutar(adaptadorIdentidad, { hquery: hquery });

assert.strictEqual(nuevo.vars.hquery, hquery);
assert.strictEqual(nuevo.vars.sesion, null);
assert.strictEqual(nuevo.vars.app.id, 0);
assert.strictEqual(nuevo.vars.data.sociedades[0].id, 20);
assert.strictEqual(Object.prototype.hasOwnProperty.call(nuevo.vars, 'estado'), false);
assert.strictEqual(nuevo.contextoApp.app.nombre, 'Nueva App');

for (const alias of ['id_aplicacion', 'nombre_app', 'pag_cargada', 'ruta_aplicacion']) {
    assert.strictEqual(Object.prototype.hasOwnProperty.call(nuevo.vars, alias), false);
}

nuevo.vars.app.nombre = 'nombre local';
assert.strictEqual(nuevo.contextoApp.app.nombre, 'Nueva App');

const adaptadorLegacy = `
window.adaptarContextoApp = function (contexto) {
    contexto.app.ruta = '/ruta/legacy';
    return {
        user: contexto.sesion,
        ruta_aplicacion: contexto.app.ruta
    };
};`;
const legacy = ejecutar(adaptadorLegacy, { hquery: hquery });

assert.strictEqual(legacy.vars.hquery, hquery);
assert.strictEqual(Object.prototype.hasOwnProperty.call(legacy.vars, 'app'), false);
assert.strictEqual(Object.prototype.hasOwnProperty.call(legacy.vars, 'estado'), false);
assert.strictEqual(legacy.vars.user, null);
assert.strictEqual(legacy.vars.ruta_aplicacion, '/ruta/legacy');
assert.strictEqual(Object.getOwnPropertyDescriptor(legacy.vars, 'ruta_aplicacion').enumerable, true);
assert.strictEqual(Object.prototype.hasOwnProperty.call(legacy.vars, 'nombre_app'), false);
assert.strictEqual(legacy.contextoApp.app.ruta, contexto.app.ruta);
assert.strictEqual(htmlHead.includes('app/js/shared.js'), false);
assert.ok(parcialContexto.indexOf('_shared/js/contexto-app.js') < parcialContexto.indexOf('app/js/shared.js'));

console.log('Contexto app OK');
