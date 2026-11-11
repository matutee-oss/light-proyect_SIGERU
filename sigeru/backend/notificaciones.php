<?php
/**
 * Notificaciones y alertas del usuario autenticado (todos los roles).
 *
 * GET    /notificaciones.php                   Últimas notificaciones + cantidad sin leer (?no_leidas=1 para filtrar)
 * GET    /notificaciones.php?desde=120         Solo las nuevas desde ese id (consulta periódica del frontend)
 * PUT    /notificaciones.php?id=5              Marcar una como leída
 * PUT    /notificaciones.php?accion=todas      Marcar todas como leídas
 * DELETE /notificaciones.php?id=5              Eliminar una notificación propia
 */

require_once __DIR__ . '/config/cabeceras.php';
require_once __DIR__ . '/config/conexion.php';
require_once __DIR__ . '/helpers/respuesta.php';
require_once __DIR__ . '/helpers/autenticacion.php';
require_once __DIR__ . '/controllers/NotificacionController.php';

$conexion = obtenerConexion();
$usuario = requerirUsuario($conexion);
$idUsuario = (int) $usuario['id_usuario'];

$controlador = new NotificacionController($conexion);
$id = parametroEntero('id');

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        $desde = parametroEntero('desde');
        if ($desde !== null) {
            responderJson(200, $controlador->nuevas($idUsuario, $desde));
        }
        responderJson(200, $controlador->listar($idUsuario, !empty($_GET['no_leidas']), parametroEntero('limite') ?? 30));
        break;

    case 'PUT':
        if (($_GET['accion'] ?? '') === 'todas') {
            responderResultado($controlador->marcarTodas($idUsuario));
        }
        responderResultado($controlador->marcarLeida($id ?? 0, $idUsuario));
        break;

    case 'DELETE':
        responderResultado($controlador->eliminar($id ?? 0, $idUsuario));
        break;

    default:
        responderJson(405, ['mensaje' => 'Método no permitido']);
}
