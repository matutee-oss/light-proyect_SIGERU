<?php

function obtenerConexion(): PDO
{
    $host = 'localhost';
    $puerto = '3306';
    $baseDatos = 'sigeru';
    $usuario = 'root';
    $contrasena = 'light';

    $dsn = "mysql:host={$host};port={$puerto};dbname={$baseDatos};charset=utf8mb4";

    $opciones = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        return new PDO($dsn, $usuario, $contrasena, $opciones);
    }   catch (PDOException $e) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['mensaje' => 'Error de conexión a la base de datos']);
        exit;
    }
}
