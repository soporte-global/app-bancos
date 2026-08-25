// llamada a la cosa de abajo!
function generar_formulario(elemento_html, ruta_json){
    fetch(window.contextoApp.app.ruta + ruta_json)                 // trae el contenido del archivo .json
    .then(resp => resp.json())                              // convierte promise en objeto de js
    .then(async data => {                                   // hace sus cosillas
        console.log(data);
        await QueryForm.render(elemento_html, data);                   
    })
    .catch(err => console.error(err));                     // si se rompe te avisa
}
window.QueryForm = (() => {
    let QueryForm = {};
    const seleccionesAutocomplete = {};
    // renderiza el formulario completo a partir del json
    QueryForm.render = async function(containerSelector, config){
        let $container = $(containerSelector);
        $container.empty();
        let $wrapper = $('<div class="query-wrapper"></div>');
        let $title = $('<h2></h2>').text(config.titulo || config.id || 'Consulta');
        let $desc = $('<p></p>').text(config.descripcion || '');
        let $form = $('<form class="query-form"></form>');
        let $acciones = $('<div class="acciones"></div>');
        let $submit = $('<button type="submit">Ejecutar</button>');
        let $debug = $('<pre class="resultado-json"></pre>');
        $form.attr('data-query-id', config.id || '');
        let parametros = Array.isArray(config.parametros) ? config.parametros : [];
        for(let parametro of parametros){
            let $field = await QueryForm.buildField(parametro);
            $form.append($field);
        }
        $acciones.append($submit);
        $form.append($acciones);
        // serializa los valores al enviar para probar la estructura generada
        $form.on('submit', function(e){
            e.preventDefault();
            let datos = QueryForm.serializeForm($(this), parametros);
            $debug.text(JSON.stringify(datos, null, 4));
            // $.ajax({
            //     url: window.contextoApp.app.ruta + '/api/query/run',
            //     method: 'POST',
            //     contentType: 'application/json',
            //     data: JSON.stringify({
            //         query_id: config.id,
            //         parametros: datos
            //     })
            // });
        });
        $wrapper.append($title, $desc, $form, $debug);
        $container.append($wrapper);
    };
    // crea el control html correspondiente según el tipo de parámetro
    QueryForm.buildField = async function(parametro){
        let tipo = parametro.tipo || 'text';
        let nombre = parametro.nombre || '';
        let label = parametro.label || nombre;
        let required = !!parametro.required;
        let inputId = 'campo_' + nombre;
        let $field = $('<div class="field"></div>');
        if(tipo === 'span'){
            let texto = parametro.texto ?? parametro.html ?? '';
            if(parametro.clase){
                $field.addClass(parametro.clase);
            }
            if(!!parametro.html){
                $field.append($('<span></span>').html(parametro.html));
            } else {
                $field.append($('<span></span>').text(texto));
            }
            return $field;
        }
        let $label = $('<label></label>');
        $label.attr('for', inputId);
        $label.text(label + (required ? ' *' : ''));
        $field.append($label);
        // inputs simples que sólo requieren default y validación básica
        if(tipo === 'text' || tipo === 'number' || tipo === 'date'){
            let $input = $('<input>');
            $input.attr('type', tipo);
            $input.attr('id', inputId);
            $input.attr('name', nombre);
            if(required){
                $input.prop('required', true);
            }
            let defaultValue = QueryForm.resolveDefault(parametro);
            if(defaultValue !== null && defaultValue !== undefined){
                $input.val(defaultValue);
            }
            $field.append($input);
            return $field;
        }
        // checkbox con valor booleano derivado del default
        if(tipo === 'checkbox'){
            let $input = $('<input type="checkbox">');
            $input.attr('id', inputId);
            $input.attr('name', nombre);
            let defaultValue = QueryForm.resolveDefault(parametro);
            if(!!defaultValue){
                $input.prop('checked', true);
            }
            $field.append($input);
            return $field;
        }
        // radio a partir de opciones normalizadas
        if(tipo === 'radio'){
            let opciones = await QueryForm.resolveOptions(parametro);
            let defaultValue = QueryForm.resolveDefault(parametro);
            let $radioGroup = $('<div class="radio-group"></div>');
            for(let opcion of opciones){
                let radioId = inputId + '_' + QueryForm.safeId(opcion.value);
                let $item = $('<div class="radio-item"></div>');
                let $input = $('<input type="radio">');
                let $labelRadio = $('<label></label>');
                $input.attr('id', radioId);
                $input.attr('name', nombre);
                $input.attr('value', opcion.value);
                if(required){
                    $input.prop('required', true);
                }
                if(String(defaultValue) === String(opcion.value)){
                    $input.prop('checked', true);
                }
                $labelRadio.attr('for', radioId);
                $labelRadio.text(opcion.label);
                $item.append($input, $labelRadio);
                $radioGroup.append($item);
            }
            $field.append($radioGroup);
            return $field;
        }
        // select combinando opciones fijas y dinámicas
        if(tipo === 'select'){
            let $select = $('<select></select>');
            $select.attr('id', inputId);
            $select.attr('name', nombre);
            if(required){
                $select.prop('required', true);
            }
            let opciones = await QueryForm.resolveOptions(parametro);
            let defaultValue = QueryForm.resolveDefault(parametro);
            if(!required && !opciones.some(op => String(op.value) === '')){
                $select.append('<option value="">-- Seleccionar --</option>');
            }
            for(let opcion of opciones){
                let $option = $('<option></option>');
                $option.attr('value', opcion.value);
                $option.text(opcion.label);
                if(String(defaultValue) === String(opcion.value)){
                    $option.prop('selected', true);
                }
                $select.append($option);
            }
            $field.append($select);
            return $field;
        }
        // autocomplete con input visible y hidden opcional para id real
        if(tipo === 'autocomplete'){
            let autocomplete = parametro.autocomplete || {};
            let guardarIdEnHidden = !!autocomplete.guardar_id_en_hidden;
            let $input = $('<input type="text">');
            $input.attr('id', inputId);
            $input.attr('name', nombre);
            $input.attr('autocomplete', 'off');
            if(required){
                $input.prop('required', true);
            }
            let defaultValue = QueryForm.resolveDefault(parametro);
            if(defaultValue !== null && defaultValue !== undefined){
                $input.val(defaultValue);
            }
            $field.append($input);
            if(guardarIdEnHidden){
                let $hidden = $('<input type="hidden">');
                $hidden.attr('name', nombre + '_id');
                $hidden.attr('data-hidden-for', nombre);
                $field.append($hidden);
            }
            QueryForm.bindAutocomplete($field, $input, parametro);
            return $field;
        }
        // fallback para tipos no contemplados aún
        let $fallback = $('<input type="text">');
        $fallback.attr('id', inputId);
        $fallback.attr('name', nombre);
        if(required){
            $fallback.prop('required', true);
        }
        $field.append($fallback);
        return $field;
    };
    // resuelve defaults simples o relativos según el json
    QueryForm.resolveDefault = function(parametro){
        let def = parametro.default;
        if(def === null || def === undefined){
            return null;
        }
        if(typeof def !== 'object' || Array.isArray(def)){
            return def;
        }
        // calcula valores relativos usando la fecha actual
        if(def.tipo === 'relativo'){
            let now = new Date();
            if(def.unidad === 'ano_actual'){
                return now.getFullYear() + Number(def.offset || 0);
            }
            if(def.unidad === 'mes_actual'){
                return now.getMonth() + 1 + Number(def.offset || 0);
            }
            if(def.unidad === 'fecha_actual'){
                let fecha = new Date(now);
                fecha.setDate(fecha.getDate() + Number(def.offset || 0));
                return QueryForm.formatDateInput(fecha);
            }
        }
        return null;
    };
    // une opciones fijas con dinámicas y quita repetidos
    QueryForm.resolveOptions = async function(parametro){
        let opcionesFinales = [];
        let opcionesBase = Array.isArray(parametro.opciones) ? parametro.opciones : [];
        let opcionesDinamicas = Array.isArray(parametro.opciones_dinamicas) ? parametro.opciones_dinamicas : [];
        opcionesFinales = opcionesFinales.concat(QueryForm.normalizeOptions(opcionesBase));
        for(let configDinamica of opcionesDinamicas){
            let opcionesGeneradas = QueryForm.buildDynamicOptions(configDinamica);
            opcionesFinales = opcionesFinales.concat(QueryForm.normalizeOptions(opcionesGeneradas));
        }
        opcionesFinales = QueryForm.deduplicateOptions(opcionesFinales);
        return opcionesFinales;
    };
    // normaliza cualquier formato de opción a value/label
    QueryForm.normalizeOptions = function(opciones){
        let salida = [];
        for(let opcion of opciones){
            if(typeof opcion === 'object' && opcion !== null && !Array.isArray(opcion)){
                salida.push({
                    value: opcion.value,
                    label: opcion.label ?? String(opcion.value)
                });
            } else {
                salida.push({
                    value: opcion,
                    label: String(opcion)
                });
            }
        }
        return salida;
    };
    // evita duplicados al mezclar múltiples fuentes de opciones
    QueryForm.deduplicateOptions = function(opciones){
        let vistos = new Set();
        let salida = [];
        for(let opcion of opciones){
            let key = String(opcion.value);
            if(vistos.has(key)){
                continue;
            }
            vistos.add(key);
            salida.push(opcion);
        }
        return salida;
    };
    // genera opciones dinámicas conocidas desde configuración
    QueryForm.buildDynamicOptions = function(config){
        let opciones = [];
        if(!config || typeof config !== 'object'){
            return opciones;
        }
        // genera secuencias numéricas simples
        if(config.tipo === 'sequence'){
            let from = Number(config.from);
            let to = Number(config.to);
            let step = Number(config.step || 1);
            let orden = config.orden || 'asc';
            if(step <= 0){
                step = 1;
            }
            for(let i = from; i <= to; i += step){
                opciones.push({
                    value: i,
                    label: String(i)
                });
            }
            if(orden === 'desc'){
                opciones.reverse();
            }
            return opciones;
        }
        // genera años hacia atrás desde el actual
        if(config.tipo === 'ultimos_anos'){
            let now = new Date();
            let actual = now.getFullYear();
            let cantidad = Number(config.cantidad || 5);
            let incluirActual = config.incluir_actual !== false;
            let orden = config.orden || 'desc';
            let inicio = incluirActual ? actual : actual - 1;
            for(let i = 0; i < cantidad; i++){
                let ano = inicio - i;
                opciones.push({
                    value: ano,
                    label: String(ano)
                });
            }
            if(orden === 'asc'){
                opciones.reverse();
            }
            return opciones;
        }
        // devuelve el catálogo fijo de meses
        if(config.tipo === 'meses'){
            let meses = [
                { value: 1, label: 'Enero' },
                { value: 2, label: 'Febrero' },
                { value: 3, label: 'Marzo' },
                { value: 4, label: 'Abril' },
                { value: 5, label: 'Mayo' },
                { value: 6, label: 'Junio' },
                { value: 7, label: 'Julio' },
                { value: 8, label: 'Agosto' },
                { value: 9, label: 'Septiembre' },
                { value: 10, label: 'Octubre' },
                { value: 11, label: 'Noviembre' },
                { value: 12, label: 'Diciembre' }
            ];
            if(config.orden === 'desc'){
                meses.reverse();
            }
            return meses;
        }
        return opciones;
    };





















    QueryForm.bindAutocomplete = function($field, $input, parametro){
        let autocomplete = parametro.autocomplete || {};
        // -----------------------------------
        // a modificar cuando se arme el front
        let onSearchStart = () => {};
        let onSearchEnd = () => {};
        // -----------------------------------
        let $hidden = !!autocomplete.guardar_id_en_hidden ? $field.find('input[type="hidden"][data-hidden-for="' + parametro.nombre + '"]') : $();
        $input.on('input', function(){
            if(!!autocomplete.guardar_id_en_hidden){
                $hidden.val('');
            }
        });
        let opcionesAutocomplete = {
            onSelect: function(item, event, inputEl){
                let idField = Object.prototype.hasOwnProperty.call(autocomplete, 'idField') ? autocomplete.idField : 'id';
                let idSeleccionado = item?.[idField] ?? '';
                let textoSeleccionado = $(inputEl).val() ?? '';
                if(!!autocomplete.guardar_id_en_hidden){
                    $hidden.val(idSeleccionado);
                }
                if(typeof autocomplete.onSelect === 'function'){
                    autocomplete.onSelect(item, event, inputEl);
                }
                // conserva la ultima seleccion valida mientras vive el formulario
                seleccionesAutocomplete[parametro.nombre] = {
                    id: idSeleccionado,
                    texto: textoSeleccionado
                };
            },
            onClose: function(){












                
                // volver al ultimo estado válido o borrar (pulir esto que lo armó el tío y no funciona)
                let ultimo = seleccionesAutocomplete[parametro.nombre] || null;
                let textoActual = ($input.val() ?? '').trim();
                let hiddenActual = !!autocomplete.guardar_id_en_hidden ? (($hidden.val() ?? '').trim()) : '';
                if(textoActual === ''){
                    if(!!autocomplete.guardar_id_en_hidden){
                        $hidden.val('');
                    }
                    delete seleccionesAutocomplete[parametro.nombre];
                    return;
                }
                if(hiddenActual !== ''){
                    return;
                }
                if(ultimo != null && (ultimo.texto ?? '') !== ''){
                    $input.val(ultimo.texto);
                    if(!!autocomplete.guardar_id_en_hidden){
                        $hidden.val(ultimo.id ?? '');
                    }
                } else {
                    $input.val('');
                    if(!!autocomplete.guardar_id_en_hidden){
                        $hidden.val('');
                    }
                }









            },
            onSearchStart: onSearchStart,
            onSearchEnd: onSearchEnd
        };
        // --------
        if(Array.isArray(autocomplete.opciones)) opcionesAutocomplete.array_busqueda = autocomplete.opciones;
        if(autocomplete.source != null) opcionesAutocomplete.source = autocomplete.source;
        if(autocomplete.source != null) opcionesAutocomplete.requestData = {parametro: parametro.nombre};
        // --------
        if(Object.prototype.hasOwnProperty.call(autocomplete, 'idField')) opcionesAutocomplete.campoID = autocomplete.idField;
        if(Object.prototype.hasOwnProperty.call(autocomplete, 'searchBy')) opcionesAutocomplete.searchBy = autocomplete.searchBy;
        if(Object.prototype.hasOwnProperty.call(autocomplete, 'labelField')) opcionesAutocomplete.optionLabel = autocomplete.labelField;
        if(Object.prototype.hasOwnProperty.call(autocomplete, 'valueField')) opcionesAutocomplete.optionValue = autocomplete.valueField;
        if(Object.prototype.hasOwnProperty.call(autocomplete, 'minLength')) opcionesAutocomplete.minLength = Number(autocomplete.minLength ?? 0);
        if(Object.prototype.hasOwnProperty.call(autocomplete, 'maxResults')) opcionesAutocomplete.maxResults = Number(autocomplete.maxResults ?? 0);
        if(Object.prototype.hasOwnProperty.call(autocomplete, 'focusSearch')) opcionesAutocomplete.focusSearch = autocomplete.focusSearch !== false;
        if(Object.prototype.hasOwnProperty.call(autocomplete, 'optionAll')) opcionesAutocomplete.optionAll = autocomplete.optionAll || null;
        // --------
        $input.crearAutocomplete(opcionesAutocomplete);
    };






















    // convierte el formulario a un objeto simple para enviar o depurar
    QueryForm.serializeForm = function($form, parametros){
        let salida = {};
        for(let parametro of parametros){
            let nombre = parametro.nombre;
            let tipo = parametro.tipo || 'text';
            if(tipo === 'span' || !nombre){
                continue;
            }
            // checkbox se serializa como booleano real
            if(tipo === 'checkbox'){
                salida[nombre] = $form.find('[name="' + nombre + '"]').is(':checked');
                continue;
            }
            // radio toma sólo la opción seleccionada
            if(tipo === 'radio'){
                salida[nombre] = $form.find('[name="' + nombre + '"]:checked').val() || null;
                continue;
            }
            // autocomplete puede devolver texto visible e id oculto
            if(tipo === 'autocomplete'){
                salida[nombre] = $form.find('[name="' + nombre + '"]').val();
                let $hidden = $form.find('[name="' + nombre + '_id"]');
                if($hidden.length){
                    salida[nombre + '_id'] = $hidden.val() || null;
                }
                continue;
            }
            // para el resto toma el valor directo del control
            salida[nombre] = $form.find('[name="' + nombre + '"]').val();
        }
        return salida;
    };
    // sanitiza valores para usarlos dentro de ids html
    QueryForm.safeId = function(valor){
        return String(valor).replace(/[^a-zA-Z0-9_-]/g, '_');
    };
    // formatea fechas al formato esperado por input date
    QueryForm.formatDateInput = function(fecha){
        let ano = fecha.getFullYear();
        let mes = String(fecha.getMonth() + 1).padStart(2, '0');
        let dia = String(fecha.getDate()).padStart(2, '0');
        return ano + '-' + mes + '-' + dia;
    };
    return QueryForm;
})();
