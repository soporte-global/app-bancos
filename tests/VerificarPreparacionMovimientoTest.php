<?php

$raiz = dirname(__DIR__);
$vista = file_get_contents($raiz . '/app/html/bandeja-mensual.php');
$javascript = file_get_contents($raiz . '/app/js/shared.js');
$api = file_get_contents($raiz . '/api.php');

foreach ([
    'data-preparar-movimiento',
    'No crea ni modifica datos del ERP',
    'movimiento-preparar',
    'data-revertir-preparacion',
    'movimiento-revertir-preparacion',
    'El historial se conserva',
    'data-asociar-valor',
    'movimiento-asociar-valor',
    'El importe asociado se toma del movimiento',
    'data-crear-borrador',
    'movimiento-crear-borrador',
    'El borrador debe tener entre 2 y 200 líneas',
] as $fragmento) {
    if (strpos($vista, $fragmento) === false) {
        throw new RuntimeException('La vista no contiene el contrato de preparacion: ' . $fragmento);
    }
}
foreach ([
    'X-CSRF-Token',
    'Idempotency-Key',
    'movimiento.preparar',
    'movimiento.revertir-preparacion',
    'motivo: motivo',
    'movimiento.asociar-valor',
    'valor_zetti_id: valorId',
    'movimiento.crear-borrador',
    'lineas: lineas',
    'formularioBandeja.requestSubmit()',
] as $fragmento) {
    if (strpos($javascript, $fragmento) === false) {
        throw new RuntimeException('El cliente no contiene el contrato de preparacion: ' . $fragmento);
    }
}
foreach ([
    "if (!BANCOS_DEBUG)",
    "'movimiento-preparar'",
    "'movimiento-revertir-preparacion'",
    "'movimiento-asociar-valor'",
    "'movimiento-crear-borrador'",
    "'application/json'",
] as $fragmento) {
    if (strpos($api, $fragmento) === false) {
        throw new RuntimeException('La API no protege la preparacion: ' . $fragmento);
    }
}

echo "Superficie de preparacion de movimiento OK\n";
