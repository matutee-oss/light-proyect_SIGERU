<?php

/**
 * Conexión PDO a MySQL.
 *
 * Los datos se leen de variables de entorno (las define docker-compose.yml).
 * Si no existen, se usan los valores de la instalación local con XAMPP.
 */
function obtenerConexion(): PDO
{
    static $conexion = null;
    if ($conexion instanceof PDO) {
        return $conexion;
    }

    $host       = getenv('DB_HOST') ?: 'localhost';
    $puerto     = getenv('DB_PORT') ?: '3306';
    $baseDatos  = getenv('DB_NAME') ?: 'sigeru';
    $usuario    = getenv('DB_USER') ?: 'root';
    $contrasena = getenv('DB_PASS') !== false ? getenv('DB_PASS') : 'light';

    $dsn = "mysql:host={$host};port={$puerto};dbname={$baseDatos};charset=utf8mb4";

    $opciones = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        $conexion = new PDO($dsn, $usuario, $contrasena, $opciones);
        // Hora de Uruguay para que NOW() y las fechas de los informes coincidan
        $conexion->exec("SET time_zone = '-03:00'");
        return $conexion;
    } catch (PDOException $e) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['mensaje' => 'Error de conexión a la base de datos'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
