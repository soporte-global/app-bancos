<?php
if (!defined('RUTA')) {
    http_response_code(403);
    exit;
}

// una app legacy puede reconstruir $user, $nivel_acceso o ajustar $contextoApp
