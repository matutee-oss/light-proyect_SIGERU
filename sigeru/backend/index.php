<?php

require_once __DIR__ . '/config/cabeceras.php';
require_once __DIR__ . '/helpers/respuesta.php';

responderJson(200, [
    'sistema'   => 'SiGeRU - API de Gestión de Residuos Urbanos',
    'version'   => '3.0',
    'endpoints' => [
        'POST   /login.php'                       => 'Iniciar sesión',
        'POST   /logout.php'                      => 'Cerrar sesión (invalida el token)',
        'POST   /registro.php'                    => 'Registrar nuevo usuario (queda pendiente de aprobación)',
        'GET    /zonas.php'                       => 'Listado de zonas (público)',
        'GET|PUT /perfil.php'                     => 'Perfil propio',
        'POST   /perfil_foto.php'                 => 'Subir foto de perfil',
        'GET|POST|PUT|DELETE /usuarios.php'       => 'ABM de usuarios — administradores',
        'GET|POST|PUT|DELETE /contenedores.php'   => 'ABM de contenedores — administradores',
        'GET|POST|PUT|DELETE /camiones.php'       => 'ABM de camiones — administradores',
        'GET|POST|PUT|DELETE /centros_acopio.php' => 'ABM de centros de acopio y maquinaria',
        'GET|POST|PUT|DELETE /incidencias.php'    => 'Registro y seguimiento de incidencias',
        'GET|POST|PUT|DELETE /recolecciones.php'  => 'API de recolección — administradores y cuadrillas',
        'GET    /mapa.php'                        => 'Mapa interactivo de contenedores e incidencias',
        'GET    /informes.php'                    => 'Informes de incidencias y recolecciones (JSON o CSV) — administradores',
        'GET    /estadisticas.php'                => 'Panel de control con estadísticas — administradores',
        'GET|PUT|DELETE /notificaciones.php'      => 'Notificaciones y alertas del usuario',
        'GET|POST /panel_admin.php'               => 'Panel de administración',
        'GET|POST /panel_vecino.php'              => 'Panel de vecino',
        'GET|POST /panel_cuadrilla.php'           => 'Panel de cuadrilla',
        'GET|POST|PUT /panel_operario.php'        => 'Panel de operario',
    ],
]);
