<?php
/**
 * Zonas (municipios). Pública: la usa también el formulario de registro.
 * GET /zonas.php
 */

require_once __DIR__ . '/config/cabeceras.php';
require_once __DIR__ . '/config/conexion.php';
require_once __DIR__ . '/helpers/respuesta.php';
require_once __DIR__ . '/models/Zona.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    responderJson(405, ['mensaje' => 'Método no permitido, use GET']);
}

responderJson(200, (new Zona(obtenerConexion()))->listarTodas());
