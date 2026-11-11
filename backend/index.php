<?php

require_once __DIR__ . '/config/cabeceras.php';
require_once __DIR__ . '/helpers/respuesta.php';

responderJson(200, [
    'sistema'   => 'SiGeRU - API de Gestión de Residuos Urbanos',
    'version'   => '2.0',
    'endpoints' => [
        'POST   /login.php'                         => 'Iniciar sesión',
        'POST   /registro.php'                      => 'Registrar nuevo usuario (queda pendiente de aprobación)',
        'GET    /perfil.php'                        => 'Obtener perfil propio (requiere auth)',
        'PUT    /perfil.php'                        => 'Actualizar perfil propio (requiere auth)',
        'GET|POST|PUT|DELETE /usuarios.php'         => 'ABM de usuarios — solo administradores',
        'GET|POST|PUT|DELETE /contenedores.php'     => 'ABM de contenedores — solo administradores',
        'GET|POST|PUT|DELETE /camiones.php'         => 'ABM de camiones (flota) — solo administradores',
        'GET|POST|PUT|DELETE /centros_acopio.php'   => 'ABM de centros de acopio y maquinaria',
        'GET|POST /panel_admin.php'                 => 'Panel de administración — solo administradores',
        'GET|POST /panel_vecino.php'                => 'Panel de vecino — solo vecinos',
        'GET     /panel_cuadrilla.php'              => 'Panel de cuadrilla — solo cuadrillas',
        'GET     /panel_operario.php'               => 'Panel de operario — solo operarios',
        'GET|POST /incidencias.php'                 => 'Reportar y gestionar incidencias',
    ],
]);
