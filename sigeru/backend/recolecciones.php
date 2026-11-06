<?php
/**
 * API de recolección (administradores y cuadrillas).
 *
 * GET    /recolecciones.php                          Historial (filtros: id_contenedor, id_camion, id_zona, desde, hasta)
 * GET    /recolecciones.php?id=3                     Detalle de una recolección
 * GET    /recolecciones.php?accion=resumen           Kilos por día (14 días) y totales del mes
 * GET    /recolecciones.php?accion=contenedores      Contenedores ordenados por nivel (para elegir qué recolectar)
 * GET    /recolecciones.php?accion=camiones          Camiones operativos (para el formulario)
 * POST   /recolecciones.php                          Registrar {id_contenedor, kg_recolectados?, id_camion?, id_ruta?, observaciones?}
 * PUT    /recolecciones.php?accion=nivel&id_contenedor=4   Actualizar nivel de llenado {nivel}
 * DELETE /recolecciones.php?id=3                     Eliminar registro — admin
 */

require_once __DIR__ . '/config/cabeceras.php';
require_once __DIR__ . '/config/conexion.php';
require_once __DIR__ . '/helpers/respuesta.php';
require_once __DIR__ . '/helpers/autenticacion.php';
require_once __DIR__ . '/controllers/RecoleccionController.php';

$conexion = obtenerConexion();
$usuario = requerirUsuario($conexion);
requerirRol($usuario, ['administrador', 'cuadrilla']);

$controlador = new RecoleccionController($conexion);
$metodo = $_SERVER['REQUEST_METHOD'];
$id = parametroEntero('id');
$accion = $_GET['accion'] ?? '';

switch ($metodo) {
    case 'GET':
        if ($accion === 'resumen') {
            responderJson(200, $controlador->resumen());
        }
        if ($accion === 'contenedores') {
            responderJson(200, (new Contenedor($conexion))->listarConFiltros($_GET));
        }
        if ($accion === 'camiones') {
            $camiones = array_values(array_filter(
                (new Camion($conexion))->listarTodos(),
                fn($c) => in_array($c['estado'], ['disponible', 'en_ruta'], true)
            ));
            responderJson(200, $camiones);
        }
        if ($id) {
            $recoleccion = $controlador->obtener($id);
            $recoleccion
                ? responderJson(200, $recoleccion)
                : responderJson(404, ['mensaje' => 'Recolección no encontrada']);
        }
        responderJson(200, $controlador->listar($usuario, $_GET));
        break;

    case 'POST':
        responderResultado($controlador->registrar($usuario, leerCuerpoJson()), 201);
        break;

    case 'PUT':
        if ($accion !== 'nivel') {
            responderJson(400, ['mensaje' => 'Acción no reconocida']);
        }
        $idContenedor = parametroEntero('id_contenedor');
        if (!$idContenedor) {
            responderJson(400, ['mensaje' => 'Debe indicar ?id_contenedor=']);
        }
        $datos = leerCuerpoJson();
        responderResultado($controlador->actualizarNivel($idContenedor, $datos['nivel'] ?? null, $usuario));
        break;

    case 'DELETE':
        if (!$id) {
            responderJson(400, ['mensaje' => 'Debe indicar ?id= de la recolección']);
        }
        responderResultado($controlador->eliminar($id, $usuario));
        break;

    default:
        responderJson(405, ['mensaje' => 'Método no permitido']);
}
