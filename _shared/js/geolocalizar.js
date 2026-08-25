console.log('--- carga geolocalizar.js ---');
// trae coordenadas del dispositivo (requiere usar protocolo HTTPS!)
async function geolocalizar() {
    if (!("geolocation" in navigator)) {
        console.warn("Geolocalización no soportada por este dispositivo");
        return { latitud: null, longitud: null };
    }
    return new Promise((resolve) => {
        navigator.geolocation.getCurrentPosition(
            (position) => {
                resolve({
                    latitud: position.coords.latitude,
                    longitud: position.coords.longitude
                });
            },
            (error) => {
                console.error("Error obteniendo ubicación:", error.message);
                resolve({
                    latitud: null,
                    longitud: null
                });
            },
            {
                enableHighAccuracy: true,
                timeout: 10000,
                maximumAge: 0
            }
        );
    });
}
// usa la fórmula de Haversine para calcular la distancia entre dos puntos en una esfera
function calcularDistancia(punto1, punto2) {
    let lat1 = gradosARadianes(punto1.latitud);
    let lat2 = gradosARadianes(punto2.latitud);
    // calcula la diferencia de latitud y longitud entre los puntos
    let deltaLat = lat2 - lat1;
    let deltaLon = gradosARadianes(punto2.longitud) - gradosARadianes(punto1.longitud);
    // a: proporción de la superficie de la esfera cubierta por el ángulo entre los dos puntos
    let a = Math.sin(deltaLat / 2) ** 2 + Math.cos(lat1) * Math.cos(lat2) * Math.sin(deltaLon / 2) ** 2;
    // c: ángulo central entre los puntos
    let c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    // radio de la tierra en metros = 6371000
    return 6371000 * c;
}
// aproximación a la fórmula de Haversine para distancias cortas, con menos necesidad de cómputo
function calcularDistanciaAprox(punto1, punto2){
    let latMedia = (punto1.latitud + punto2.latitud) / 2;
    // cantidad de metros en un grado de latitud ≈ 111320
    let deltaLat = (punto2.latitud - punto1.latitud) * 111320;
    let deltaLon = (punto2.longitud - punto1.longitud) * 111320 * Math.cos(latMedia * Math.PI / 180);
    return Math.sqrt(deltaLat ** 2 + deltaLon ** 2);
}
// lo que dice el título
function gradosARadianes(grados) {
    return grados * Math.PI / 180;
}
//busca nodos cercanos
function nodosCercanos(ubicacion, nodos, distanciaMaxima = 300) {
    if (!coordenadasValidas(ubicacion)) {
        return [];
    }

    let calcular = distanciaMaxima > 3000 ? calcularDistancia : calcularDistanciaAprox;
    let nodos_cercanos = [];
    let n = nodos.length;
    for (let x = 0; x < n; x++) {
        let nodo = nodos[x];
        if (!coordenadasValidas(nodo)) {
            continue;
        }
        let distancia = calcular(ubicacion, nodo);
        if (distancia < distanciaMaxima) {
            nodos_cercanos.push({ ...nodo, distancia });
        }
    }
    // ordena los nodos cercanos antes de devolverlos
    nodos_cercanos.sort((a, b) => a.distancia - b.distancia);
    return nodos_cercanos;
}

function coordenadasValidas(punto) {
    if (!punto
        || punto.latitud === null
        || punto.latitud === ''
        || punto.longitud === null
        || punto.longitud === ''
    ) {
        return false;
    }
    return Number.isFinite(Number(punto.latitud))
        && Number.isFinite(Number(punto.longitud));
}

async function geolocaliprueba(radio_busqueda){
    const ubicacion = await geolocalizar();
    const nodos = vars.data.nodos;
    const geolocalizacion = {
        ubicacion: ubicacion,
        nodo: { indice: null }
    };
    vars.data.geolocalizacion = geolocalizacion;

    let nodos_cercanos = nodosCercanos(ubicacion, nodos, radio_busqueda);
    selectorNodo(
        nodos,
        geolocalizacion.nodo,
        {
            al_confirmar: function (nodo) {
                abre_maps(ubicacion, nodo);
            },
            al_ampliar_busqueda: function () {
                geolocaliprueba(radio_busqueda * 10);
            },
            nodos_cercanos : nodos_cercanos, 
            clases_personalizadas : 'anula_variacion',
            ejecuta_siempre : true,
            mensaje_cero_nodos: 'Ningún nodo encontrado en el rango establecido'
        }
    );
}

function abre_maps(ubicacion, nodo){
    window.open(
        'https://www.google.com/maps/dir/'+
        ubicacion.latitud+','+ubicacion.longitud+'/'+
        nodo.latitud+','+nodo.longitud
    , '_blank');
}
