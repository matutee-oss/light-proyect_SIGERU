<?php

require_once __DIR__ . '/config/cabeceras.php';
require_once __DIR__ . '/config/conexion.php';
require_once __DIR__ . '/helpers/respuesta.php';
require_once __DIR__ . '/helpers/autenticacion.php';
require_once __DIR__ . '/controllers/CamionController.php';

$conexion = obtenerConexion();
$usuarioAutenticado = requerirUsuario($conexion);
requerirRol($usuarioAutenticado, ['administrador']);

$controlador = new CamionController($conexion);
$metodo = $_SERVER['REQUEST_METHOD'];
$id = parametroEntero('id');

switch ($metodo) {
    case 'GET':
        if ($id) {
            $camion = $controlador->obtener($id);
            $camion
                ? responderJson(200, $camion)
                : responderJson(404, ['mensaje' => 'Camión no encontrado']);
        }
        responderJson(200, $controlador->listar());
        break;

    case 'POST':
        $datos = leerCuerpoJson();
        $resultado = $controlador->crear($datos);
        responderResultado($resultado, 201);
        break;

    case 'PUT':
        if (!$id) {
            responderJson(400, ['mensaje' => 'Debe indicar ?id= del camión a actualizar']);
        }
        $datos = leerCuerpoJson();
        $resultado = $controlador->actualizar($id, $datos);
        responderResultado($resultado);
        break;

    case 'DELETE':
        if (!$id) {
            responderJson(400, ['mensaje' => 'Debe indicar ?id= del camión a eliminar']);
        }
        $resultado = $controlador->eliminar($id);
        responderResultado($resultado);
        break;

    default:
        responderJson(405, ['mensaje' => 'Método no permitido']);
}
