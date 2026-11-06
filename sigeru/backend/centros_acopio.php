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
$id = parametroEntero('id');
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
            responderResultado($resultado, 201);
            break;
        }

        $datos = leerCuerpoJson();
        $resultado = $controlador->crear($datos);
        responderResultado($resultado, 201);
        break;

    case 'PUT':
        requerirRol($usuarioAutenticado, ['administrador']);
        if (!$id) {
            responderJson(400, ['mensaje' => 'Debe indicar ?id= del centro a actualizar']);
        }

        if ($accion === 'actualizar_recurso') {
            $datos = leerCuerpoJson();
            $idRecurso = parametroEntero('id_recurso');
            if (!$idRecurso) {
                responderJson(400, ['mensaje' => 'Debe indicar ?id_recurso=']);
            }
            $resultado = $controlador->actualizarRecurso($id, $idRecurso, $datos);
            responderResultado($resultado);
            break;
        }

        $datos = leerCuerpoJson();
        $resultado = $controlador->actualizar($id, $datos);
        responderResultado($resultado);
        break;

    case 'DELETE':
        requerirRol($usuarioAutenticado, ['administrador']);
        if ($accion === 'eliminar_recurso' && $id) {
            $idRecurso = parametroEntero('id_recurso');
            if (!$idRecurso) {
                responderJson(400, ['mensaje' => 'Debe indicar ?id_recurso=']);
            }
            responderResultado($controlador->eliminarRecurso($id, $idRecurso));
        }
        if (!$id) {
            responderJson(400, ['mensaje' => 'Debe indicar ?id= del centro a eliminar']);
        }
        $resultado = $controlador->eliminar($id);
        responderResultado($resultado);
        break;

    default:
        responderJson(405, ['mensaje' => 'Método no permitido']);
}
