<?php
/**
 * Mapa interactivo con contenedores e incidencias.
 *
 * GET /mapa.php   Filtros opcionales: id_zona, estado_contenedor, nivel_minimo,
 *                 tipo, prioridad, estado_incidencia (por defecto solo incidencias pendientes)
 */

require_once __DIR__ . '/config/cabeceras.php';
require_once __DIR__ . '/config/conexion.php';
require_once __DIR__ . '/helpers/respuesta.php';
require_once __DIR__ . '/helpers/autenticacion.php';
require_once __DIR__ . '/controllers/MapaController.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    responderJson(405, ['mensaje' => 'Método no permitido, use GET']);
}

$conexion = obtenerConexion();
$usuario = requerirUsuario($conexion);
requerirRol($usuario, ['administrador', 'cuadrilla', 'vecino']);

responderJson(200, (new MapaController($conexion))->obtener($usuario, $_GET));
