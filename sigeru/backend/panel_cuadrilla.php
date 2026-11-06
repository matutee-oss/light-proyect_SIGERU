<?php

require_once __DIR__ . '/config/cabeceras.php';
require_once __DIR__ . '/config/conexion.php';
require_once __DIR__ . '/helpers/respuesta.php';
require_once __DIR__ . '/helpers/autenticacion.php';
require_once __DIR__ . '/controllers/CuadrillaController.php';

$conexion = obtenerConexion();
$usuarioAutenticado = requerirUsuario($conexion);
requerirRol($usuarioAutenticado, ['cuadrilla']);

$controlador = new CuadrillaController($conexion);
$idCuadrilla = (int) $usuarioAutenticado['id_usuario'];

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        responderJson(200, $controlador->obtenerPanel($idCuadrilla));
        break;

    case 'POST':
        $accion = $_GET['accion'] ?? '';
        $datos = leerCuerpoJson();

        if ($accion === 'marcar_parada') {
            $resultado = $controlador->marcarParada(
                $usuarioAutenticado,
                (int) ($datos['id_ruta'] ?? 0),
                (int) ($datos['id_contenedor'] ?? 0),
                (bool) ($datos['atendido'] ?? false),
                isset($datos['kg_recolectados']) && $datos['kg_recolectados'] !== '' ? (float) $datos['kg_recolectados'] : null
            );
        } elseif ($accion === 'generar_demo') {
            $resultado = $controlador->generarRutaDemo($idCuadrilla);
        } else {
            responderJson(400, ['mensaje' => 'Acción no reconocida']);
        }

        responderResultado($resultado);
        break;

    default:
        responderJson(405, ['mensaje' => 'Método no permitido']);
}
