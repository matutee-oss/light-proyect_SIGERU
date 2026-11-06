<?php

function responderJson(int $codigoHttp, array $datos): void
{
    http_response_code($codigoHttp);
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Responde según el resultado estándar de los controladores:
 * ['exito' => bool, 'mensaje' => string, 'codigo' => int opcional].
 */
function responderResultado(array $resultado, int $codigoExito = 200): void
{
    $codigo = $resultado['exito'] ? $codigoExito : ($resultado['codigo'] ?? 400);
    unset($resultado['codigo']);
    responderJson($codigo, $resultado);
}

function leerCuerpoJson(): array
{
    $crudo = file_get_contents('php://input');
    $datos = json_decode($crudo, true);
    return is_array($datos) ? $datos : [];
}

function parametroEntero(string $nombre): ?int
{
    if (!isset($_GET[$nombre]) || $_GET[$nombre] === '') {
        return null;
    }
    return filter_var($_GET[$nombre], FILTER_VALIDATE_INT) !== false ? (int) $_GET[$nombre] : null;
}
