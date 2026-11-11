<?php

require_once __DIR__ . '/config/cabeceras.php';
require_once __DIR__ . '/config/conexion.php';
require_once __DIR__ . '/helpers/respuesta.php';
require_once __DIR__ . '/helpers/autenticacion.php';
require_once __DIR__ . '/controllers/VecinoController.php';

$conexion = obtenerConexion();
$usuarioAutenticado = requerirUsuario($conexion);
requerirRol($usuarioAutenticado, ['vecino']);

$controlador = new VecinoController($conexion);
$idUsuario = (int) $usuarioAutenticado['id_usuario'];

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        responderJson(200, $controlador->obtenerPanel($idUsuario));
        break;

    case 'POST':
        $accion = $_GET['accion'] ?? '';
        $datos = leerCuerpoJson();

        if ($accion === 'elegir_zona') {
            $resultado = $controlador->elegirZona($idUsuario, (int) ($datos['id_zona'] ?? 0));
            responderJson($resultado['exito'] ? 200 : 400, $resultado);
        } else {
            responderJson(400, ['mensaje' => 'Acción no reconocida']);
        }
        break;

    default:
        responderJson(405, ['mensaje' => 'Método no permitido']);
}
