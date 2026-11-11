<?php
/**
 * Bootstrap de PHPUnit.
 * Carga el autoload de Composer y todas las clases del backend.
 */

date_default_timezone_set('America/Montevideo');

$autoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}

foreach (['helpers', 'models', 'services', 'controllers'] as $carpeta) {
    foreach (glob(__DIR__ . "/../{$carpeta}/*.php") as $archivo) {
        // respuesta.php y autenticacion.php cortan la ejecución con exit(): no se usan en los tests
        if (in_array(basename($archivo), ['respuesta.php', 'autenticacion.php'], true)) {
            continue;
        }
        require_once $archivo;
    }
}

require_once __DIR__ . '/Integracion/BaseDatosTestCase.php';
