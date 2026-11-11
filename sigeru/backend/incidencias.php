<?php
/**
 * Registro y seguimiento de incidencias.
 *
 * GET    /incidencias.php                         Listado según el rol (filtros: estado, tipo, prioridad, id_zona, id_contenedor, desde, hasta, texto)
 * GET    /incidencias.php?id=5                    Detalle + historial de seguimiento
 * GET    /incidencias.php?accion=cuadrillas       Cuadrillas activas (para asignar) — admin
 * POST   /incidencias.php                         Reportar {id_contenedor, tipo, descripcion?, prioridad?}
 * POST   /incidencias.php?id=5&accion=comentar    Agregar comentario {comentario}
 * PUT    /incidencias.php?id=5&accion=tomar       La cuadrilla toma la incidencia
 * PUT    /incidencias.php?id=5&accion=estado      Cambiar estado {estado, comentario?}
 * PUT    /incidencias.php?id=5&accion=asignar     Asignar cuadrilla {id_cuadrilla} — admin
 * PUT    /incidencias.php?id=5&accion=prioridad   Cambiar prioridad {prioridad} — admin
 * DELETE /incidencias.php?id=5                    Eliminar — admin
 */

require_once __DIR__ . '/config/cabeceras.php';
require_once __DIR__ . '/config/conexion.php';
require_once __DIR__ . '/helpers/respuesta.php';
require_once __DIR__ . '/helpers/autenticacion.php';
require_once __DIR__ . '/controllers/IncidenciaController.php';

$conexion = obtenerConexion();
$usuario = requerirUsuario($conexion);

$controlador = new IncidenciaController($conexion);
$metodo = $_SERVER['REQUEST_METHOD'];
$id = parametroEntero('id');
$accion = $_GET['accion'] ?? '';

switch ($metodo) {
    case 'GET':
        if ($accion === 'cuadrillas') {
            requerirRol($usuario, ['administrador']);
            responderJson(200, (new Usuario($conexion))->listarCuadrillasActivas());
        }
        if ($id) {
            $incidencia = $controlador->obtener($id, $usuario);
            $incidencia
                ? responderJson(200, $incidencia)
                : responderJson(404, ['mensaje' => 'Incidencia no encontrada']);
        }
        responderJson(200, $controlador->listarSegunRol($usuario, $_GET));
        break;

    case 'POST':
        $datos = leerCuerpoJson();

        // Compatibilidad con la 2da entrega (antes todo iba por POST)
        if ($accion === 'tomar' && $id) {
            responderResultado($controlador->tomar($id, $usuario));
        }
        if ($accion === 'cambiar_estado' && $id) {
            responderResultado($controlador->cambiarEstado($id, $datos['estado'] ?? '', $usuario, $datos['comentario'] ?? null));
        }
        if ($accion === 'comentar' && $id) {
            responderResultado($controlador->comentar($id, $usuario, (string) ($datos['comentario'] ?? '')), 201);
        }

        responderResultado($controlador->reportar($usuario, $datos), 201);
        break;

    case 'PUT':
        if (!$id) {
            responderJson(400, ['mensaje' => 'Debe indicar ?id= de la incidencia']);
        }
        $datos = leerCuerpoJson();

        switch ($accion) {
            case 'tomar':
                responderResultado($controlador->tomar($id, $usuario));
                break;
            case 'estado':
            case 'cambiar_estado':
                responderResultado($controlador->cambiarEstado($id, $datos['estado'] ?? '', $usuario, $datos['comentario'] ?? null));
                break;
            case 'asignar':
                responderResultado($controlador->asignar($id, (int) ($datos['id_cuadrilla'] ?? 0), $usuario));
                break;
            case 'prioridad':
                responderResultado($controlador->cambiarPrioridad($id, $datos['prioridad'] ?? '', $usuario));
                break;
            default:
                responderJson(400, ['mensaje' => 'Acción no reconocida']);
        }
        break;

    case 'DELETE':
        if (!$id) {
            responderJson(400, ['mensaje' => 'Debe indicar ?id= de la incidencia']);
        }
        responderResultado($controlador->eliminar($id, $usuario));
        break;

    default:
        responderJson(405, ['mensaje' => 'Método no permitido']);
}
