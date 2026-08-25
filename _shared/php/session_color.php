<?php
require_once dirname(__DIR__, 2) . '/src/config.php';
require_once dirname(__DIR__, 2) . '/src/autoload.php';

header('Content-Type: application/json; charset=utf-8');

try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405);
        throw new RuntimeException('La configuracion de color solo acepta solicitudes POST.');
    }
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    $limites = [
        'hue' => [0, 360],
        'sat' => [0, 2],
        'lum' => [0.5, 1.5],
    ];
    $datos = [];
    foreach ($limites as $nombre => $limite) {
        if (!isset($_POST[$nombre]) || !is_numeric($_POST[$nombre])) {
            throw new InvalidArgumentException('La configuracion de color no es valida.');
        }
        $valor = (float) $_POST[$nombre];
        if ($valor < $limite[0] || $valor > $limite[1]) {
            throw new InvalidArgumentException('La configuracion de color esta fuera de rango.');
        }
        $datos[$nombre] = $valor;
    }

    $_SESSION['COLOR_CONF'] = $datos;
    echo json_encode($datos);
} catch (Throwable $error) {
    if (http_response_code() < 400) {
        http_response_code(400);
    }
    echo json_encode(['error' => $error->getMessage()]);
}
