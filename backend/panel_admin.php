<?php

require_once __DIR__ . '/config/cabeceras.php';
require_once __DIR__ . '/config/conexion.php';
require_once __DIR__ . '/helpers/respuesta.php';
require_once __DIR__ . '/helpers/autenticacion.php';
require_once __DIR__ . '/controllers/AdminController.php';

$conexion = obtenerConexion();
$usuarioAutenticado = requerirUsuario($conexion);
requerirRol($usuarioAutenticado, ['administrador']);

$controlador = new AdminController($conexion);

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        responderJson(200, $controlador->obtenerResumen());
        break;

    case 'POST':
        $accion = $_GET['accion'] ?? '';
        $datos = leerCuerpoJson();

        switch ($accion) {
            case 'aprobar_usuario':
                $resultado = $controlador->aprobarUsuario((int) ($datos['id_usuario'] ?? 0));
                break;
            case 'rechazar_usuario':
                $resultado = $controlador->rechazarUsuario((int) ($datos['id_usuario'] ?? 0));
                break;
            case 'generar_codigo':
                $resultado = $controlador->generarCodigo(
                    $datos['tipo_usuario'] ?? '',
                    (int) $usuarioAutenticado['id_usuario']
                );
                break;
            case 'publicar_aviso':
                $resultado = $controlador->publicarAviso(
                    $datos['titulo'] ?? '',
                    $datos['mensaje'] ?? '',
                    !empty($datos['id_zona']) ? (int) $datos['id_zona'] : null
                );
                break;
            default:
                responderJson(400, ['mensaje' => 'Acción no reconocida']);
        }

        responderJson($resultado['exito'] ? 200 : 400, $resultado);
        break;

    default:
        responderJson(405, ['mensaje' => 'Método no permitido']);
}
