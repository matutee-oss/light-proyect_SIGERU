<?php

require_once __DIR__ . '/config/cabeceras.php';
require_once __DIR__ . '/config/conexion.php';
require_once __DIR__ . '/helpers/respuesta.php';
require_once __DIR__ . '/helpers/autenticacion.php';
require_once __DIR__ . '/models/Usuario.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderJson(405, ['mensaje' => 'Método no permitido, use POST']);
}

$conexion = obtenerConexion();
$usuarioAutenticado = requerirUsuario($conexion);

if (empty($_FILES['foto'])) {
    responderJson(400, ['mensaje' => 'No se recibió ningún archivo (campo "foto")']);
}

$archivo = $_FILES['foto'];

if ($archivo['error'] !== UPLOAD_ERR_OK) {
    responderJson(400, ['mensaje' => 'Error al subir el archivo']);
}

$tiposPermitidos = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
$tipoReal = mime_content_type($archivo['tmp_name']);

if (!isset($tiposPermitidos[$tipoReal])) {
    responderJson(400, ['mensaje' => 'Formato no permitido. Usá JPG, PNG o WEBP']);
}

$tamanoMaximoBytes = 3 * 1024 * 1024;
if ($archivo['size'] > $tamanoMaximoBytes) {
    responderJson(400, ['mensaje' => 'La imagen no puede superar los 3 MB']);
}

$carpetaDestino = __DIR__ . '/uploads/perfiles';
if (!is_dir($carpetaDestino)) {
    mkdir($carpetaDestino, 0755, true);
}

$idUsuario = (int) $usuarioAutenticado['id_usuario'];
$nombreArchivo = $idUsuario . '_' . bin2hex(random_bytes(6)) . '.' . $tiposPermitidos[$tipoReal];
$rutaDestino = $carpetaDestino . '/' . $nombreArchivo;

if (!move_uploaded_file($archivo['tmp_name'], $rutaDestino)) {
    responderJson(500, ['mensaje' => 'No se pudo guardar la imagen']);
}

$rutaRelativa = 'uploads/perfiles/' . $nombreArchivo;

$modelo = new Usuario($conexion);
$modelo->actualizarFoto($idUsuario, $rutaRelativa);

responderJson(200, ['exito' => true, 'foto' => $rutaRelativa]);
