console.log('--- carga tablesorter-parsers.js ---');
(function () {
    const cacheFechas = {};
    const columnasPorParser = {};

    $.tablesorter.addParser({
        id: 'checkbox',
        is: function (s, table, cell, $cell) {
            return $cell.find('input[type="checkbox"]').length > 0;
        },
        format: function (s, table, cell) {
            return $(cell).find('input[type="checkbox"]').prop('checked') ? 1 : 0;
        },
        type: 'numeric'
    });

    // para ordenar por fechargentas DD/MM/YYYY
    $.tablesorter.addParser({
        id: 'datetimeFormateado',
        is: function(s) {
            return false;                                                          // CONDICION: ninguna, solo debe coincidir la columna (ver el anterior)
        },
        format: function(s) {
            // revisa si la fecha está en caché, para no recalcular cuando no hace falta
            if (s in cacheFechas) {
                return cacheFechas[s];
            }
            // si no está en caché, pasa la fecha a formato ordenable
            let [day, month, year] = s.split('/').map(Number); // asignación directa split a variables!
            let date = new Date(year, month - 1, day);
            const result = !isNaN(date.getTime()) ? date.getTime() : 0;
            // antes de devolver el número, lo guarda en caché para futuros usos
            cacheFechas[s] = result;
            return result;
        },
        type: 'numeric'
    });

    // Parser: fecha argentina con hora (opcional)
    // Acepta: "d/m/yyyy", "dd/mm/yyyy", con o sin " hh:mm"
    $.tablesorter.addParser({
        id: 'fechaARhm',
        is: function (s, table, cell, $cell) {
            // detecta si el texto parece una fecha dd/mm/yyyy con hh:mm opcional
            return /^\s*\d{1,2}\/\d{1,2}\/\d{4}(?:\s+\d{1,2}:\d{2})?\s*$/.test(s);
        },
        format: function (s, table, cell) {
            s = (s || '').trim();
            if (!s) return Number.NEGATIVE_INFINITY; // vacíos arriba (cambiá a INFINITY si querés abajo)

            let m = s.match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})(?:\s+(\d{1,2}):(\d{2}))?$/);
            if (!m) return Number.NEGATIVE_INFINITY;

            let d  = +m[1], mo = +m[2], y = +m[3];
            let hh = +(m[4] ?? 0), mi = +(m[5] ?? 0);

            // construye en UTC y valida coherencia
            let dt = new Date(Date.UTC(y, mo - 1, d, hh, mi, 0, 0));
            let ok = dt.getUTCFullYear() === y &&
                (dt.getUTCMonth() + 1) === mo &&
                dt.getUTCDate() === d &&
                dt.getUTCHours() === hh &&
                dt.getUTCMinutes() === mi;

            return ok ? dt.getTime() : Number.NEGATIVE_INFINITY;
        },
        type: 'numeric'
    });

    // para ordenar platita $ XXX.XXX,XX, (no utiliza caché porque son muchos valores distintos)
    $.tablesorter.addParser({
        id: 'money',
        is: function(s) {
            return false;                                                           // CONDICION: ninguna, solo debe coincidir la columna
        },
        format: function(s) {
            return numberFormat(s, true);
        },
        type: 'numeric'
    });

    $.tablesorter.addParser({
        id: 'selectValue',
        is: function (s, table, cell, $cell) {
            return $cell.find('select').length > 0;
        },
        format: function (s, table, cell) {
            let sel = cell.querySelector('select');
            return sel ? (sel.value ?? '').trim() : s.trim();
        },
        type: 'text'
    });

    $.tablesorter.addParser({
        id: 'selectValueNum',
        is: function (s, table, cell, $cell) {
            return $cell.find('select').length > 0;
        },
        format: function (s, table, cell) {
            let sel = cell.querySelector('select');
            let v = sel ? sel.value : '';
            let n = parseFloat(String(v).replace(',', '.'));
            return Number.isFinite(n) ? n : Number.NEGATIVE_INFINITY; // vacíos primero
        },
        type: 'numeric'
    });

    // chequea columnas que usan X parser (al final lo hice al pedo pero lo dejo por si sirve en un futuro)
    window.chequea_parsers = function (nombre_parser, id_tabla) {
        let sorterData = $('table#'+id_tabla).data('tablesorter');
        let respuesta = [];
        $.each(sorterData.headers, function(index, header) {
            if (header.sorter === nombre_parser) {
                respuesta.push(index);
            }
        });
        columnasPorParser[nombre_parser] = respuesta;
        return respuesta;
    };
})();
