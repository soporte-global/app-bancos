<?php

$raiz = dirname(__DIR__);
$vista = file_get_contents($raiz . '/app/html/bandeja-mensual.php');
$javascript = file_get_contents($raiz . '/app/js/shared.js');
$api = file_get_contents($raiz . '/api.php');

foreach ([
    'data-preparar-movimiento',
    'No crea ni modifica datos del ERP',
    'movimiento-preparar',
] as $fragmento) {
    if (strpos($vista, $fragmento) === false) {
        throw new RuntimeException('La vista no contiene el contrato de preparacion: ' . $fragmento);
    }
}
foreach ([
    'X-CSRF-Token',
    'Idempotency-Key',
    'movimiento.preparar',
    'formularioBandeja.requestSubmit()',
] as $fragmento) {
    if (strpos($javascript, $fragmento) === false) {
        throw new RuntimeException('El cliente no contiene el contrato de preparacion: ' . $fragmento);
    }
}
foreach ([
    "if (!BANCOS_DEBUG)",
    "'movimiento-preparar'",
    "'application/json'",
] as $fragmento) {
    if (strpos($api, $fragmento) === false) {
        throw new RuntimeException('La API no protege la preparacion: ' . $fragmento);
    }
}

echo "Superficie de preparacion de movimiento OK\n";
