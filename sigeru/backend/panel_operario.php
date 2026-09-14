<?php

require_once __DIR__ . '/config/cabeceras.php';
require_once __DIR__ . '/config/conexion.php';
require_once __DIR__ . '/helpers/respuesta.php';
require_once __DIR__ . '/helpers/autenticacion.php';
require_once __DIR__ . '/controllers/OperarioController.php';

$conexion = obtenerConexion();
$usuarioAutenticado = requerirUsuario($conexion);
requerirRol($usuarioAutenticado, ['operario']);

$controlador = new OperarioController($conexion);
$idUsuario = (int) $usuarioAutenticado['id_usuario'];

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        $resultado = $controlador->obtenerPanel($idUsuario);
        responderJson($resultado['exito'] ? 200 : 404, $resultado);
        break;

    case 'POST':
        $accion = $_GET['accion'] ?? '';
        $datos = leerCuerpoJson();

        if ($accion === 'registrar_ingreso') {
            $resultado = $controlador->registrarIngreso($idUsuario, $datos);
            responderJson($resultado['exito'] ? 201 : 400, $resultado);
        } else {
            responderJson(400, ['mensaje' => 'Acción no reconocida']);
        }
        break;

    case 'PUT':
        $idRecurso = isset($_GET['id_recurso']) ? (int) $_GET['id_recurso'] : null;
        if (!$idRecurso) {
            responderJson(400, ['mensaje' => 'Debe indicar ?id_recurso=']);
        }
        $datos = leerCuerpoJson();
        $resultado = $controlador->actualizarRecurso($idUsuario, $idRecurso, (int) ($datos['cantidad'] ?? 0));
        responderJson($resultado['exito'] ? 200 : 400, $resultado);
        break;

    default:
        responderJson(405, ['mensaje' => 'Método no permitido']);
}
