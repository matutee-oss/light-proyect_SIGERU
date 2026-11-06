<?php
/**
 * Panel de control con estadísticas (solo administradores).
 *
 * GET /estadisticas.php            Mes en curso
 * GET /estadisticas.php?mes=2026-10
 */

require_once __DIR__ . '/config/cabeceras.php';
require_once __DIR__ . '/config/conexion.php';
require_once __DIR__ . '/helpers/respuesta.php';
require_once __DIR__ . '/helpers/autenticacion.php';
require_once __DIR__ . '/controllers/EstadisticaController.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    responderJson(405, ['mensaje' => 'Método no permitido, use GET']);
}

$conexion = obtenerConexion();
$usuario = requerirUsuario($conexion);
requerirRol($usuario, ['administrador']);

$controlador = new EstadisticaController($conexion);
responderResultado($controlador->panel($_GET['mes'] ?? null));
