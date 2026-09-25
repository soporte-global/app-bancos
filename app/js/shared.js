(function () {
    'use strict';

    var formatoImporte = new Intl.NumberFormat('es-AR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });

    function mostrarImporte(valor) {
        var numero = Number(valor);
        return Number.isFinite(numero) ? formatoImporte.format(numero) : String(valor);
    }

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
                { nombre: 'mensajes', prefijo: '' },
                { nombre: 'conciliacion', prefijo: 'Conciliación: ' }
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
        Array.prototype.forEach.call(formulario.querySelectorAll('[name="estado"], [name="responsable_id"], [name="asociacion"], [name="mensajes"], [name="conciliacion"]'), function (control) {
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
            var mismoContexto = ['pag', 'cuenta_bancaria_id', 'inicio_periodo', 'estado', 'responsable_id', 'asociacion', 'mensajes', 'conciliacion', 'limite'].every(function (nombre) {
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

        function prevalidarCierre(boton) {
            var bloque = boton.closest('[data-accion-prevalidar-cierre]');
            var resultado = bloque && bloque.querySelector('[data-preflight-resultado]');
            var cerrar = bloque && bloque.querySelector('[data-cerrar-movimiento]');
            var parametros = new URLSearchParams({
                accion: 'movimiento.prevalidar-cierre',
                movimiento_id: boton.dataset.movimientoId,
                cuenta_bancaria_id: boton.dataset.cuentaBancariaId,
                inicio_periodo: boton.dataset.inicioPeriodo
            });
            boton.disabled = true;
            if (resultado) {
                resultado.textContent = 'Validando condiciones de cierre…';
                resultado.className = 'bandeja-preflight-resultado';
            }
            if (cerrar) {
                cerrar.hidden = true;
            }
            fetch(window.contextoApp.app.ruta + '/api.php?' + parametros.toString(), {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' }
            }).then(leerRespuesta).then(function (contenido) {
                var datos = contenido.data;
                var lineas = [datos.resultado === 'BLOQUEADO'
                    ? 'Cierre bloqueado.'
                    : (datos.resultado === 'LISTO' ? 'Preflight correcto.' : 'Preflight correcto con advertencias.')];
                datos.bloqueos.concat(datos.advertencias).forEach(function (hallazgo) {
                    lineas.push(hallazgo.mensaje);
                });
                if (datos.efectos_previstos.length > 0) {
                    lineas.push('Efectos previstos: ' + datos.efectos_previstos.map(function (efecto) {
                        return efecto.accion;
                    }).join(', ') + '.');
                }
                resultado.textContent = lineas.join(' ');
                resultado.classList.add(datos.listo ? 'es-correcto' : 'es-bloqueado');
                if (cerrar) {
                    cerrar.hidden = !datos.ejecutable_ahora;
                }
            }).catch(function (error) {
                if (resultado) {
                    resultado.textContent = error.message;
                    resultado.classList.add('es-bloqueado');
                }
            }).then(function () {
                boton.disabled = false;
            });
        }

        function prevalidarConciliacionCheque(boton) {
            var bloque = boton.closest('[data-accion-prevalidar-cheque]');
            var resultado = bloque && bloque.querySelector('[data-preflight-cheque-resultado]');
            var conciliar = bloque && bloque.querySelector('[data-conciliar-cheque]');
            var parametros = new URLSearchParams({
                accion: 'cheque.prevalidar-conciliacion',
                movimiento_id: boton.dataset.movimientoId,
                cuenta_bancaria_id: boton.dataset.cuentaBancariaId,
                inicio_periodo: boton.dataset.inicioPeriodo
            });
            boton.disabled = true;
            if (resultado) {
                resultado.textContent = 'Validando cheque asociado…';
                resultado.className = 'bandeja-preflight-resultado';
            }
            if (conciliar) {
                conciliar.hidden = true;
            }
            fetch(window.contextoApp.app.ruta + '/api.php?' + parametros.toString(), {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' }
            }).then(leerRespuesta).then(function (contenido) {
                var datos = contenido.data;
                var titulos = {
                    BLOQUEADO: 'Conciliación bloqueada.',
                    YA_CONCILIADO: 'La conciliación ya está registrada.',
                    NO_REQUIERE_CONCILIACION: 'El cheque no requiere conciliación.',
                    LISTO_CON_ADVERTENCIAS: 'Cheque listo para conciliar.'
                };
                var lineas = [titulos[datos.resultado] || datos.resultado];
                datos.bloqueos.concat(datos.advertencias).forEach(function (hallazgo) {
                    lineas.push(hallazgo.mensaje);
                });
                if (datos.cheque) {
                    lineas.push('Cheque #' + datos.cheque.valor_zetti_id
                        + ', estado ERP ' + datos.cheque.estado_erp
                        + ', diferencia ' + mostrarImporte(datos.cheque.diferencia) + '.');
                }
                if (datos.contexto_contable && datos.contexto_contable.cuenta_banco
                    && datos.contexto_contable.cuenta_valor) {
                    lineas.push('Cuenta banco: ' + datos.contexto_contable.cuenta_banco.codigo
                        + '. Cuenta cheque: ' + datos.contexto_contable.cuenta_valor.codigo + '.');
                }
                if (datos.politica_diferencia) {
                    lineas.push('Tratamiento de diferencia: '
                        + datos.politica_diferencia.tratamiento + '.');
                }
                if (datos.efectos_previstos.length > 0) {
                    lineas.push('Efectos previstos: ' + datos.efectos_previstos.map(function (efecto) {
                        return efecto.accion;
                    }).join(', ') + '.');
                }
                resultado.textContent = lineas.join(' ');
                resultado.classList.add(datos.resultado === 'BLOQUEADO' ? 'es-bloqueado' : 'es-correcto');
                if (conciliar) {
                    conciliar.hidden = !datos.listo_para_conciliar;
                }
            }).catch(function (error) {
                if (resultado) {
                    resultado.textContent = error.message;
                    resultado.classList.add('es-bloqueado');
                }
            }).then(function () {
                boton.disabled = false;
            });
        }

        function conciliarCheque(boton) {
            var bloque = boton.closest('[data-accion-prevalidar-cheque]');
            var resultado = bloque && bloque.querySelector('[data-preflight-cheque-resultado]');
            if (!window.confirm('Se crearán la operación, el valor y el asiento de conciliación en el sandbox. ¿Continuar?')) {
                return;
            }
            var clave = boton.dataset.idempotencyKey || crearClaveIdempotencia();
            boton.dataset.idempotencyKey = clave;
            boton.disabled = true;
            if (resultado) {
                resultado.textContent = 'Conciliando cheque en sandbox…';
            }
            obtenerCsrf().then(function (token) {
                return fetch(window.contextoApp.app.ruta + '/api.php?accion=cheque.conciliar', {
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
            }).then(leerRespuesta).then(function (contenido) {
                boton.textContent = 'Cheque conciliado';
                if (resultado) {
                    resultado.textContent = 'Conciliación #' + contenido.data.conciliacion_id
                        + ' creada en sandbox. El movimiento permanece PARA_CERRAR.';
                    resultado.className = 'bandeja-preflight-resultado es-correcto';
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
                if (resultado) {
                    resultado.textContent = error.message;
                    resultado.classList.add('es-bloqueado');
                }
            });
        }

        function cerrarMovimiento(boton) {
            var bloque = boton.closest('[data-accion-prevalidar-cierre]');
            var resultado = bloque && bloque.querySelector('[data-preflight-resultado]');
            if (!window.confirm('El movimiento quedará CERRADO y no podrá reabrirse. ¿Continuar?')) {
                return;
            }
            var clave = boton.dataset.idempotencyKey || crearClaveIdempotencia();
            boton.dataset.idempotencyKey = clave;
            boton.disabled = true;
            if (resultado) {
                resultado.textContent = 'Cerrando movimiento…';
            }
            obtenerCsrf().then(function (token) {
                return fetch(window.contextoApp.app.ruta + '/api.php?accion=movimiento.cerrar', {
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
            }).then(leerRespuesta).then(function (contenido) {
                boton.textContent = 'Movimiento cerrado';
                if (resultado) {
                    var tipos = (contenido.data.efectos_aplicados || []).map(function (efecto) {
                        return efecto.tipo;
                    });
                    if (tipos.indexOf('VALOR') !== -1 && tipos.indexOf('BORRADOR_ASIENTO') !== -1) {
                        resultado.textContent = 'Movimiento CERRADO, valor liquidado y asiento creado en el sandbox. Actualizando la bandeja…';
                    } else if (tipos.indexOf('BORRADOR_ASIENTO') !== -1) {
                        resultado.textContent = 'Movimiento CERRADO y asiento creado en el sandbox. Actualizando la bandeja…';
                    } else if (tipos.indexOf('VALOR') !== -1) {
                        resultado.textContent = 'Movimiento CERRADO y valor ERP liquidado en el sandbox. Actualizando la bandeja…';
                    } else {
                        resultado.textContent = 'Movimiento CERRADO sin modificar el ERP. Actualizando la bandeja…';
                    }
                    resultado.className = 'bandeja-preflight-resultado es-correcto';
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
                if (resultado) {
                    resultado.textContent = error.message;
                    resultado.className = 'bandeja-preflight-resultado es-bloqueado';
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

        function crearOpcionRecurso(datos, tipo) {
            var boton = document.createElement('button');
            var titulo = document.createElement('strong');
            var detalle = document.createElement('span');
            boton.type = 'button';
            boton.className = 'bandeja-recurso-opcion';
            boton.setAttribute('aria-pressed', 'false');
            if (tipo === 'valor') {
                boton.dataset.seleccionarValor = datos.id;
                titulo.textContent = '#' + datos.id + ' · ' + (datos.subtipo || datos.tipo || 'Valor ERP');
                detalle.textContent = mostrarImporte(datos.monto_principal) + ' · ' + (datos.estado || 'Estado #' + datos.estado_id) +
                    (datos.fecha_emision ? ' · ' + datos.fecha_emision.slice(0, 10) : '') +
                    (datos.codigo_externo ? ' · ' + datos.codigo_externo : '');
            } else if (tipo === 'asiento') {
                boton.dataset.seleccionarAsiento = datos.id;
                titulo.textContent = '#' + datos.id + ' · ' + (datos.nombre || 'Asiento ERP');
                detalle.textContent = mostrarImporte(datos.importe_maximo) + ' · ' + datos.cantidad_lineas + ' líneas' +
                    (datos.fecha ? ' · ' + datos.fecha.slice(0, 10) : '') +
                    (datos.nodo ? ' · ' + datos.nodo : '') +
                    (datos.tiene_usos === true || datos.tiene_usos === 't' ? ' · con usos compartidos' : '');
            } else if (tipo === 'nodo') {
                boton.dataset.seleccionarNodo = datos.id;
                titulo.textContent = (datos.codigo_jerarquico || 'Sin código') + ' · ' + (datos.nombre || 'Nodo ERP');
                detalle.textContent = '#' + datos.id +
                    (datos.nombre_corto ? ' · ' + datos.nombre_corto : '') +
                    (datos.preferido === true || datos.preferido === 't' ? ' · nodo de la cuenta bancaria' : '');
            } else {
                boton.dataset.seleccionarCuenta = datos.id;
                titulo.textContent = (datos.codigo || 'Sin código') + ' · ' + (datos.nombre || 'Cuenta ERP');
                detalle.textContent = '#' + datos.id + ' · ' + (datos.nodo || 'Nodo #' + datos.nodo_id) +
                    (datos.imputable === true || datos.imputable === 't' ? ' · imputable' : ' · no imputable') +
                    (datos.coincide_nodo === true || datos.coincide_nodo === 't' ? ' · mismo nodo' : '');
            }
            boton.appendChild(titulo);
            boton.appendChild(detalle);
            return boton;
        }

        function buscarRecursos(boton, tipo) {
            var esValor = tipo === 'valor';
            var bloque = boton.closest(esValor ? '[data-accion-asociar-valor]' : '[data-accion-asociar-asiento]');
            var accion = bloque && bloque.querySelector(esValor ? '[data-asociar-valor]' : '[data-asociar-asiento]');
            var resultados = bloque && bloque.querySelector(esValor ? '[data-resultados-valores]' : '[data-resultados-asientos]');
            var estado = bloque && bloque.querySelector('[data-accion-estado]');
            var compartido = bloque && bloque.querySelector('[data-asiento-compartido]');
            if (!accion || !resultados) {
                return;
            }
            var parametros = new URLSearchParams({
                accion: esValor ? 'erp.buscar-valores' : 'erp.buscar-asientos',
                movimiento_id: accion.dataset.movimientoId,
                cuenta_bancaria_id: accion.dataset.cuentaBancariaId,
                inicio_periodo: accion.dataset.inicioPeriodo,
                limite: '12'
            });
            if (!esValor) {
                parametros.set('compartido', compartido.checked ? '1' : '0');
            }
            boton.disabled = true;
            resultados.textContent = '';
            if (estado) {
                estado.textContent = esValor ? 'Buscando valores disponibles…' : 'Buscando asientos compatibles…';
            }
            fetch(window.contextoApp.app.ruta + '/api.php?' + parametros.toString(), {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' }
            }).then(leerRespuesta).then(function (contenido) {
                if (!contenido.data.length) {
                    var vacio = document.createElement('p');
                    vacio.className = 'sin-dato';
                    vacio.textContent = esValor
                        ? 'No hay valores contextuales disponibles.'
                        : 'No hay asientos compatibles en la ventana de 45 días.';
                    resultados.appendChild(vacio);
                } else {
                    contenido.data.forEach(function (recurso) {
                        resultados.appendChild(crearOpcionRecurso(recurso, tipo));
                    });
                }
                if (estado) {
                    estado.textContent = contenido.data.length + ' opciones encontradas. Elegí una para completar el ID.';
                }
            }).catch(function (error) {
                if (estado) {
                    estado.textContent = error.message;
                }
            }).finally(function () {
                boton.disabled = false;
            });
        }

        function seleccionarRecurso(boton, tipo) {
            var esValor = tipo === 'valor';
            var bloque = boton.closest(esValor ? '[data-accion-asociar-valor]' : '[data-accion-asociar-asiento]');
            var control = bloque && bloque.querySelector(esValor ? '[data-valor-zetti-id]' : '[data-asiento-zetti-id]');
            var estado = bloque && bloque.querySelector('[data-accion-estado]');
            var atributo = esValor ? 'data-seleccionar-valor' : 'data-seleccionar-asiento';
            if (!control) {
                return;
            }
            Array.prototype.forEach.call(bloque.querySelectorAll('[' + atributo + ']'), function (opcion) {
                opcion.setAttribute('aria-pressed', opcion === boton ? 'true' : 'false');
            });
            control.value = boton.getAttribute(atributo);
            if (estado) {
                estado.textContent = (esValor ? 'Valor #' : 'Asiento #') + control.value + ' seleccionado. Revisá y confirmá la asociación.';
            }
        }

        function buscarRecursoBorrador(boton, tipo) {
            var esNodo = tipo === 'nodo';
            var bloque = boton.closest('[data-accion-crear-borrador]');
            var accion = bloque && bloque.querySelector('[data-crear-borrador]');
            var alcance = esNodo ? bloque.querySelector('[data-selector-nodo]') : boton.closest('[data-borrador-linea]');
            var busqueda = alcance && alcance.querySelector(esNodo ? '[data-borrador-nodo-busqueda]' : '[data-linea-cuenta-busqueda]');
            var resultados = alcance && alcance.querySelector(esNodo ? '[data-resultados-nodos]' : '[data-resultados-cuentas]');
            var nodo = bloque && bloque.querySelector('[data-borrador-nodo]');
            var nodoBusqueda = bloque && bloque.querySelector('[data-borrador-nodo-busqueda]');
            var estado = bloque && bloque.querySelector('[data-accion-estado]');
            var termino = busqueda ? busqueda.value.trim() : '';
            var nodoId = nodo && nodo.value ? nodo.value : (nodoBusqueda ? nodoBusqueda.value.trim() : '');
            if (!esNodo && !/^[1-9][0-9]{0,9}$/.test(nodoId)) {
                estado.textContent = 'Elegí primero el nodo del borrador.';
                nodoBusqueda.focus();
                return;
            }
            if (!esNodo && (termino.length < 2 || termino.length > 80)) {
                estado.textContent = 'Ingresá al menos 2 caracteres para buscar la cuenta.';
                busqueda.focus();
                return;
            }
            var parametros = new URLSearchParams({
                accion: esNodo ? 'erp.buscar-nodos' : 'erp.buscar-cuentas',
                movimiento_id: accion.dataset.movimientoId,
                cuenta_bancaria_id: accion.dataset.cuentaBancariaId,
                inicio_periodo: accion.dataset.inicioPeriodo,
                busqueda: termino,
                limite: '12'
            });
            if (!esNodo) {
                parametros.set('nodo_zetti_id', nodoId);
            }
            boton.disabled = true;
            resultados.textContent = '';
            estado.textContent = esNodo ? 'Buscando nodos ERP…' : 'Buscando cuentas contables…';
            fetch(window.contextoApp.app.ruta + '/api.php?' + parametros.toString(), {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' }
            }).then(leerRespuesta).then(function (contenido) {
                if (!contenido.data.length) {
                    var vacio = document.createElement('p');
                    vacio.className = 'sin-dato';
                    vacio.textContent = esNodo ? 'No se encontraron nodos.' : 'No se encontraron cuentas.';
                    resultados.appendChild(vacio);
                } else {
                    contenido.data.forEach(function (recurso) {
                        resultados.appendChild(crearOpcionRecurso(recurso, tipo));
                    });
                }
                estado.textContent = contenido.data.length + ' opciones encontradas. Elegí una para continuar.';
            }).catch(function (error) {
                estado.textContent = error.message;
            }).finally(function () {
                boton.disabled = false;
            });
        }

        function seleccionarRecursoBorrador(boton, tipo) {
            var esNodo = tipo === 'nodo';
            var bloque = boton.closest('[data-accion-crear-borrador]');
            var alcance = esNodo ? bloque.querySelector('[data-selector-nodo]') : boton.closest('[data-borrador-linea]');
            var oculto = alcance.querySelector(esNodo ? '[data-borrador-nodo]' : '[data-linea-cuenta]');
            var visible = alcance.querySelector(esNodo ? '[data-borrador-nodo-busqueda]' : '[data-linea-cuenta-busqueda]');
            var titulo = boton.querySelector('strong');
            var atributo = esNodo ? 'data-seleccionar-nodo' : 'data-seleccionar-cuenta';
            var estado = bloque.querySelector('[data-accion-estado]');
            oculto.value = boton.getAttribute(atributo);
            visible.value = titulo ? titulo.textContent + ' (#' + oculto.value + ')' : oculto.value;
            Array.prototype.forEach.call(alcance.querySelectorAll('[' + atributo + ']'), function (opcion) {
                opcion.setAttribute('aria-pressed', opcion === boton ? 'true' : 'false');
            });
            if (esNodo) {
                Array.prototype.forEach.call(bloque.querySelectorAll('[data-borrador-linea]'), function (fila) {
                    fila.querySelector('[data-linea-cuenta]').value = '';
                    fila.querySelector('[data-linea-cuenta-busqueda]').value = '';
                    fila.querySelector('[data-resultados-cuentas]').textContent = '';
                });
            }
            estado.textContent = (esNodo ? 'Nodo #' : 'Cuenta #') + oculto.value + ' seleccionado.';
        }

        function ejecutarMensajeria(boton, accion) {
            var bloque = boton.closest('[data-mensajeria-movimiento]');
            var estado = bloque && bloque.querySelector('[data-mensajeria-estado]');
            var cuerpo = bloque && bloque.querySelector('[data-mensaje-cuerpo]');
            var esAgregar = accion === 'movimiento.agregar-mensaje';
            var texto = cuerpo ? cuerpo.value.trim() : '';
            if (esAgregar && (texto.length < 1 || texto.length > 2000)) {
                if (estado) {
                    estado.textContent = 'El mensaje debe contener entre 1 y 2000 caracteres.';
                }
                if (cuerpo) {
                    cuerpo.focus();
                }
                return;
            }
            var clave = boton.dataset.idempotencyKey || crearClaveIdempotencia();
            boton.dataset.idempotencyKey = clave;
            Array.prototype.forEach.call(bloque.querySelectorAll('button, textarea'), function (control) {
                control.disabled = true;
            });
            if (estado) {
                estado.textContent = esAgregar ? 'Publicando mensaje…' : 'Registrando lectura…';
            }
            obtenerCsrf().then(function (token) {
                var entrada = {
                    movimiento_id: Number(boton.dataset.movimientoId),
                    cuenta_bancaria_id: boton.dataset.cuentaBancariaId,
                    inicio_periodo: boton.dataset.inicioPeriodo
                };
                if (esAgregar) {
                    entrada.cuerpo = texto;
                }
                return fetch(window.contextoApp.app.ruta + '/api.php?accion=' + accion, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': token,
                        'Idempotency-Key': clave
                    },
                    body: JSON.stringify(entrada)
                });
            }).then(leerRespuesta).then(function (contenido) {
                if (estado) {
                    estado.textContent = esAgregar
                        ? 'Mensaje publicado. Actualizando conversación…'
                        : contenido.data.mensajes_leidos + ' mensajes marcados como leídos. Actualizando…';
                }
                window.setTimeout(function () {
                    if (formularioBandeja && typeof formularioBandeja.requestSubmit === 'function') {
                        formularioBandeja.requestSubmit();
                    } else if (formularioBandeja) {
                        formularioBandeja.submit();
                    }
                }, 700);
            }).catch(function (error) {
                Array.prototype.forEach.call(bloque.querySelectorAll('button, textarea'), function (control) {
                    control.disabled = false;
                });
                if (estado) {
                    estado.textContent = error.message;
                }
            });
        }

        function asignarResponsable(boton) {
            var bloque = boton.closest('[data-accion-asignar-responsable]');
            var control = bloque && bloque.querySelector('[data-responsable-id]');
            var estado = bloque && bloque.querySelector('[data-accion-estado]');
            var responsableId = control ? Number(control.value) : 0;
            if (!Number.isInteger(responsableId) || responsableId <= 0) {
                if (estado) {
                    estado.textContent = 'Seleccioná un responsable válido.';
                }
                if (control) {
                    control.focus();
                }
                return;
            }
            var clave = boton.dataset.idempotencyKey || crearClaveIdempotencia();
            boton.dataset.idempotencyKey = clave;
            boton.disabled = true;
            control.disabled = true;
            if (estado) {
                estado.textContent = 'Asignando responsable…';
            }
            obtenerCsrf().then(function (token) {
                return fetch(window.contextoApp.app.ruta + '/api.php?accion=movimiento.asignar-responsable', {
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
                        responsable_id: responsableId
                    })
                });
            }).then(leerRespuesta).then(function (contenido) {
                if (estado) {
                    estado.textContent = contenido.data.cambio
                        ? 'Responsable asignado a ' + contenido.data.responsable + '. Actualizando…'
                        : contenido.data.responsable + ' ya era responsable. Actualizando…';
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
                control.disabled = false;
                if (estado) {
                    estado.textContent = error.message;
                }
            });
        }

        function asociarValor(boton) {
            var bloque = boton.closest('[data-accion-asociar-valor]');
            var valorControl = bloque && bloque.querySelector('[data-valor-zetti-id]');
            var estado = bloque && bloque.querySelector('[data-accion-estado]');
            var valorId = valorControl ? valorControl.value.trim() : '';
            if (!/^[1-9][0-9]{0,19}$/.test(valorId)) {
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
                        ' asociado por ' + mostrarImporte(contenido.data.monto_asociado) + '. Actualizando…';
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
            var asientoId = asientoControl ? asientoControl.value.trim() : '';
            if (!/^[1-9][0-9]{0,19}$/.test(asientoId)) {
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
                        ' asociado por ' + mostrarImporte(contenido.data.monto_asociado) +
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
                    ? '0.00'
                    : '';
            });
            Array.prototype.forEach.call(nueva.querySelectorAll('[data-resultados-cuentas]'), function (resultados) {
                resultados.textContent = '';
            });
            contenedor.appendChild(nueva);
            nueva.querySelector('[data-linea-cuenta-busqueda]').focus();
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
            var nodoBusqueda = bloque && bloque.querySelector('[data-borrador-nodo-busqueda]');
            var fecha = bloque && bloque.querySelector('[data-borrador-fecha]');
            var modelo = bloque && bloque.querySelector('[data-borrador-modelo]');
            var filas = bloque && bloque.querySelectorAll('[data-borrador-linea]');
            var lineas = [];
            var totalDebe = 0;
            var totalHaber = 0;
            var errorEntrada = '';
            var nodoId = nodo && nodo.value ? nodo.value : (nodoBusqueda ? nodoBusqueda.value.trim() : '');
            if (!/^[1-9][0-9]{0,9}$/.test(nodoId) || !fecha.value || !modelo.value.trim()) {
                errorEntrada = 'Seleccioná un nodo y completá fecha contable y modelo.';
            }
            Array.prototype.forEach.call(filas || [], function (fila) {
                var cuentaControl = fila.querySelector('[data-linea-cuenta]');
                var cuentaBusqueda = fila.querySelector('[data-linea-cuenta-busqueda]');
                var cuenta = cuentaControl.value || cuentaBusqueda.value.trim();
                var debe = Number(fila.querySelector('[data-linea-debe]').value || 0);
                var haber = Number(fila.querySelector('[data-linea-haber]').value || 0);
                if (!/^[1-9][0-9]{0,19}$/.test(cuenta) || debe < 0 || haber < 0 || (debe > 0) === (haber > 0)) {
                    errorEntrada = 'Cada línea necesita una cuenta seleccionada y un importe positivo sólo en debe o haber.';
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
                        nodo_zetti_id: nodoId,
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
            var asignar = evento.target.closest('[data-asignar-responsable]');
            if (asignar) {
                asignarResponsable(asignar);
                return;
            }
            var agregarMensaje = evento.target.closest('[data-agregar-mensaje]');
            if (agregarMensaje) {
                ejecutarMensajeria(agregarMensaje, 'movimiento.agregar-mensaje');
                return;
            }
            var marcarMensajesLeidos = evento.target.closest('[data-marcar-mensajes-leidos]');
            if (marcarMensajesLeidos) {
                ejecutarMensajeria(marcarMensajesLeidos, 'movimiento.marcar-mensajes-leidos');
                return;
            }
            var seleccionarNodo = evento.target.closest('[data-seleccionar-nodo]');
            if (seleccionarNodo) {
                seleccionarRecursoBorrador(seleccionarNodo, 'nodo');
                return;
            }
            var seleccionarCuenta = evento.target.closest('[data-seleccionar-cuenta]');
            if (seleccionarCuenta) {
                seleccionarRecursoBorrador(seleccionarCuenta, 'cuenta');
                return;
            }
            var buscarNodos = evento.target.closest('[data-buscar-nodos]');
            if (buscarNodos) {
                buscarRecursoBorrador(buscarNodos, 'nodo');
                return;
            }
            var buscarCuentas = evento.target.closest('[data-buscar-cuentas]');
            if (buscarCuentas) {
                buscarRecursoBorrador(buscarCuentas, 'cuenta');
                return;
            }
            var seleccionarValor = evento.target.closest('[data-seleccionar-valor]');
            if (seleccionarValor) {
                seleccionarRecurso(seleccionarValor, 'valor');
                return;
            }
            var seleccionarAsiento = evento.target.closest('[data-seleccionar-asiento]');
            if (seleccionarAsiento) {
                seleccionarRecurso(seleccionarAsiento, 'asiento');
                return;
            }
            var buscarValores = evento.target.closest('[data-buscar-valores]');
            if (buscarValores) {
                buscarRecursos(buscarValores, 'valor');
                return;
            }
            var buscarAsientos = evento.target.closest('[data-buscar-asientos]');
            if (buscarAsientos) {
                buscarRecursos(buscarAsientos, 'asiento');
                return;
            }
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
            var accionPrevalidarCierre = evento.target.closest('[data-prevalidar-cierre]');
            if (accionPrevalidarCierre) {
                prevalidarCierre(accionPrevalidarCierre);
                return;
            }
            var accionPrevalidarCheque = evento.target.closest('[data-prevalidar-cheque]');
            if (accionPrevalidarCheque) {
                prevalidarConciliacionCheque(accionPrevalidarCheque);
                return;
            }
            var accionConciliarCheque = evento.target.closest('[data-conciliar-cheque]');
            if (accionConciliarCheque) {
                conciliarCheque(accionConciliarCheque);
                return;
            }
            var accionCerrarMovimiento = evento.target.closest('[data-cerrar-movimiento]');
            if (accionCerrarMovimiento) {
                cerrarMovimiento(accionCerrarMovimiento);
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

        raiz.addEventListener('input', function (evento) {
            if (evento.target.matches('[data-borrador-nodo-busqueda]')) {
                var bloque = evento.target.closest('[data-accion-crear-borrador]');
                var nodo = bloque && bloque.querySelector('[data-borrador-nodo]');
                if (nodo) {
                    nodo.value = '';
                }
                return;
            }
            if (evento.target.matches('[data-linea-cuenta-busqueda]')) {
                var fila = evento.target.closest('[data-borrador-linea]');
                var cuenta = fila && fila.querySelector('[data-linea-cuenta]');
                if (cuenta) {
                    cuenta.value = '';
                }
            }
        });

        raiz.addEventListener('change', function (evento) {
            if (!evento.target.matches('[data-asiento-compartido]')) {
                return;
            }
            var bloque = evento.target.closest('[data-accion-asociar-asiento]');
            var control = bloque && bloque.querySelector('[data-asiento-zetti-id]');
            var resultados = bloque && bloque.querySelector('[data-resultados-asientos]');
            var estado = bloque && bloque.querySelector('[data-accion-estado]');
            if (control) {
                control.value = '';
            }
            if (resultados) {
                resultados.textContent = '';
            }
            if (estado) {
                estado.textContent = 'La modalidad cambió. Buscá nuevamente un asiento compatible.';
            }
        });

        cerrar.addEventListener('click', cerrarDetalle);
        fondo.addEventListener('click', cerrarDetalle);
        panel.addEventListener('keydown', function (evento) {
            if (evento.key === 'Escape') {
                cerrarDetalle();
                return;
            }
            if (evento.key === 'Tab') {
                var enfocables = Array.prototype.filter.call(panel.querySelectorAll(
                    'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
                ), function (control) {
                    return control.getClientRects().length > 0;
                });
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

    function iniciarImportacion(raiz) {
        var formulario = raiz.querySelector('[data-form-importacion]');
        var cuenta = formulario && formulario.querySelector('[data-importacion-cuenta]');
        var configuracion = formulario && formulario.querySelector('[data-importacion-configuracion]');
        var periodo = formulario && formulario.querySelector('[data-importacion-periodo]');
        var estado = formulario && formulario.querySelector('[data-importacion-estado]');
        var resultado = raiz.querySelector('[data-importacion-resultado]');
        var metricas = raiz.querySelector('[data-importacion-metricas]');
        var errores = raiz.querySelector('[data-importacion-errores]');
        var tabla = raiz.querySelector('[data-importacion-tabla]');
        var confirmacion = raiz.querySelector('[data-importacion-confirmacion]');
        var confirmar = raiz.querySelector('[data-confirmar-importacion]');
        var descargarErrores = raiz.querySelector('[data-descargar-errores]');
        var claveConfirmacion = '';
        var totalErroresReporte = 0;
        if (!formulario || !cuenta || !configuracion || !periodo || !estado || !resultado ||
                !metricas || !errores || !tabla || !confirmacion || !confirmar || !descargarErrores) {
            return;
        }

        function leerJson(respuesta) {
            return respuesta.json().catch(function () {
                throw new Error('El servidor devolvió una respuesta inválida.');
            }).then(function (contenido) {
                if (!respuesta.ok) {
                    throw new Error(contenido.error && contenido.error.mensaje
                        ? contenido.error.mensaje
                        : 'No se pudo completar la solicitud.');
                }
                return contenido;
            });
        }

        function agregarMetrica(nombre, valor) {
            var contenedor = document.createElement('div');
            var termino = document.createElement('dt');
            var dato = document.createElement('dd');
            termino.textContent = nombre;
            dato.textContent = valor;
            contenedor.appendChild(termino);
            contenedor.appendChild(dato);
            metricas.appendChild(contenedor);
        }

        function renderizar(datos) {
            resultado.hidden = false;
            metricas.textContent = '';
            errores.textContent = '';
            tabla.textContent = '';
            agregarMetrica('Filas', datos.total_filas);
            agregarMetrica('Válidas', datos.filas_validas);
            agregarMetrica('Errores', datos.total_errores);
            agregarMetrica('Hash SHA-256', datos.hash_sha256.slice(0, 16) + '…');
            agregarMetrica('Crédito', mostrarImporte(datos.credito_total));
            agregarMetrica('Débito', mostrarImporte(datos.debito_total));
            agregarMetrica('Delimitador', datos.delimitador);
            agregarMetrica('Configuraciones', datos.configuraciones_disponibles);
            agregarMetrica('Clasificación unívoca', datos.clasificacion.univocas);
            agregarMetrica('Clasificación múltiple', datos.clasificacion.multiples);
            agregarMetrica('Sin regla o código', datos.clasificacion.sin_regla + datos.clasificacion.sin_codigo);
            totalErroresReporte = Number(datos.total_errores) || 0;
            descargarErrores.hidden = totalErroresReporte === 0;

            if (datos.errores.length) {
                var tituloErrores = document.createElement('h3');
                var lista = document.createElement('ol');
                tituloErrores.textContent = 'Errores encontrados';
                datos.errores.forEach(function (error) {
                    var item = document.createElement('li');
                    item.textContent = 'Fila ' + error.fila + ': ' + error.mensaje;
                    lista.appendChild(item);
                });
                errores.appendChild(tituloErrores);
                errores.appendChild(lista);
            }

            if (datos.previsualizacion.length) {
                var grilla = document.createElement('table');
                grilla.className = 'bandeja-lineas';
                var cabecera = document.createElement('thead');
                var filaCabecera = document.createElement('tr');
                ['Fila', 'Fecha', 'Referencia', 'Descripción', 'Crédito', 'Débito', 'Clasificación'].forEach(function (nombre) {
                    var th = document.createElement('th');
                    th.scope = 'col';
                    th.textContent = nombre;
                    filaCabecera.appendChild(th);
                });
                cabecera.appendChild(filaCabecera);
                grilla.appendChild(cabecera);
                var cuerpo = document.createElement('tbody');
                datos.previsualizacion.forEach(function (fila) {
                    var tr = document.createElement('tr');
                    var clasificacionFila = fila.clasificacion;
                    if (fila.clasificacion === 'UNIVOCA') {
                        clasificacionFila = 'UNÍVOCA · subtipo ' + fila.subtipo_valor_zetti_id;
                    } else if (fila.clasificacion === 'MULTIPLE') {
                        clasificacionFila = 'MÚLTIPLE · ' + fila.subtipos_candidatos.join(', ');
                    } else if (fila.clasificacion === 'SIN_REGLA') {
                        clasificacionFila = 'SIN REGLA';
                    } else if (fila.clasificacion === 'SIN_CODIGO') {
                        clasificacionFila = 'SIN CÓDIGO';
                    }
                    [fila.numero_fila_origen, fila.fecha_operacion, fila.referencia || '', fila.descripcion,
                        mostrarImporte(fila.credito), mostrarImporte(fila.debito), clasificacionFila].forEach(function (valor) {
                        var td = document.createElement('td');
                        td.textContent = valor;
                        tr.appendChild(td);
                    });
                    cuerpo.appendChild(tr);
                });
                grilla.appendChild(cuerpo);
                tabla.appendChild(grilla);
            }

            if (!datos.valido) {
                confirmacion.textContent = 'Corregí los errores y analizá nuevamente. No se guardó ningún dato.';
                confirmar.hidden = true;
            } else {
                confirmacion.textContent = 'Archivo válido. Confirmá para crear el lote y sus movimientos en estado ABIERTO.';
                confirmar.hidden = false;
            }
        }

        function nuevaClaveIdempotencia() {
            if (window.crypto && typeof window.crypto.randomUUID === 'function') {
                return 'importacion-' + window.crypto.randomUUID();
            }
            return 'importacion-' + Date.now() + '-' + Math.random().toString(16).slice(2);
        }

        function actualizarConfiguraciones() {
            var cuentaId = cuenta.value;
            var opciones = configuracion.querySelectorAll('option[data-cuenta-id]');
            configuracion.value = '';
            Array.prototype.forEach.call(opciones, function (opcion) {
                var disponible = cuentaId !== '' && opcion.getAttribute('data-cuenta-id') === cuentaId;
                opcion.hidden = !disponible;
                opcion.disabled = !disponible;
            });
            configuracion.disabled = cuentaId === '';
        }

        function invalidarPrevisualizacion() {
            claveConfirmacion = '';
            totalErroresReporte = 0;
            confirmar.hidden = true;
            descargarErrores.hidden = true;
            resultado.hidden = true;
        }

        function nombreDescarga(respuesta) {
            var disposicion = respuesta.headers.get('Content-Disposition') || '';
            var coincidencia = disposicion.match(/filename="?([^";]+)"?/i);
            return coincidencia ? coincidencia[1] : 'errores-importacion.csv';
        }

        function descargarReporteErrores() {
            if (!formulario.reportValidity()) {
                return;
            }
            var datosFormulario = new FormData(formulario);
            datosFormulario.set('inicio_periodo', periodo.value + '-01');
            descargarErrores.disabled = true;
            estado.textContent = 'Generando el reporte completo de errores…';
            fetch(window.contextoApp.app.ruta + '/api.php?accion=csrf', {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' }
            }).then(leerJson).then(function (csrf) {
                return fetch(window.contextoApp.app.ruta + '/api.php?accion=importacion.reporte-errores', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'text/csv, application/json',
                        'X-CSRF-Token': csrf.data.csrf_token
                    },
                    body: datosFormulario
                });
            }).then(function (respuesta) {
                if (!respuesta.ok) {
                    return leerJson(respuesta);
                }
                var nombre = nombreDescarga(respuesta);
                return respuesta.blob().then(function (archivo) {
                    var url = URL.createObjectURL(archivo);
                    var enlace = document.createElement('a');
                    enlace.href = url;
                    enlace.download = nombre;
                    document.body.appendChild(enlace);
                    enlace.click();
                    enlace.remove();
                    URL.revokeObjectURL(url);
                    estado.textContent = 'Reporte descargado con ' + totalErroresReporte + ' errores.';
                });
            }).catch(function (error) {
                estado.textContent = error.message;
            }).finally(function () {
                descargarErrores.disabled = false;
            });
        }

        function enviar(accion, esConfirmacion) {
            if (!formulario.reportValidity()) {
                return;
            }
            var datosFormulario = new FormData(formulario);
            datosFormulario.set('inicio_periodo', periodo.value + '-01');
            var controles = formulario.querySelectorAll('input, select, button');
            Array.prototype.forEach.call(controles, function (control) { control.disabled = true; });
            confirmar.disabled = true;
            estado.textContent = esConfirmacion
                ? 'Confirmando la importación…'
                : 'Analizando el archivo completo…';
            if (!esConfirmacion) {
                claveConfirmacion = '';
                confirmar.hidden = true;
                descargarErrores.hidden = true;
                resultado.hidden = true;
            }
            fetch(window.contextoApp.app.ruta + '/api.php?accion=csrf', {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' }
            }).then(leerJson).then(function (csrf) {
                var cabeceras = {
                    'Accept': 'application/json',
                    'X-CSRF-Token': csrf.data.csrf_token
                };
                if (esConfirmacion) {
                    cabeceras['Idempotency-Key'] = claveConfirmacion;
                }
                return fetch(window.contextoApp.app.ruta + '/api.php?accion=' + accion, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: cabeceras,
                    body: datosFormulario
                });
            }).then(leerJson).then(function (contenido) {
                if (esConfirmacion) {
                    confirmar.hidden = true;
                    confirmacion.textContent = 'Importación #' + contenido.data.importacion_id +
                        ' creada con ' + contenido.data.total_movimientos + ' movimientos en estado ABIERTO. ' +
                        contenido.data.clasificacion.univocas + ' quedaron clasificados de forma unívoca y ' +
                        contenido.data.asignados_automaticamente + ' recibieron responsable automático.';
                    estado.textContent = contenido.meta.repetida
                        ? 'La solicitud ya estaba confirmada; se recuperó su resultado.'
                        : 'Importación confirmada correctamente.';
                } else {
                    renderizar(contenido.data);
                    claveConfirmacion = contenido.data.valido ? nuevaClaveIdempotencia() : '';
                    estado.textContent = contenido.data.valido
                        ? 'Archivo válido. Revisá el resumen antes de confirmar.'
                        : 'El archivo contiene errores de validación.';
                }
            }).catch(function (error) {
                estado.textContent = error.message;
            }).finally(function () {
                Array.prototype.forEach.call(controles, function (control) { control.disabled = false; });
                actualizarConfiguracionesSinLimpiar();
                confirmar.disabled = false;
            });

            function actualizarConfiguracionesSinLimpiar() {
                var cuentaId = cuenta.value;
                var seleccion = configuracion.value;
                var opciones = configuracion.querySelectorAll('option[data-cuenta-id]');
                Array.prototype.forEach.call(opciones, function (opcion) {
                    var disponible = cuentaId !== '' && opcion.getAttribute('data-cuenta-id') === cuentaId;
                    opcion.hidden = !disponible;
                    opcion.disabled = !disponible;
                });
                configuracion.disabled = cuentaId === '';
                configuracion.value = seleccion;
            }
        }

        cuenta.addEventListener('change', function () {
            actualizarConfiguraciones();
            invalidarPrevisualizacion();
        });
        configuracion.addEventListener('change', invalidarPrevisualizacion);
        periodo.addEventListener('change', invalidarPrevisualizacion);
        formulario.querySelector('[name="archivo"]').addEventListener('change', invalidarPrevisualizacion);
        formulario.addEventListener('submit', function (evento) {
            evento.preventDefault();
            enviar('importacion.previsualizar', false);
        });
        confirmar.addEventListener('click', function () {
            if (claveConfirmacion === '') {
                estado.textContent = 'Analizá nuevamente el archivo antes de confirmar.';
                return;
            }
            enviar('importacion.confirmar', true);
        });
        descargarErrores.addEventListener('click', descargarReporteErrores);
        actualizarConfiguraciones();
    }

    function iniciarConfiguracion(raiz) {
        var detalle = raiz.querySelector('[data-configuracion-id]');
        var estado = raiz.querySelector('[data-configuracion-estado]');
        var totalAutomaticas = raiz.querySelector('[data-total-automaticas]');
        var totalReglas = raiz.querySelector('[data-total-reglas]');
        var editor = raiz.querySelector('[data-editor-regla]');
        var guardar = editor && editor.querySelector('[data-guardar-regla]');
        var cancelar = editor && editor.querySelector('[data-cancelar-regla]');
        var editorVinculo = raiz.querySelector('[data-editor-vinculo]');
        var totalCuentas = raiz.querySelector('[data-total-cuentas]');
        var guardarVinculo = editorVinculo && editorVinculo.querySelector('button[type="submit"]');
        var editorMapeo = raiz.querySelector('[data-editor-mapeo]');
        var totalMapeos = raiz.querySelector('[data-total-mapeos]');
        var guardarMapeo = editorMapeo && editorMapeo.querySelector('[data-guardar-mapeo]');
        var cancelarMapeo = editorMapeo && editorMapeo.querySelector('[data-cancelar-mapeo]');
        var editorAsignacion = raiz.querySelector('[data-editor-asignacion]');
        var totalAsignaciones = raiz.querySelector('[data-total-asignaciones]');
        var guardarAsignacion = editorAsignacion && editorAsignacion.querySelector('[data-guardar-asignacion]');
        var cancelarAsignacion = editorAsignacion && editorAsignacion.querySelector('[data-cancelar-asignacion]');
        if (!detalle || !estado || !totalAutomaticas || !totalReglas || !editor || !guardar || !cancelar ||
            !editorVinculo || !totalCuentas || !guardarVinculo || !editorMapeo || !totalMapeos ||
            !guardarMapeo || !cancelarMapeo || !editorAsignacion || !totalAsignaciones ||
            !guardarAsignacion || !cancelarAsignacion) {
            return;
        }

        function leerJson(respuesta) {
            return respuesta.json().catch(function () {
                throw new Error('El servidor devolvió una respuesta inválida.');
            }).then(function (contenido) {
                if (!respuesta.ok) {
                    throw new Error(contenido.error && contenido.error.mensaje
                        ? contenido.error.mensaje
                        : 'No se pudo completar la solicitud.');
                }
                return contenido;
            });
        }

        function claveIdempotencia() {
            if (window.crypto && typeof window.crypto.randomUUID === 'function') {
                return 'configuracion-' + window.crypto.randomUUID();
            }
            return 'configuracion-' + Date.now() + '-' + Math.random().toString(16).slice(2);
        }

        function enviarComando(accion, cuerpo) {
            return fetch(window.contextoApp.app.ruta + '/api.php?accion=csrf', {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' }
            }).then(leerJson).then(function (csrf) {
                return fetch(window.contextoApp.app.ruta + '/api.php?accion=' + accion, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': csrf.data.csrf_token,
                        'Idempotency-Key': claveIdempotencia()
                    },
                    body: JSON.stringify(cuerpo)
                });
            }).then(leerJson);
        }

        function prepararEditor(fila, modo) {
            editor.reset();
            editor.dataset.modo = modo;
            editor.elements.regla_id.value = fila ? fila.getAttribute('data-regla-id') : '';
            editor.elements.subtipo_valor_zetti_id.value = fila ? fila.getAttribute('data-subtipo-id') : '';
            editor.elements.sentido.value = fila ? fila.getAttribute('data-sentido') : 'C';
            editor.elements.codigo_extracto.value = fila ? fila.getAttribute('data-codigo') : '';
            editor.elements.validar_automaticamente.checked = fila
                ? fila.getAttribute('data-validar-automaticamente') === '1'
                : false;
            var retirar = modo === 'retirar';
            ['subtipo_valor_zetti_id', 'sentido', 'codigo_extracto', 'validar_automaticamente'].forEach(function (nombre) {
                editor.elements[nombre].disabled = retirar;
            });
            guardar.textContent = retirar ? 'Confirmar retiro' : (fila ? 'Guardar nueva versión' : 'Crear regla');
            editor.elements.motivo.value = '';
            editor.elements.motivo.focus();
        }

        function buscarFila(reglaId) {
            return detalle.querySelector('[data-regla-id="' + reglaId + '"]');
        }

        function botonRegla(texto, clase, atributo) {
            var boton = document.createElement('button');
            boton.type = 'button';
            boton.className = clase;
            boton.setAttribute(atributo, '');
            boton.textContent = texto;
            return boton;
        }

        function aplicarReglaGuardada(datos) {
            var fila = datos.regla_id_anterior ? buscarFila(datos.regla_id_anterior) : null;
            var eraAutomatica = fila ? fila.getAttribute('data-validar-automaticamente') === '1' : false;
            if (!fila) {
                fila = document.createElement('tr');
                ['codigo', 'sentido', 'subtipo', 'version', 'estado', 'acciones'].forEach(function (campo) {
                    var celda = document.createElement('td');
                    celda.setAttribute('data-' + campo + '-regla', '');
                    fila.appendChild(celda);
                });
                var acciones = fila.querySelector('[data-acciones-regla]');
                acciones.className = 'configuracion-acciones-regla';
                acciones.appendChild(botonRegla('Editar', 'configuracion-secundario', 'data-editar-regla'));
                acciones.appendChild(botonRegla('', 'configuracion-alternar', 'data-alternar-validacion'));
                acciones.appendChild(botonRegla('Retirar', 'configuracion-peligro', 'data-retirar-regla'));
                detalle.querySelector('.configuracion-reglas tbody').appendChild(fila);
                totalReglas.textContent = String((Number(totalReglas.textContent) || 0) + 1);
            }
            var automatica = datos.validar_automaticamente === true;
            fila.setAttribute('data-regla-id', datos.regla_id);
            fila.setAttribute('data-subtipo-id', datos.subtipo_valor_zetti_id);
            fila.setAttribute('data-sentido', datos.sentido);
            fila.setAttribute('data-codigo', datos.codigo_extracto || '');
            fila.setAttribute('data-validar-automaticamente', automatica ? '1' : '0');
            fila.querySelector('[data-codigo-regla]').textContent = datos.codigo_extracto || 'Sin código';
            fila.querySelector('[data-sentido-regla]').textContent = datos.sentido;
            fila.querySelector('[data-subtipo-regla]').textContent = editor.elements.subtipo_valor_zetti_id.selectedOptions[0].textContent;
            fila.querySelector('[data-version-regla]').textContent = datos.version;
            fila.querySelector('[data-estado-regla]').textContent = automatica ? 'Habilitada' : 'Deshabilitada';
            fila.querySelector('[data-alternar-validacion]').textContent = automatica ? 'Deshabilitar' : 'Habilitar';
            if (eraAutomatica !== automatica) {
                var total = Number(totalAutomaticas.textContent) || 0;
                totalAutomaticas.textContent = String(Math.max(0, total + (automatica ? 1 : -1)));
            }
        }

        function aplicarRetiro(datos) {
            var fila = buscarFila(datos.regla_id);
            if (!fila) {
                return;
            }
            if (fila.getAttribute('data-validar-automaticamente') === '1') {
                totalAutomaticas.textContent = String(Math.max(0, (Number(totalAutomaticas.textContent) || 0) - 1));
            }
            totalReglas.textContent = String(Math.max(0, (Number(totalReglas.textContent) || 0) - 1));
            fila.remove();
        }

        function buscarVinculo(cuentaId) {
            return detalle.querySelector('[data-cuenta-vinculada-id="' + cuentaId + '"]');
        }

        function aplicarVinculo(datos) {
            var fila = buscarVinculo(datos.cuenta_bancaria_id);
            if (datos.activa) {
                if (!fila) {
                    fila = document.createElement('tr');
                    fila.setAttribute('data-cuenta-vinculada-id', datos.cuenta_bancaria_id);
                    var etiqueta = document.createElement('td');
                    etiqueta.setAttribute('data-etiqueta-cuenta', '');
                    var acciones = document.createElement('td');
                    acciones.className = 'configuracion-acciones-regla';
                    acciones.appendChild(botonRegla('Desvincular', 'configuracion-peligro', 'data-desvincular-cuenta'));
                    fila.appendChild(etiqueta);
                    fila.appendChild(acciones);
                    detalle.querySelector('.configuracion-vinculos tbody').appendChild(fila);
                    totalCuentas.textContent = String((Number(totalCuentas.textContent) || 0) + 1);
                }
                fila.querySelector('[data-etiqueta-cuenta]').textContent = datos.etiqueta;
            } else if (fila) {
                fila.remove();
                totalCuentas.textContent = String(Math.max(0, (Number(totalCuentas.textContent) || 0) - 1));
            }
        }

        function limpiarEditorVinculo() {
            editorVinculo.reset();
            editorVinculo.dataset.modo = 'vincular';
            guardarVinculo.textContent = 'Vincular cuenta';
        }

        function prepararEditorMapeo(fila, modo) {
            editorMapeo.reset();
            editorMapeo.dataset.modo = modo;
            editorMapeo.elements.mapeo_id.value = fila ? fila.getAttribute('data-mapeo-id') : '';
            editorMapeo.elements.subtipo_valor_zetti_id.value = fila ? fila.getAttribute('data-subtipo-mapeo-id') : '';
            editorMapeo.elements.cuenta_zetti_id.value = fila ? fila.getAttribute('data-cuenta-mapeo-id') : '';
            var retirar = modo === 'retirar';
            editorMapeo.elements.subtipo_valor_zetti_id.disabled = retirar;
            editorMapeo.elements.cuenta_zetti_id.disabled = retirar;
            guardarMapeo.textContent = retirar ? 'Confirmar retiro' : (fila ? 'Guardar nueva versión' : 'Crear mapeo');
            editorMapeo.elements.motivo.value = '';
            editorMapeo.elements.motivo.focus();
        }

        function buscarMapeo(mapeoId) {
            return detalle.querySelector('[data-mapeo-id="' + mapeoId + '"]');
        }

        function aplicarMapeo(datos) {
            var fila = datos.mapeo_id_anterior ? buscarMapeo(datos.mapeo_id_anterior) : null;
            if (!fila) {
                fila = document.createElement('tr');
                ['subtipo', 'cuenta', 'version', 'acciones'].forEach(function (campo) {
                    var celda = document.createElement('td');
                    celda.setAttribute('data-' + campo + '-mapeo', '');
                    fila.appendChild(celda);
                });
                var acciones = fila.querySelector('[data-acciones-mapeo]');
                acciones.className = 'configuracion-acciones-regla';
                acciones.appendChild(botonRegla('Editar', 'configuracion-secundario', 'data-editar-mapeo'));
                acciones.appendChild(botonRegla('Retirar', 'configuracion-peligro', 'data-retirar-mapeo'));
                detalle.querySelector('.configuracion-mapeos-tabla tbody').appendChild(fila);
                totalMapeos.textContent = String((Number(totalMapeos.textContent) || 0) + 1);
            }
            fila.setAttribute('data-mapeo-id', datos.mapeo_id);
            fila.setAttribute('data-subtipo-mapeo-id', datos.subtipo_valor_zetti_id);
            fila.setAttribute('data-cuenta-mapeo-id', datos.cuenta_zetti_id);
            fila.querySelector('[data-subtipo-mapeo]').textContent = editorMapeo.elements.subtipo_valor_zetti_id.selectedOptions[0].textContent;
            fila.querySelector('[data-cuenta-mapeo]').textContent = editorMapeo.elements.cuenta_zetti_id.selectedOptions[0].textContent;
            fila.querySelector('[data-version-mapeo]').textContent = datos.version;
        }

        function aplicarRetiroMapeo(datos) {
            var fila = buscarMapeo(datos.mapeo_id);
            if (fila) {
                fila.remove();
                totalMapeos.textContent = String(Math.max(0, (Number(totalMapeos.textContent) || 0) - 1));
            }
        }

        function prepararEditorAsignacion(fila, modo) {
            editorAsignacion.reset();
            editorAsignacion.dataset.modo = modo;
            editorAsignacion.elements.regla_asignacion_id.value = fila ? fila.getAttribute('data-asignacion-id') : '';
            editorAsignacion.elements.subtipo_valor_zetti_id.value = fila ? fila.getAttribute('data-subtipo-asignacion-id') : '';
            editorAsignacion.elements.usuario_id.value = fila ? fila.getAttribute('data-usuario-asignacion-id') : '';
            var retirar = modo === 'retirar';
            editorAsignacion.elements.subtipo_valor_zetti_id.disabled = retirar;
            editorAsignacion.elements.usuario_id.disabled = retirar;
            guardarAsignacion.textContent = retirar ? 'Confirmar retiro' : (fila ? 'Guardar nueva versión' : 'Crear regla');
            editorAsignacion.elements.motivo.value = '';
            editorAsignacion.elements.motivo.focus();
        }

        function buscarAsignacion(id) {
            return detalle.querySelector('[data-asignacion-id="' + id + '"]');
        }

        function aplicarAsignacion(datos) {
            var fila = datos.regla_asignacion_id_anterior ? buscarAsignacion(datos.regla_asignacion_id_anterior) : null;
            if (!fila) {
                fila = document.createElement('tr');
                ['subtipo', 'usuario', 'version', 'acciones'].forEach(function (campo) {
                    var celda = document.createElement('td');
                    celda.setAttribute('data-' + campo + '-asignacion', '');
                    fila.appendChild(celda);
                });
                var acciones = fila.querySelector('[data-acciones-asignacion]');
                acciones.className = 'configuracion-acciones-regla';
                acciones.appendChild(botonRegla('Editar', 'configuracion-secundario', 'data-editar-asignacion'));
                acciones.appendChild(botonRegla('Retirar', 'configuracion-peligro', 'data-retirar-asignacion'));
                detalle.querySelector('.configuracion-asignaciones-tabla tbody').appendChild(fila);
                totalAsignaciones.textContent = String((Number(totalAsignaciones.textContent) || 0) + 1);
            }
            fila.setAttribute('data-asignacion-id', datos.regla_asignacion_id);
            fila.setAttribute('data-subtipo-asignacion-id', datos.subtipo_valor_zetti_id);
            fila.setAttribute('data-usuario-asignacion-id', datos.usuario_id);
            fila.querySelector('[data-subtipo-asignacion]').textContent = editorAsignacion.elements.subtipo_valor_zetti_id.selectedOptions[0].textContent;
            fila.querySelector('[data-usuario-asignacion]').textContent = editorAsignacion.elements.usuario_id.selectedOptions[0].textContent;
            fila.querySelector('[data-version-asignacion]').textContent = datos.version;
        }

        function aplicarRetiroAsignacion(datos) {
            var fila = buscarAsignacion(datos.regla_asignacion_id);
            if (fila) {
                fila.remove();
                totalAsignaciones.textContent = String(Math.max(0, (Number(totalAsignaciones.textContent) || 0) - 1));
            }
        }

        raiz.addEventListener('click', function (evento) {
            var editarAsignacion = evento.target.closest('[data-editar-asignacion]');
            var retirarAsignacion = evento.target.closest('[data-retirar-asignacion]');
            if ((editarAsignacion || retirarAsignacion) && raiz.contains(editarAsignacion || retirarAsignacion)) {
                var filaAsignacion = (editarAsignacion || retirarAsignacion).closest('[data-asignacion-id]');
                if (filaAsignacion) {
                    prepararEditorAsignacion(filaAsignacion, retirarAsignacion ? 'retirar' : 'guardar');
                }
                return;
            }
            var editarMapeo = evento.target.closest('[data-editar-mapeo]');
            var retirarMapeo = evento.target.closest('[data-retirar-mapeo]');
            if ((editarMapeo || retirarMapeo) && raiz.contains(editarMapeo || retirarMapeo)) {
                var filaMapeo = (editarMapeo || retirarMapeo).closest('[data-mapeo-id]');
                if (filaMapeo) {
                    prepararEditorMapeo(filaMapeo, retirarMapeo ? 'retirar' : 'guardar');
                }
                return;
            }
            var desvincularCuenta = evento.target.closest('[data-desvincular-cuenta]');
            if (desvincularCuenta && raiz.contains(desvincularCuenta)) {
                var filaCuenta = desvincularCuenta.closest('[data-cuenta-vinculada-id]');
                if (filaCuenta) {
                    editorVinculo.dataset.modo = 'desvincular';
                    editorVinculo.elements.cuenta_bancaria_id.value = filaCuenta.getAttribute('data-cuenta-vinculada-id');
                    editorVinculo.elements.motivo.value = '';
                    guardarVinculo.textContent = 'Confirmar desvinculación';
                    editorVinculo.elements.motivo.focus();
                }
                return;
            }
            var editar = evento.target.closest('[data-editar-regla]');
            var retirar = evento.target.closest('[data-retirar-regla]');
            var boton = evento.target.closest('[data-alternar-validacion]');
            var objetivo = editar || retirar || boton;
            if (!objetivo || !raiz.contains(objetivo)) {
                return;
            }
            var fila = objetivo.closest('[data-regla-id]');
            if (!fila) {
                return;
            }
            if (editar) {
                prepararEditor(fila, 'guardar');
                return;
            }
            if (retirar) {
                prepararEditor(fila, 'retirar');
                return;
            }
            var anterior = fila.getAttribute('data-validar-automaticamente') === '1';
            var solicitado = !anterior;
            boton.disabled = true;
            estado.textContent = solicitado
                ? 'Habilitando la validación automática…'
                : 'Deshabilitando la validación automática…';
            enviarComando('configuracion.actualizar-validacion-automatica', {
                configuracion_id: detalle.getAttribute('data-configuracion-id'),
                regla_id: fila.getAttribute('data-regla-id'),
                validar_automaticamente: solicitado
            }).then(function (contenido) {
                var actual = contenido.data.validar_automaticamente === true;
                var etiqueta = fila.querySelector('[data-estado-regla]');
                fila.setAttribute('data-regla-id', contenido.data.regla_id);
                fila.setAttribute('data-validar-automaticamente', actual ? '1' : '0');
                fila.querySelector('[data-version-regla]').textContent = contenido.data.version;
                etiqueta.textContent = actual ? 'Habilitada' : 'Deshabilitada';
                boton.textContent = actual ? 'Deshabilitar' : 'Habilitar';
                if (contenido.data.cambio) {
                    var total = Number(totalAutomaticas.textContent) || 0;
                    totalAutomaticas.textContent = String(Math.max(0, total + (actual ? 1 : -1)));
                }
                estado.textContent = contenido.meta.repetida
                    ? 'El cambio ya estaba registrado; se recuperó su resultado.'
                    : 'Regla actualizada correctamente.';
            }).catch(function (error) {
                estado.textContent = error.message;
            }).finally(function () {
                boton.disabled = false;
            });
        });

        cancelar.addEventListener('click', function () {
            prepararEditor(null, 'guardar');
        });

        editorVinculo.elements.cuenta_bancaria_id.addEventListener('change', function () {
            editorVinculo.dataset.modo = 'vincular';
            guardarVinculo.textContent = 'Vincular cuenta';
        });

        editorVinculo.addEventListener('submit', function (evento) {
            evento.preventDefault();
            if (!editorVinculo.reportValidity()) {
                return;
            }
            var modo = editorVinculo.dataset.modo || 'vincular';
            var accion = modo === 'desvincular'
                ? 'configuracion.desvincular-cuenta'
                : 'configuracion.vincular-cuenta';
            var cuerpo = {
                configuracion_id: detalle.getAttribute('data-configuracion-id'),
                cuenta_bancaria_id: editorVinculo.elements.cuenta_bancaria_id.value,
                motivo: editorVinculo.elements.motivo.value
            };
            guardarVinculo.disabled = true;
            estado.textContent = modo === 'desvincular'
                ? 'Desvinculando la cuenta bancaria…'
                : 'Vinculando la cuenta bancaria…';
            enviarComando(accion, cuerpo).then(function (contenido) {
                aplicarVinculo(contenido.data);
                estado.textContent = contenido.meta.repetida
                    ? 'La solicitud ya estaba registrada; se recuperó su resultado.'
                    : (modo === 'desvincular' ? 'Cuenta desvinculada correctamente.' : 'Cuenta vinculada correctamente.');
                limpiarEditorVinculo();
            }).catch(function (error) {
                estado.textContent = error.message;
            }).finally(function () {
                guardarVinculo.disabled = false;
            });
        });

        cancelarMapeo.addEventListener('click', function () {
            prepararEditorMapeo(null, 'guardar');
        });

        editorMapeo.addEventListener('submit', function (evento) {
            evento.preventDefault();
            if (!editorMapeo.reportValidity()) {
                return;
            }
            var modo = editorMapeo.dataset.modo || 'guardar';
            var cuerpo = {
                configuracion_id: detalle.getAttribute('data-configuracion-id'),
                mapeo_id: editorMapeo.elements.mapeo_id.value || null,
                motivo: editorMapeo.elements.motivo.value
            };
            var accion = 'configuracion.guardar-mapeo';
            if (modo === 'retirar') {
                accion = 'configuracion.retirar-mapeo';
                estado.textContent = 'Retirando el mapeo contable…';
            } else {
                cuerpo.subtipo_valor_zetti_id = editorMapeo.elements.subtipo_valor_zetti_id.value;
                cuerpo.cuenta_zetti_id = editorMapeo.elements.cuenta_zetti_id.value;
                estado.textContent = cuerpo.mapeo_id ? 'Creando una nueva versión del mapeo…' : 'Creando el mapeo contable…';
            }
            guardarMapeo.disabled = true;
            cancelarMapeo.disabled = true;
            enviarComando(accion, cuerpo).then(function (contenido) {
                if (modo === 'retirar') {
                    aplicarRetiroMapeo(contenido.data);
                    estado.textContent = 'Mapeo contable retirado correctamente.';
                } else {
                    aplicarMapeo(contenido.data);
                    estado.textContent = contenido.data.cambio ? 'Mapeo contable guardado correctamente.' : 'El mapeo no tenía cambios.';
                }
                prepararEditorMapeo(null, 'guardar');
            }).catch(function (error) {
                estado.textContent = error.message;
            }).finally(function () {
                guardarMapeo.disabled = false;
                cancelarMapeo.disabled = false;
            });
        });

        cancelarAsignacion.addEventListener('click', function () {
            prepararEditorAsignacion(null, 'guardar');
        });

        editorAsignacion.addEventListener('submit', function (evento) {
            evento.preventDefault();
            if (!editorAsignacion.reportValidity()) { return; }
            var modo = editorAsignacion.dataset.modo || 'guardar';
            var cuerpo = { configuracion_id: detalle.getAttribute('data-configuracion-id'), regla_asignacion_id: editorAsignacion.elements.regla_asignacion_id.value || null, motivo: editorAsignacion.elements.motivo.value };
            var accion = 'configuracion.guardar-asignacion';
            if (modo === 'retirar') {
                accion = 'configuracion.retirar-asignacion';
                estado.textContent = 'Retirando la regla de responsable…';
            } else {
                cuerpo.subtipo_valor_zetti_id = editorAsignacion.elements.subtipo_valor_zetti_id.value;
                cuerpo.usuario_id = editorAsignacion.elements.usuario_id.value;
                estado.textContent = cuerpo.regla_asignacion_id ? 'Creando una nueva versión de la asignación…' : 'Creando la regla de responsable…';
            }
            guardarAsignacion.disabled = true; cancelarAsignacion.disabled = true;
            enviarComando(accion, cuerpo).then(function (contenido) {
                if (modo === 'retirar') { aplicarRetiroAsignacion(contenido.data); estado.textContent = 'Regla de responsable retirada correctamente.'; }
                else { aplicarAsignacion(contenido.data); estado.textContent = contenido.data.cambio ? 'Regla de responsable guardada correctamente.' : 'La regla no tenía cambios.'; }
                prepararEditorAsignacion(null, 'guardar');
            }).catch(function (error) { estado.textContent = error.message; }).finally(function () { guardarAsignacion.disabled = false; cancelarAsignacion.disabled = false; });
        });

        editor.addEventListener('submit', function (evento) {
            evento.preventDefault();
            if (!editor.reportValidity()) {
                return;
            }
            var modo = editor.dataset.modo || 'guardar';
            var cuerpo = {
                configuracion_id: detalle.getAttribute('data-configuracion-id'),
                regla_id: editor.elements.regla_id.value || null,
                motivo: editor.elements.motivo.value
            };
            var accion;
            if (modo === 'retirar') {
                accion = 'configuracion.retirar-regla';
                estado.textContent = 'Retirando la regla…';
            } else {
                accion = 'configuracion.guardar-regla';
                cuerpo.subtipo_valor_zetti_id = editor.elements.subtipo_valor_zetti_id.value;
                cuerpo.sentido = editor.elements.sentido.value;
                cuerpo.codigo_extracto = editor.elements.codigo_extracto.value;
                cuerpo.validar_automaticamente = editor.elements.validar_automaticamente.checked;
                estado.textContent = cuerpo.regla_id ? 'Creando una nueva versión…' : 'Creando la regla…';
            }
            guardar.disabled = true;
            cancelar.disabled = true;
            enviarComando(accion, cuerpo).then(function (contenido) {
                if (modo === 'retirar') {
                    aplicarRetiro(contenido.data);
                    estado.textContent = 'Regla retirada correctamente.';
                } else {
                    aplicarReglaGuardada(contenido.data);
                    estado.textContent = contenido.data.cambio
                        ? 'Regla guardada correctamente.'
                        : 'La regla no tenía cambios estructurales.';
                }
                prepararEditor(null, 'guardar');
            }).catch(function (error) {
                estado.textContent = error.message;
            }).finally(function () {
                guardar.disabled = false;
                cancelar.disabled = false;
            });
        });

        prepararEditor(null, 'guardar');
        limpiarEditorVinculo();
        prepararEditorMapeo(null, 'guardar');
        prepararEditorAsignacion(null, 'guardar');
    }

    function iniciar() {
        var bandejas = document.querySelectorAll('[data-bandeja]');
        Array.prototype.forEach.call(bandejas, function (bandeja) {
            iniciarBarraContexto(bandeja);
            iniciarPaginacion(bandeja);
            iniciarDetalle(bandeja);
        });
        Array.prototype.forEach.call(document.querySelectorAll('[data-importacion-page]'), iniciarImportacion);
        Array.prototype.forEach.call(document.querySelectorAll('[data-configuracion-page]'), iniciarConfiguracion);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', iniciar);
    } else {
        iniciar();
    }
}());
