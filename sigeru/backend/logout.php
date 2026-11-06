<?php
/**
 * Cierra la sesión actual borrando el token de la base.
 * Antes el "cerrar sesión" solo limpiaba el navegador y el token seguía siendo válido.
 *
 * POST /logout.php
 */

require_once __DIR__ . '/config/cabeceras.php';
require_once __DIR__ . '/config/conexion.php';
require_once __DIR__ . '/helpers/respuesta.php';
require_once __DIR__ . '/helpers/autenticacion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderJson(405, ['mensaje' => 'Método no permitido, use POST']);
}

$token = obtenerTokenDeCabecera();
if ($token !== null) {
    $consulta = obtenerConexion()->prepare('DELETE FROM sesiones WHERE token = :token');
    $consulta->execute(['token' => $token]);
}

responderJson(200, ['exito' => true, 'mensaje' => 'Sesión cerrada']);
