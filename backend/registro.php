<?php

require_once __DIR__ . '/config/cabeceras.php';
require_once __DIR__ . '/config/conexion.php';
require_once __DIR__ . '/helpers/respuesta.php';
require_once __DIR__ . '/controllers/AuthController.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderJson(405, ['mensaje' => 'Método no permitido, use POST']);
}

$datos = leerCuerpoJson();

$camposObligatorios = ['nombre', 'apellido', 'ci', 'email', 'password', 'tipo_usuario'];
foreach ($camposObligatorios as $campo) {
    if (empty($datos[$campo])) {
        responderJson(400, ['mensaje' => "Falta el campo obligatorio: {$campo}"]);
    }
}

$tiposValidos = ['administrador', 'cuadrilla', 'operario', 'vecino'];
if (!in_array($datos['tipo_usuario'], $tiposValidos, true)) {
    responderJson(400, ['mensaje' => 'tipo_usuario inválido']);
}

if ($datos['tipo_usuario'] === 'operario' && empty($datos['id_centro'])) {
    responderJson(400, ['mensaje' => 'Los operarios deben indicar id_centro (centro de acopio donde trabajan)']);
}

$rolesInternos = ['administrador', 'cuadrilla', 'operario'];
if (in_array($datos['tipo_usuario'], $rolesInternos, true) && empty($datos['codigo_funcionario'])) {
    responderJson(400, ['mensaje' => 'Este rol requiere un código de funcionario']);
}

$conexion = obtenerConexion();
$controlador = new AuthController($conexion);
$resultado = $controlador->registrar($datos);

responderJson($resultado['exito'] ? 201 : 409, $resultado);
