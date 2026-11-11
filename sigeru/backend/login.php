<?php

require_once __DIR__ . '/config/cabeceras.php';
require_once __DIR__ . '/config/conexion.php';
require_once __DIR__ . '/helpers/respuesta.php';
require_once __DIR__ . '/controllers/AuthController.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderJson(405, ['mensaje' => 'Método no permitido, use POST']);
}

$datos = leerCuerpoJson();

if (empty($datos['email']) || empty($datos['password'])) {
    responderJson(400, ['mensaje' => 'Debe enviar email y password']);
}

$controlador = new AuthController(obtenerConexion());
$resultado = $controlador->login((string) $datos['email'], (string) $datos['password']);

responderJson($resultado['exito'] ? 200 : 401, $resultado);
