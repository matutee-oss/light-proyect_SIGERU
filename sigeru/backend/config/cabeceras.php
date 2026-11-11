<?php

date_default_timezone_set('America/Montevideo');

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Cualquier error no controlado se devuelve como JSON (antes salía HTML de PHP
// y el frontend fallaba al hacer respuesta.json()).
set_exception_handler(function (Throwable $error) {
    error_log('[SiGeRU] ' . $error->getMessage() . ' en ' . $error->getFile() . ':' . $error->getLine());
    http_response_code(500);
    echo json_encode(['exito' => false, 'mensaje' => 'Error interno del servidor'], JSON_UNESCAPED_UNICODE);
    exit;
});
