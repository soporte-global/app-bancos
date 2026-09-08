(function () {
    'use strict';

    function periodoLegible(valor) {
        var coincidencia = /^(\d{4})-(\d{2})-\d{2}$/.exec(valor);
        if (!coincidencia) {
            return 'Sin seleccionar';
        }

        var meses = [
            'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
            'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'
        ];

        return meses[Number(coincidencia[2]) - 1] + ' de ' + coincidencia[1];
    }

    function iniciarBarraContexto(raiz) {
        var formulario = raiz.querySelector('.bandeja-filtros');
        var barra = raiz.querySelector('[data-bandeja-contexto]');
        var cuenta = formulario && formulario.elements.cuenta_bancaria_id;
        var periodo = formulario && formulario.elements.inicio_periodo;
        var salidaCuenta = barra && barra.querySelector('[data-contexto-cuenta]');
        var salidaPeriodo = barra && barra.querySelector('[data-contexto-periodo]');
        var salidaEstado = barra && barra.querySelector('[data-contexto-estado]');
        var filtros = barra && barra.querySelector('[data-filtros-activos]');
        var filtrosVacio = filtros && filtros.querySelector('[data-filtros-vacio]');
        var filtrosLista = filtros && filtros.querySelector('[data-filtros-lista]');
        var limite = formulario && formulario.elements.limite;
        var estadoCargando = raiz.querySelector('[data-estado-cargando]');
        var reintentar = raiz.querySelector('[data-reintentar-consulta]');
        var seleccionarContexto = raiz.querySelector('[data-seleccionar-contexto]');

        if (!formulario || !barra || !cuenta || !periodo || !salidaCuenta || !salidaPeriodo || !salidaEstado) {
            return;
        }

        function actualizarContexto() {
            salidaCuenta.textContent = cuenta.value || 'Sin seleccionar';
            salidaPeriodo.textContent = periodoLegible(periodo.value);
        }

        function actualizarFiltros() {
            if (!filtrosVacio || !filtrosLista || !limite) {
                return;
            }

            filtrosLista.textContent = '';
            if (limite.value === '' || limite.value === '50') {
                filtrosVacio.hidden = false;
                filtrosLista.hidden = true;
                return;
            }

            var item = document.createElement('li');
            var etiqueta = document.createElement('span');
            var limpiar = document.createElement('button');
            etiqueta.textContent = 'Límite: ' + limite.value;
            limpiar.type = 'button';
            limpiar.setAttribute('aria-label', 'Quitar filtro Límite');
            limpiar.setAttribute('data-limpiar-filtro', 'limite');
            limpiar.textContent = '×';
            item.appendChild(etiqueta);
            item.appendChild(limpiar);
            filtrosLista.appendChild(item);
            filtrosVacio.hidden = true;
            filtrosLista.hidden = false;
        }

        cuenta.addEventListener('input', actualizarContexto);
        periodo.addEventListener('input', actualizarContexto);
        limite.addEventListener('input', actualizarFiltros);
        filtros.addEventListener('click', function (evento) {
            if (!evento.target.matches('[data-limpiar-filtro="limite"]')) {
                return;
            }
            limite.value = '50';
            actualizarFiltros();
            limite.focus();
        });
        function mostrarCarga() {
            actualizarContexto();
            barra.setAttribute('data-cargando', 'true');
            salidaEstado.textContent = 'Cargando movimientos…';
            if (estadoCargando) {
                estadoCargando.hidden = false;
                estadoCargando.setAttribute('aria-busy', 'true');
            }
        }

        formulario.addEventListener('submit', function () {
            mostrarCarga();
        });

        if (reintentar) {
            reintentar.addEventListener('click', function () {
                var errorActual = reintentar.closest('[data-contenido-bandeja]');
                if (errorActual) {
                    errorActual.hidden = true;
                }
                if (typeof formulario.requestSubmit === 'function') {
                    formulario.requestSubmit();
                } else {
                    mostrarCarga();
                    formulario.submit();
                }
            });
        }

        if (seleccionarContexto) {
            seleccionarContexto.addEventListener('click', function () {
                (cuenta.value ? periodo : cuenta).focus();
            });
        }
    }

    function rutaRelativa(url) {
        return url.pathname + url.search;
    }

    function leerEstadoPagina(clave) {
        try {
            return JSON.parse(window.sessionStorage.getItem(clave));
        } catch (error) {
            return null;
        }
    }

    function guardarEstadoPagina(clave, estado) {
        try {
            window.sessionStorage.setItem(clave, JSON.stringify(estado));
        } catch (error) {
            // La navegación sigue disponible aunque el navegador bloquee sessionStorage.
        }
    }

    function iniciarPaginacion(raiz) {
        var paginacion = raiz.querySelector('[data-paginacion]');
        var anterior = paginacion && paginacion.querySelector('[data-pagina-anterior]');
        var siguiente = paginacion && paginacion.querySelector('[data-pagina-siguiente]');
        var posicion = paginacion && paginacion.querySelector('[data-posicion-pagina]');

        if (!paginacion || !anterior || !posicion) {
            return;
        }

        var actual = rutaRelativa(window.location);
        var claveActual = 'bandeja-pagina:' + actual;
        var cantidad = Number(paginacion.getAttribute('data-cantidad')) || 0;
        var parametros = new URLSearchParams(window.location.search);
        var estado = leerEstadoPagina(claveActual);

        if (!parametros.has('cursor')) {
            estado = { pagina: 1, inicio: cantidad > 0 ? 1 : 0, anterior: null };
        }

        if (estado) {
            var fin = estado.inicio === 0 ? 0 : estado.inicio + cantidad - 1;
            posicion.textContent = 'Página ' + estado.pagina + ' · movimientos ' + estado.inicio + '–' + fin;
            if (estado.anterior) {
                anterior.href = estado.anterior;
                anterior.removeAttribute('aria-disabled');
            }
        }

        if (siguiente) {
            siguiente.addEventListener('click', function () {
                var destino = rutaRelativa(new URL(siguiente.href, window.location.href));
                var paginaActual = estado && estado.pagina ? estado.pagina : 1;
                var inicioActual = estado && typeof estado.inicio === 'number' ? estado.inicio : 0;
                guardarEstadoPagina('bandeja-pagina:' + destino, {
                    pagina: paginaActual + 1,
                    inicio: inicioActual + cantidad,
                    anterior: actual
                });
            });
        }
    }

    function iniciar() {
        var bandejas = document.querySelectorAll('[data-bandeja]');
        Array.prototype.forEach.call(bandejas, function (bandeja) {
            iniciarBarraContexto(bandeja);
            iniciarPaginacion(bandeja);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', iniciar);
    } else {
        iniciar();
    }
}());
