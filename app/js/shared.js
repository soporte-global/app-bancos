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

    function iniciarTema(raiz) {
        var boton = raiz.querySelector('[data-alternar-tema]');
        var clave = 'bandeja-tema';
        var tema = 'claro';

        if (!boton) {
            return;
        }

        try {
            tema = window.localStorage.getItem(clave) === 'oscuro' ? 'oscuro' : 'claro';
        } catch (error) {
            tema = 'claro';
        }

        function aplicarTema(nuevoTema) {
            var oscuro = nuevoTema === 'oscuro';
            tema = oscuro ? 'oscuro' : 'claro';
            if (oscuro) {
                raiz.setAttribute('data-tema', 'oscuro');
                document.body.classList.add('bandeja-tema-oscuro');
            } else {
                raiz.removeAttribute('data-tema');
                document.body.classList.remove('bandeja-tema-oscuro');
            }
            boton.setAttribute('aria-pressed', oscuro ? 'true' : 'false');
            boton.textContent = oscuro ? 'Modo claro' : 'Modo oscuro';
        }

        aplicarTema(tema);
        boton.addEventListener('click', function () {
            aplicarTema(tema === 'oscuro' ? 'claro' : 'oscuro');
            try {
                window.localStorage.setItem(clave, tema);
            } catch (error) {
                // La preferencia sigue activa durante la vista aunque no pueda persistirse.
            }
        });
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

    function paginaInicialActual() {
        var inicio = new URL(window.location.href);
        inicio.searchParams.delete('cursor');
        return rutaRelativa(inicio);
    }

    function paginaAnteriorDesdeReferrer(parametrosActuales) {
        if (!document.referrer) {
            return null;
        }

        try {
            var referencia = new URL(document.referrer);
            if (referencia.origin !== window.location.origin || referencia.pathname !== window.location.pathname) {
                return null;
            }

            var parametrosReferencia = referencia.searchParams;
            var mismoContexto = ['pag', 'cuenta_bancaria_id', 'inicio_periodo'].every(function (nombre) {
                return parametrosReferencia.get(nombre) === parametrosActuales.get(nombre);
            });
            return mismoContexto ? rutaRelativa(referencia) : null;
        } catch (error) {
            return null;
        }
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
        } else if (!estado) {
            estado = {
                pagina: null,
                inicio: null,
                anterior: paginaAnteriorDesdeReferrer(parametros) || paginaInicialActual()
            };
        }

        if (estado && estado.pagina) {
            var fin = estado.inicio === 0 ? 0 : estado.inicio + cantidad - 1;
            posicion.textContent = 'Página ' + estado.pagina + ' · movimientos ' + estado.inicio + '–' + fin;
        }

        if (estado && estado.anterior) {
            anterior.href = estado.anterior;
            anterior.removeAttribute('aria-disabled');
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

    function iniciarDetalle(raiz) {
        var panel = raiz.querySelector('[data-panel-detalle]');
        var fondo = raiz.querySelector('[data-detalle-fondo]');
        var cuerpo = panel && panel.querySelector('[data-detalle-cuerpo]');
        var cerrar = panel && panel.querySelector('[data-cerrar-detalle]');
        var disparador = null;
        var posicionScroll = 0;

        if (!panel || !fondo || !cuerpo || !cerrar) {
            return;
        }

        function cerrarDetalle() {
            panel.hidden = true;
            fondo.hidden = true;
            cuerpo.textContent = '';
            document.documentElement.classList.remove('bandeja-detalle-activo');
            document.body.classList.remove('bandeja-detalle-activo');
            if (disparador) {
                try {
                    disparador.focus({ preventScroll: true });
                } catch (error) {
                    disparador.focus();
                }
                window.scrollTo(0, posicionScroll);
            }
        }

        raiz.addEventListener('click', function (evento) {
            var boton = evento.target.closest('[data-abrir-detalle]');
            var fila = boton && boton.closest('tr');
            var plantilla = fila && fila.querySelector('[data-detalle-movimiento]');
            if (!boton || !plantilla) {
                return;
            }

            disparador = boton;
            posicionScroll = window.scrollY;
            cuerpo.textContent = '';
            cuerpo.appendChild(plantilla.content.cloneNode(true));
            fondo.hidden = false;
            panel.hidden = false;
            document.documentElement.classList.add('bandeja-detalle-activo');
            document.body.classList.add('bandeja-detalle-activo');
            cerrar.focus();
        });

        cerrar.addEventListener('click', cerrarDetalle);
        fondo.addEventListener('click', cerrarDetalle);
        panel.addEventListener('keydown', function (evento) {
            if (evento.key === 'Escape') {
                cerrarDetalle();
            }
        });
    }

    function iniciarFooter(raiz) {
        var controles = raiz.querySelector('[data-controles-bandeja]');
        var footer = document.querySelector('header.footer');
        if (!controles || !footer) {
            return;
        }

        footer.classList.add('bandeja-footer');
        document.body.classList.add('bandeja-con-footer');
        footer.appendChild(controles);
        footer.removeAttribute('hidden');
        footer.style.removeProperty('display');
    }

    function iniciar() {
        var bandejas = document.querySelectorAll('[data-bandeja]');
        if (bandejas.length > 0) {
            document.documentElement.classList.add('bandeja-scroll');
            document.body.classList.add('bandeja-scroll');
        }
        Array.prototype.forEach.call(bandejas, function (bandeja) {
            iniciarTema(bandeja);
            iniciarBarraContexto(bandeja);
            iniciarPaginacion(bandeja);
            iniciarDetalle(bandeja);
            iniciarFooter(bandeja);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', iniciar);
    } else {
        iniciar();
    }
}());
