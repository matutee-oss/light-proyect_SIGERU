<?php

/**
 * Lee el header Authorization de forma compatible con Apache, Nginx y PHP-FPM.
 * getallheaders() no existe en todos los servidores y Apache a veces lo descarta.
 */
function obtenerCabeceraAutorizacion(): string
{
    if (function_exists('getallheaders')) {
        foreach (getallheaders() as $nombre => $valor) {
            if (strcasecmp($nombre, 'Authorization') === 0) {
                return (string) $valor;
            }
        }
    }
    return (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
}

function obtenerTokenDeCabecera(): ?string
{
    if (!preg_match('/^Bearer\s+([a-f0-9]{64})$/i', trim(obtenerCabeceraAutorizacion()), $coincidencia)) {
        return null;
    }
    return $coincidencia[1];
}

function requerirUsuario(PDO $conexion): array
{
    $token = obtenerTokenDeCabecera();

    if ($token === null) {
        responderJson(401, ['mensaje' => 'Falta el token de autenticación']);
    }

    $consulta = $conexion->prepare(
        "SELECT u.*
         FROM sesiones s
         JOIN usuarios u ON u.id_usuario = s.id_usuario
         WHERE s.token = :token AND s.expira_en > NOW() AND u.estado = 'activo'"
    );
    $consulta->execute(['token' => $token]);
    $usuario = $consulta->fetch();

    if (!$usuario) {
        responderJson(401, ['mensaje' => 'Sesión inválida o expirada, iniciá sesión de nuevo']);
    }

    unset($usuario['password_hash']);
    return $usuario;
}

function requerirRol(array $usuario, array $rolesPermitidos): void
{
    if (!in_array($usuario['tipo_usuario'], $rolesPermitidos, true)) {
        responderJson(403, ['mensaje' => 'No tenés permiso para acceder a esta sección']);
    }
}
