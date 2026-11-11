<?php

require_once __DIR__ . '/config/cabeceras.php';
require_once __DIR__ . '/config/conexion.php';
require_once __DIR__ . '/helpers/respuesta.php';
require_once __DIR__ . '/helpers/autenticacion.php';
require_once __DIR__ . '/controllers/UsuarioController.php';

$conexion = obtenerConexion();
$usuarioAutenticado = requerirUsuario($conexion);
requerirRol($usuarioAutenticado, ['administrador']);

$controlador = new UsuarioController($conexion);
$metodo = $_SERVER['REQUEST_METHOD'];
$id = isset($_GET['id']) ? (int) $_GET['id'] : null;

switch ($metodo) {
    case 'GET':
        if ($id) {
            $usuario = $controlador->obtener($id);
            $usuario
                ? responderJson(200, $usuario)
                : responderJson(404, ['mensaje' => 'Usuario no encontrado']);
        }
        responderJson(200, $controlador->listar());
        break;

    case 'POST':
        $datos = leerCuerpoJson();
        $resultado = $controlador->crear($datos);
        responderJson($resultado['exito'] ? 201 : 400, $resultado);
        break;

    case 'PUT':
        if (!$id) {
            responderJson(400, ['mensaje' => 'Debe indicar ?id= del usuario a actualizar']);
        }
        $datos = leerCuerpoJson();
        $resultado = $controlador->actualizar($id, $datos);
        responderJson($resultado['exito'] ? 200 : 404, $resultado);
        break;

    case 'DELETE':
        if (!$id) {
            responderJson(400, ['mensaje' => 'Debe indicar ?id= del usuario a eliminar']);
        }
        if ($id === (int) $usuarioAutenticado['id_usuario']) {
            responderJson(400, ['mensaje' => 'No podés eliminar tu propio usuario']);
        }
        $resultado = $controlador->eliminar($id);
        responderJson($resultado['exito'] ? 200 : 404, $resultado);
        break;

    default:
        responderJson(405, ['mensaje' => 'Método no permitido']);
}
