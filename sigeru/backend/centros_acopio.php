<?php

require_once __DIR__ . '/config/cabeceras.php';
require_once __DIR__ . '/config/conexion.php';
require_once __DIR__ . '/helpers/respuesta.php';
require_once __DIR__ . '/helpers/autenticacion.php';
require_once __DIR__ . '/controllers/CentroAcopioController.php';

$conexion = obtenerConexion();
$usuarioAutenticado = requerirUsuario($conexion);
requerirRol($usuarioAutenticado, ['administrador', 'operario']);

$controlador = new CentroAcopioController($conexion);
$metodo = $_SERVER['REQUEST_METHOD'];
$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$accion = $_GET['accion'] ?? '';

switch ($metodo) {
    case 'GET':
        if ($id) {
            $centro = $controlador->obtener($id);
            $centro
                ? responderJson(200, $centro)
                : responderJson(404, ['mensaje' => 'Centro de acopio no encontrado']);
        }
        responderJson(200, $controlador->listar());
        break;

    case 'POST':
        requerirRol($usuarioAutenticado, ['administrador']);

        if ($accion === 'agregar_recurso' && $id) {
            $datos = leerCuerpoJson();
            $resultado = $controlador->agregarRecurso($id, $datos);
            responderJson($resultado['exito'] ? 201 : 400, $resultado);
            break;
        }

        $datos = leerCuerpoJson();
        $resultado = $controlador->crear($datos);
        responderJson($resultado['exito'] ? 201 : 400, $resultado);
        break;

    case 'PUT':
        requerirRol($usuarioAutenticado, ['administrador']);
        if (!$id) {
            responderJson(400, ['mensaje' => 'Debe indicar ?id= del centro a actualizar']);
        }

        if ($accion === 'actualizar_recurso') {
            $datos = leerCuerpoJson();
            $idRecurso = isset($_GET['id_recurso']) ? (int) $_GET['id_recurso'] : null;
            if (!$idRecurso) {
                responderJson(400, ['mensaje' => 'Debe indicar ?id_recurso=']);
            }
            $resultado = $controlador->actualizarRecurso($id, $idRecurso, $datos);
            responderJson($resultado['exito'] ? 200 : 404, $resultado);
            break;
        }

        $datos = leerCuerpoJson();
        $resultado = $controlador->actualizar($id, $datos);
        responderJson($resultado['exito'] ? 200 : 404, $resultado);
        break;

    case 'DELETE':
        requerirRol($usuarioAutenticado, ['administrador']);
        if (!$id) {
            responderJson(400, ['mensaje' => 'Debe indicar ?id= del centro a eliminar']);
        }
        $resultado = $controlador->eliminar($id);
        responderJson($resultado['exito'] ? 200 : 404, $resultado);
        break;

    default:
        responderJson(405, ['mensaje' => 'Método no permitido']);
}
