<?php

require_once __DIR__ . '/config/cabeceras.php';
require_once __DIR__ . '/config/conexion.php';
require_once __DIR__ . '/helpers/respuesta.php';
require_once __DIR__ . '/helpers/autenticacion.php';
require_once __DIR__ . '/controllers/IncidenciaController.php';

$conexion = obtenerConexion();
$usuarioAutenticado = requerirUsuario($conexion);

$controlador = new IncidenciaController($conexion);
$metodo = $_SERVER['REQUEST_METHOD'];
$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$accion = $_GET['accion'] ?? '';

switch ($metodo) {
    case 'GET':
        if ($id) {
            $incidencia = $controlador->obtener($id, $usuarioAutenticado);
            $incidencia
                ? responderJson(200, $incidencia)
                : responderJson(404, ['mensaje' => 'Incidencia no encontrada']);
        }
        responderJson(200, $controlador->listarSegunRol($usuarioAutenticado));
        break;

    case 'POST':
        $datos = leerCuerpoJson();

        if ($accion === 'tomar' && $id) {
            requerirRol($usuarioAutenticado, ['cuadrilla']);
            $resultado = $controlador->tomar($id, $usuarioAutenticado);
        } elseif ($accion === 'cambiar_estado' && $id) {
            requerirRol($usuarioAutenticado, ['administrador', 'cuadrilla']);
            $resultado = $controlador->cambiarEstado($id, $datos['estado'] ?? '');
        } else {
            requerirRol($usuarioAutenticado, ['vecino']);
            $resultado = $controlador->reportar((int) $usuarioAutenticado['id_usuario'], $datos);
        }

        responderJson($resultado['exito'] ? 200 : 400, $resultado);
        break;

    default:
        responderJson(405, ['mensaje' => 'Método no permitido']);
}
