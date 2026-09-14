<?php

require_once __DIR__ . '/config/cabeceras.php';
require_once __DIR__ . '/config/conexion.php';
require_once __DIR__ . '/helpers/respuesta.php';
require_once __DIR__ . '/helpers/autenticacion.php';
require_once __DIR__ . '/models/Usuario.php';

$conexion = obtenerConexion();
$usuarioAutenticado = requerirUsuario($conexion);
$modelo = new Usuario($conexion);

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        unset($usuarioAutenticado['password_hash']);
        responderJson(200, $usuarioAutenticado);
        break;

    case 'PUT':
        $datos = leerCuerpoJson();

        if (empty($datos['nombre']) || empty($datos['apellido'])) {
            responderJson(400, ['mensaje' => 'Nombre y apellido son obligatorios']);
        }

        $modelo->actualizarPerfil((int) $usuarioAutenticado['id_usuario'], $datos);
        responderJson(200, ['exito' => true, 'mensaje' => 'Perfil actualizado']);
        break;

    default:
        responderJson(405, ['mensaje' => 'Método no permitido']);
}
