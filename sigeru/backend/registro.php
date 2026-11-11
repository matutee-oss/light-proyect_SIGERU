<?php

require_once __DIR__ . '/config/cabeceras.php';
require_once __DIR__ . '/config/conexion.php';
require_once __DIR__ . '/helpers/respuesta.php';
require_once __DIR__ . '/controllers/AuthController.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderJson(405, ['mensaje' => 'Método no permitido, use POST']);
}

$controlador = new AuthController(obtenerConexion());
responderResultado($controlador->registrar(leerCuerpoJson()), 201);
