<?php

require_once __DIR__ . '/config/cabeceras.php';
require_once __DIR__ . '/config/conexion.php';
require_once __DIR__ . '/helpers/respuesta.php';
require_once __DIR__ . '/helpers/autenticacion.php';
require_once __DIR__ . '/controllers/ContenedorController.php';

$conexion = obtenerConexion();
$usuarioAutenticado = requerirUsuario($conexion);
requerirRol($usuarioAutenticado, ['administrador']);

$controlador = new ContenedorController($conexion);
$metodo = $_SERVER['REQUEST_METHOD'];
$id = parametroEntero('id');

switch ($metodo) {
    case 'GET':
        if ($id) {
            $contenedor = $controlador->obtener($id);
            $contenedor
                ? responderJson(200, $contenedor)
                : responderJson(404, ['mensaje' => 'Contenedor no encontrado']);
        }
        responderJson(200, $controlador->listar(array_intersect_key($_GET, array_flip(['id_zona', 'estado', 'nivel_minimo']))));
        break;

    case 'POST':
        $datos = leerCuerpoJson();
        $resultado = $controlador->crear($datos);
        responderResultado($resultado, 201);
        break;

    case 'PUT':
        if (!$id) {
            responderJson(400, ['mensaje' => 'Debe indicar ?id= del contenedor a actualizar']);
        }
        $datos = leerCuerpoJson();
        $resultado = $controlador->actualizar($id, $datos);
        responderResultado($resultado);
        break;

    case 'DELETE':
        if (!$id) {
            responderJson(400, ['mensaje' => 'Debe indicar ?id= del contenedor a eliminar']);
        }
        $resultado = $controlador->eliminar($id);
        responderResultado($resultado);
        break;

    default:
        responderJson(405, ['mensaje' => 'Método no permitido']);
}
