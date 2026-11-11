<?php

function requerirUsuario(PDO $conexion): array
{
    $cabeceras = getallheaders();
    $autorizacion = $cabeceras['Authorization'] ?? $cabeceras['authorization'] ?? '';

    if (!preg_match('/^Bearer\s+(.+)$/', trim($autorizacion), $coincidencia)) {
        responderJson(401, ['mensaje' => 'Falta el token de autenticación']);
    }

    $token = $coincidencia[1];

    $consulta = $conexion->prepare(
        'SELECT u.*
         FROM sesiones s
         JOIN usuarios u ON u.id_usuario = s.id_usuario
         WHERE s.token = :token AND s.expira_en > NOW()'
    );
    $consulta->execute(['token' => $token]);
    $usuario = $consulta->fetch();

    if (!$usuario) {
        responderJson(401, ['mensaje' => 'Sesión inválida o expirada, iniciá sesión de nuevo']);
    }

    return $usuario;
}

function requerirRol(array $usuario, array $rolesPermitidos): void
{
    if (!in_array($usuario['tipo_usuario'], $rolesPermitidos, true)) {
        responderJson(403, ['mensaje' => 'No tenés permiso para acceder a esta sección']);
    }
}
