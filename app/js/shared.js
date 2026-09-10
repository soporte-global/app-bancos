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
            salidaCuenta.textContent = cuenta.value && cuenta.selectedIndex >= 0
                ? cuenta.options[cuenta.selectedIndex].textContent
                : 'Sin seleccionar';
            salidaPeriodo.textContent = periodoLegible(periodo.value);
        }

        function actualizarPeriodos() {
            var seleccionAnterior = periodo.value;
            Array.prototype.forEach.call(periodo.options, function (opcion) {
                if (!opcion.dataset.cuenta) {
                    opcion.hidden = false;
                    opcion.disabled = false;
                    return;
                }
                var disponible = opcion.dataset.cuenta === cuenta.value;
                opcion.hidden = !disponible;
                opcion.disabled = !disponible;
            });
            if (!periodo.selectedOptions.length || periodo.selectedOptions[0].disabled) {
                periodo.value = '';
            } else {
                periodo.value = seleccionAnterior;
            }
        }

        function actualizarFiltros() {
            if (!filtrosVacio || !filtrosLista || !limite) {
                return;
            }

            filtrosLista.textContent = '';
            var activos = [];
            var definiciones = [
                { nombre: 'estado', prefijo: 'Estado: ' },
                { nombre: 'responsable_id', prefijo: 'Responsable: ' },
                { nombre: 'asociacion', prefijo: '' },
                { nombre: 'mensajes', prefijo: '' }
            ];
            if (limite.value !== '' && limite.value !== '50') {
                activos.push({ nombre: 'limite', etiqueta: 'Límite: ' + limite.value });
            }
            definiciones.forEach(function (definicion) {
                var control = formulario.elements[definicion.nombre];
                if (control && control.value) {
                    activos.push({
                        nombre: definicion.nombre,
                        etiqueta: definicion.prefijo + control.options[control.selectedIndex].textContent
                    });
                }
            });
            if (activos.length === 0) {
                filtrosVacio.hidden = false;
                filtrosLista.hidden = true;
                return;
            }
            activos.forEach(function (activo) {
                var item = document.createElement('li');
                var etiqueta = document.createElement('span');
                var limpiar = document.createElement('button');
                etiqueta.textContent = activo.etiqueta;
                limpiar.type = 'button';
                limpiar.setAttribute('aria-label', 'Quitar filtro ' + activo.etiqueta);
                limpiar.setAttribute('data-limpiar-filtro', activo.nombre);
                limpiar.textContent = '×';
                item.appendChild(etiqueta);
                item.appendChild(limpiar);
                filtrosLista.appendChild(item);
            });
            filtrosVacio.hidden = true;
            filtrosLista.hidden = false;
        }

        cuenta.addEventListener('input', function () {
            actualizarPeriodos();
            actualizarContexto();
        });
        periodo.addEventListener('input', actualizarContexto);
        Array.prototype.forEach.call(formulario.querySelectorAll('[name="estado"], [name="responsable_id"], [name="asociacion"], [name="mensajes"]'), function (control) {
            control.addEventListener('input', actualizarFiltros);
        });
        limite.addEventListener('input', actualizarFiltros);
        filtros.addEventListener('click', function (evento) {
            var nombre = evento.target.getAttribute('data-limpiar-filtro');
            if (!nombre || !formulario.elements[nombre]) {
                return;
            }
            formulario.elements[nombre].value = nombre === 'limite' ? '50' : '';
            actualizarFiltros();
            formulario.elements[nombre].focus();
        });
        actualizarPeriodos();
        function mostrarCarga() {
            actualizarContexto();
            raiz.setAttribute('aria-busy', 'true');
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
            var mismoContexto = ['pag', 'cuenta_bancaria_id', 'inicio_periodo', 'estado', 'responsable_id', 'asociacion', 'mensajes', 'limite'].every(function (nombre) {
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
        var parametros = new URLSearchParams(window.location.search);
        var estado = leerEstadoPagina(claveActual);

        if (!parametros.has('cursor')) {
            estado = { anterior: null };
        } else if (!estado) {
            estado = {
                anterior: paginaAnteriorDesdeReferrer(parametros) || paginaInicialActual()
            };
        }

        if (estado && estado.anterior) {
            anterior.href = estado.anterior;
            anterior.removeAttribute('aria-disabled');
        }

        if (siguiente) {
            siguiente.addEventListener('click', function () {
                var destino = rutaRelativa(new URL(siguiente.href, window.location.href));
                guardarEstadoPagina('bandeja-pagina:' + destino, {
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
        var formularioBandeja = raiz.querySelector('.bandeja-filtros');
        var disparador = null;
        var posicionScroll = 0;
        var csrfToken = null;

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

        function crearClaveIdempotencia() {
            if (window.crypto && typeof window.crypto.randomUUID === 'function') {
                return window.crypto.randomUUID();
            }
            var aleatorio = window.crypto && typeof window.crypto.getRandomValues === 'function'
                ? window.crypto.getRandomValues(new Uint32Array(4))
                : [Date.now(), Math.random() * 0xffffffff, Math.random() * 0xffffffff, Math.random() * 0xffffffff];
            return 'web-' + Array.prototype.map.call(aleatorio, function (valor) {
                return Math.floor(valor).toString(16);
            }).join('-');
        }

        function leerRespuesta(respuesta) {
            return respuesta.json().catch(function () {
                return {};
            }).then(function (contenido) {
                if (!respuesta.ok) {
                    var mensaje = contenido.error && contenido.error.mensaje
                        ? contenido.error.mensaje
                        : 'No se pudo completar la acción.';
                    throw new Error(mensaje);
                }
                return contenido;
            });
        }

        function obtenerCsrf() {
            if (csrfToken) {
                return Promise.resolve(csrfToken);
            }
            return fetch(window.contextoApp.app.ruta + '/api.php?accion=csrf', {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' }
            }).then(leerRespuesta).then(function (contenido) {
                csrfToken = contenido.data.csrf_token;
                return csrfToken;
            });
        }

        function prepararMovimiento(boton) {
            var bloque = boton.closest('[data-accion-preparar]');
            var estado = bloque && bloque.querySelector('[data-accion-estado]');
            var clave = boton.dataset.idempotencyKey || crearClaveIdempotencia();
            boton.dataset.idempotencyKey = clave;
            boton.disabled = true;
            if (estado) {
                estado.textContent = 'Preparando movimiento…';
            }
            obtenerCsrf().then(function (token) {
                return fetch(window.contextoApp.app.ruta + '/api.php?accion=movimiento.preparar', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': token,
                        'Idempotency-Key': clave
                    },
                    body: JSON.stringify({
                        movimiento_id: Number(boton.dataset.movimientoId),
                        cuenta_bancaria_id: boton.dataset.cuentaBancariaId,
                        inicio_periodo: boton.dataset.inicioPeriodo
                    })
                });
            }).then(leerRespuesta).then(function () {
                boton.textContent = 'Preparado';
                if (estado) {
                    estado.textContent = 'Movimiento marcado PARA_CERRAR. Actualizando la bandeja…';
                }
                window.setTimeout(function () {
                    if (formularioBandeja && typeof formularioBandeja.requestSubmit === 'function') {
                        formularioBandeja.requestSubmit();
                    } else if (formularioBandeja) {
                        formularioBandeja.submit();
                    }
                }, 700);
            }).catch(function (error) {
                boton.disabled = false;
                if (estado) {
                    estado.textContent = error.message;
                }
            });
        }

        function revertirPreparacion(boton) {
            var bloque = boton.closest('[data-accion-revertir]');
            var motivoControl = bloque && bloque.querySelector('[data-motivo-reversion]');
            var estado = bloque && bloque.querySelector('[data-accion-estado]');
            var motivo = motivoControl ? motivoControl.value.trim() : '';
            if (motivo.length < 3) {
                if (estado) {
                    estado.textContent = 'Ingresá un motivo de al menos 3 caracteres.';
                }
                if (motivoControl) {
                    motivoControl.focus();
                }
                return;
            }
            var clave = boton.dataset.idempotencyKey || crearClaveIdempotencia();
            boton.dataset.idempotencyKey = clave;
            boton.disabled = true;
            motivoControl.disabled = true;
            if (estado) {
                estado.textContent = 'Revirtiendo preparación…';
            }
            obtenerCsrf().then(function (token) {
                return fetch(window.contextoApp.app.ruta + '/api.php?accion=movimiento.revertir-preparacion', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': token,
                        'Idempotency-Key': clave
                    },
                    body: JSON.stringify({
                        movimiento_id: Number(boton.dataset.movimientoId),
                        cuenta_bancaria_id: boton.dataset.cuentaBancariaId,
                        inicio_periodo: boton.dataset.inicioPeriodo,
                        motivo: motivo
                    })
                });
            }).then(leerRespuesta).then(function (contenido) {
                var descartados = contenido.data.descartados;
                boton.textContent = 'Preparación revertida';
                if (estado) {
                    estado.textContent = 'Movimiento ABIERTO. Se desactivaron ' +
                        descartados.asociaciones + ' asociaciones, ' +
                        descartados.reservas + ' reservas y ' +
                        descartados.borradores + ' borradores.';
                }
                window.setTimeout(function () {
                    if (formularioBandeja && typeof formularioBandeja.requestSubmit === 'function') {
                        formularioBandeja.requestSubmit();
                    } else if (formularioBandeja) {
                        formularioBandeja.submit();
                    }
                }, 900);
            }).catch(function (error) {
                boton.disabled = false;
                motivoControl.disabled = false;
                if (estado) {
                    estado.textContent = error.message;
                }
            });
        }

        function asociarValor(boton) {
            var bloque = boton.closest('[data-accion-asociar-valor]');
            var valorControl = bloque && bloque.querySelector('[data-valor-zetti-id]');
            var estado = bloque && bloque.querySelector('[data-accion-estado]');
            var valorId = valorControl ? Number(valorControl.value) : 0;
            if (!Number.isInteger(valorId) || valorId <= 0) {
                if (estado) {
                    estado.textContent = 'Ingresá un ID de valor ERP válido.';
                }
                if (valorControl) {
                    valorControl.focus();
                }
                return;
            }
            var clave = boton.dataset.idempotencyKey || crearClaveIdempotencia();
            boton.dataset.idempotencyKey = clave;
            boton.disabled = true;
            valorControl.disabled = true;
            if (estado) {
                estado.textContent = 'Reservando y asociando valor…';
            }
            obtenerCsrf().then(function (token) {
                return fetch(window.contextoApp.app.ruta + '/api.php?accion=movimiento.asociar-valor', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': token,
                        'Idempotency-Key': clave
                    },
                    body: JSON.stringify({
                        movimiento_id: Number(boton.dataset.movimientoId),
                        cuenta_bancaria_id: boton.dataset.cuentaBancariaId,
                        inicio_periodo: boton.dataset.inicioPeriodo,
                        valor_zetti_id: valorId
                    })
                });
            }).then(leerRespuesta).then(function (contenido) {
                boton.textContent = 'Valor asociado';
                if (estado) {
                    estado.textContent = 'Valor #' + contenido.data.valor_zetti_id +
                        ' asociado por ' + contenido.data.monto_asociado + '. Actualizando…';
                }
                window.setTimeout(function () {
                    if (formularioBandeja && typeof formularioBandeja.requestSubmit === 'function') {
                        formularioBandeja.requestSubmit();
                    } else if (formularioBandeja) {
                        formularioBandeja.submit();
                    }
                }, 900);
            }).catch(function (error) {
                boton.disabled = false;
                valorControl.disabled = false;
                if (estado) {
                    estado.textContent = error.message;
                }
            });
        }

        function asociarAsiento(boton) {
            var bloque = boton.closest('[data-accion-asociar-asiento]');
            var asientoControl = bloque && bloque.querySelector('[data-asiento-zetti-id]');
            var compartidoControl = bloque && bloque.querySelector('[data-asiento-compartido]');
            var estado = bloque && bloque.querySelector('[data-accion-estado]');
            var asientoId = asientoControl ? Number(asientoControl.value) : 0;
            if (!Number.isInteger(asientoId) || asientoId <= 0) {
                if (estado) {
                    estado.textContent = 'Ingresá un ID de asiento ERP válido.';
                }
                if (asientoControl) {
                    asientoControl.focus();
                }
                return;
            }
            var clave = boton.dataset.idempotencyKey || crearClaveIdempotencia();
            boton.dataset.idempotencyKey = clave;
            boton.disabled = true;
            asientoControl.disabled = true;
            compartidoControl.disabled = true;
            if (estado) {
                estado.textContent = 'Validando, reservando y asociando asiento…';
            }
            obtenerCsrf().then(function (token) {
                return fetch(window.contextoApp.app.ruta + '/api.php?accion=movimiento.asociar-asiento', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': token,
                        'Idempotency-Key': clave
                    },
                    body: JSON.stringify({
                        movimiento_id: Number(boton.dataset.movimientoId),
                        cuenta_bancaria_id: boton.dataset.cuentaBancariaId,
                        inicio_periodo: boton.dataset.inicioPeriodo,
                        asiento_zetti_id: asientoId,
                        compartido: compartidoControl.checked
                    })
                });
            }).then(leerRespuesta).then(function (contenido) {
                boton.textContent = 'Asiento asociado';
                if (estado) {
                    estado.textContent = 'Asiento #' + contenido.data.asiento_zetti_id +
                        ' asociado por ' + contenido.data.monto_asociado +
                        (contenido.data.compartido ? ' como compartido. ' : '. ') + 'Actualizando…';
                }
                window.setTimeout(function () {
                    if (formularioBandeja && typeof formularioBandeja.requestSubmit === 'function') {
                        formularioBandeja.requestSubmit();
                    } else if (formularioBandeja) {
                        formularioBandeja.submit();
                    }
                }, 900);
            }).catch(function (error) {
                boton.disabled = false;
                asientoControl.disabled = false;
                compartidoControl.disabled = false;
                if (estado) {
                    estado.textContent = error.message;
                }
            });
        }

        function agregarLineaBorrador(boton) {
            var bloque = boton.closest('[data-accion-crear-borrador]');
            var contenedor = bloque && bloque.querySelector('[data-borrador-lineas]');
            var referencia = contenedor && contenedor.querySelector('[data-borrador-linea]');
            if (!contenedor || !referencia || contenedor.querySelectorAll('[data-borrador-linea]').length >= 200) {
                return;
            }
            var nueva = referencia.cloneNode(true);
            Array.prototype.forEach.call(nueva.querySelectorAll('input'), function (control) {
                control.value = control.hasAttribute('data-linea-debe') || control.hasAttribute('data-linea-haber')
                    ? '0'
                    : '';
            });
            contenedor.appendChild(nueva);
            nueva.querySelector('[data-linea-cuenta]').focus();
        }

        function quitarLineaBorrador(boton) {
            var bloque = boton.closest('[data-accion-crear-borrador]');
            var lineas = bloque && bloque.querySelectorAll('[data-borrador-linea]');
            if (!lineas || lineas.length <= 2) {
                var estado = bloque && bloque.querySelector('[data-accion-estado]');
                if (estado) {
                    estado.textContent = 'El borrador necesita al menos dos líneas.';
                }
                return;
            }
            boton.closest('[data-borrador-linea]').remove();
        }

        function crearBorrador(boton) {
            var bloque = boton.closest('[data-accion-crear-borrador]');
            var estado = bloque && bloque.querySelector('[data-accion-estado]');
            var nodo = bloque && bloque.querySelector('[data-borrador-nodo]');
            var fecha = bloque && bloque.querySelector('[data-borrador-fecha]');
            var modelo = bloque && bloque.querySelector('[data-borrador-modelo]');
            var filas = bloque && bloque.querySelectorAll('[data-borrador-linea]');
            var lineas = [];
            var totalDebe = 0;
            var totalHaber = 0;
            var errorEntrada = '';
            if (!nodo || !Number.isInteger(Number(nodo.value)) || Number(nodo.value) <= 0 || !fecha.value || !modelo.value.trim()) {
                errorEntrada = 'Completá nodo, fecha contable y modelo.';
            }
            Array.prototype.forEach.call(filas || [], function (fila) {
                var cuenta = Number(fila.querySelector('[data-linea-cuenta]').value);
                var debe = Number(fila.querySelector('[data-linea-debe]').value || 0);
                var haber = Number(fila.querySelector('[data-linea-haber]').value || 0);
                if (!Number.isInteger(cuenta) || cuenta <= 0 || debe < 0 || haber < 0 || (debe > 0) === (haber > 0)) {
                    errorEntrada = 'Cada línea necesita una cuenta y un importe positivo sólo en debe o haber.';
                }
                totalDebe += debe;
                totalHaber += haber;
                lineas.push({
                    cuenta_zetti_id: cuenta,
                    debe: fila.querySelector('[data-linea-debe]').value || '0',
                    haber: fila.querySelector('[data-linea-haber]').value || '0',
                    observacion: fila.querySelector('[data-linea-observacion]').value.trim()
                });
            });
            if (!errorEntrada && Math.abs(totalDebe - totalHaber) > 0.000001) {
                errorEntrada = 'El total del debe debe coincidir con el total del haber.';
            }
            if (errorEntrada) {
                if (estado) {
                    estado.textContent = errorEntrada;
                }
                return;
            }

            var clave = boton.dataset.idempotencyKey || crearClaveIdempotencia();
            boton.dataset.idempotencyKey = clave;
            Array.prototype.forEach.call(bloque.querySelectorAll('input, button'), function (control) {
                control.disabled = true;
            });
            if (estado) {
                estado.textContent = 'Creando borrador balanceado…';
            }
            obtenerCsrf().then(function (token) {
                return fetch(window.contextoApp.app.ruta + '/api.php?accion=movimiento.crear-borrador', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': token,
                        'Idempotency-Key': clave
                    },
                    body: JSON.stringify({
                        movimiento_id: Number(boton.dataset.movimientoId),
                        cuenta_bancaria_id: boton.dataset.cuentaBancariaId,
                        inicio_periodo: boton.dataset.inicioPeriodo,
                        nodo_zetti_id: Number(nodo.value),
                        fecha_contable: fecha.value,
                        modelo: modelo.value.trim(),
                        lineas: lineas
                    })
                });
            }).then(leerRespuesta).then(function (contenido) {
                boton.textContent = 'Borrador creado';
                if (estado) {
                    estado.textContent = 'Borrador #' + contenido.data.borrador_id +
                        ' creado con ' + contenido.data.cantidad_lineas + ' líneas. Actualizando…';
                }
                window.setTimeout(function () {
                    if (formularioBandeja && typeof formularioBandeja.requestSubmit === 'function') {
                        formularioBandeja.requestSubmit();
                    } else if (formularioBandeja) {
                        formularioBandeja.submit();
                    }
                }, 900);
            }).catch(function (error) {
                Array.prototype.forEach.call(bloque.querySelectorAll('input, button'), function (control) {
                    control.disabled = false;
                });
                if (estado) {
                    estado.textContent = error.message;
                }
            });
        }

        raiz.addEventListener('click', function (evento) {
            var agregarLinea = evento.target.closest('[data-agregar-linea]');
            if (agregarLinea) {
                agregarLineaBorrador(agregarLinea);
                return;
            }
            var quitarLinea = evento.target.closest('[data-quitar-linea]');
            if (quitarLinea) {
                quitarLineaBorrador(quitarLinea);
                return;
            }
            var accionCrearBorrador = evento.target.closest('[data-crear-borrador]');
            if (accionCrearBorrador) {
                crearBorrador(accionCrearBorrador);
                return;
            }
            var accionAsociarValor = evento.target.closest('[data-asociar-valor]');
            if (accionAsociarValor) {
                asociarValor(accionAsociarValor);
                return;
            }
            var accionAsociarAsiento = evento.target.closest('[data-asociar-asiento]');
            if (accionAsociarAsiento) {
                asociarAsiento(accionAsociarAsiento);
                return;
            }
            var accionRevertir = evento.target.closest('[data-revertir-preparacion]');
            if (accionRevertir) {
                revertirPreparacion(accionRevertir);
                return;
            }
            var accionPreparar = evento.target.closest('[data-preparar-movimiento]');
            if (accionPreparar) {
                prepararMovimiento(accionPreparar);
                return;
            }
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
                return;
            }
            if (evento.key === 'Tab') {
                var enfocables = panel.querySelectorAll(
                    'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
                );
                if (enfocables.length === 0) {
                    evento.preventDefault();
                    return;
                }
                var primero = enfocables[0];
                var ultimo = enfocables[enfocables.length - 1];
                if (evento.shiftKey && document.activeElement === primero) {
                    evento.preventDefault();
                    ultimo.focus();
                } else if (!evento.shiftKey && document.activeElement === ultimo) {
                    evento.preventDefault();
                    primero.focus();
                }
            }
        });
    }

    function iniciar() {
        var bandejas = document.querySelectorAll('[data-bandeja]');
        Array.prototype.forEach.call(bandejas, function (bandeja) {
            iniciarBarraContexto(bandeja);
            iniciarPaginacion(bandeja);
            iniciarDetalle(bandeja);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', iniciar);
    } else {
        iniciar();
    }
}());
