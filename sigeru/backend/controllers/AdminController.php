<?php

require_once __DIR__ . '/../models/Usuario.php';
require_once __DIR__ . '/../models/Contenedor.php';
require_once __DIR__ . '/../models/Camion.php';
require_once __DIR__ . '/../models/Zona.php';
require_once __DIR__ . '/../models/Incidencia.php';
require_once __DIR__ . '/../models/Aviso.php';
require_once __DIR__ . '/../models/CentroAcopio.php';
require_once __DIR__ . '/../models/Notificacion.php';
require_once __DIR__ . '/../services/ServicioAlertas.php';

class AdminController
{
    private Usuario $modeloUsuario;
    private Contenedor $modeloContenedor;
    private Camion $modeloCamion;
    private Zona $modeloZona;
    private Incidencia $modeloIncidencia;
    private Aviso $modeloAviso;
    private PDO $conexion;
    private CentroAcopio $modeloCentro;
    private ServicioAlertas $alertas;

    public function __construct(PDO $conexion)
    {
        $this->conexion = $conexion;
        $this->modeloUsuario = new Usuario($conexion);
        $this->modeloContenedor = new Contenedor($conexion);
        $this->modeloCamion = new Camion($conexion);
        $this->modeloZona = new Zona($conexion);
        $this->modeloIncidencia = new Incidencia($conexion);
        $this->modeloAviso = new Aviso($conexion);
        $this->modeloCentro = new CentroAcopio($conexion);
        $this->alertas = new ServicioAlertas(new Notificacion($conexion));
    }

    public function obtenerResumen(): array
    {
        $usuarios = $this->modeloUsuario->listarTodos();
        $contenedores = $this->modeloContenedor->listarTodos();
        $camiones = $this->modeloCamion->listarTodos();
        $zonas = $this->modeloZona->listarTodas();

        $centros = $this->modeloCentro->listarTodos();

        return [
            'estadisticas' => [
                'usuarios'            => count($usuarios),
                'contenedores'        => count($contenedores),
                'camiones'            => count($camiones),
                'zonas'               => count($zonas),
                'incidencias_abiertas' => $this->modeloIncidencia->contarAbiertas(),
                'contenedores_criticos' => count(array_filter($contenedores, fn($c) => $c['estado'] === 'desbordado' || (int) $c['nivel_llenado'] >= 80)),
            ],
            'progreso_semanal' => $this->modeloIncidencia->progresoSemanal(),
            'ranking_zonas'    => $this->modeloIncidencia->rankingZonas(),
            'conteo_tipos'     => $this->modeloIncidencia->conteoPorTipo(),
            'feed_actividad'   => $this->modeloIncidencia->feedActividad(),
            'contenedores'     => $contenedores,
            'camiones_mapa'    => $this->modeloCamion->listarGeolocalizados(),
            'centros'          => $centros,
            'usuarios'         => $usuarios,
            'zonas'            => $zonas,
            'pendientes'       => $this->modeloUsuario->listarPendientes(),
            'avisos'           => $this->modeloAviso->listarActivos(),
        ];
    }

    public function aprobarUsuario(int $idUsuario): array
    {
        if (!$this->modeloUsuario->buscarPorId($idUsuario)) {
            return ['exito' => false, 'mensaje' => 'Usuario no encontrado'];
        }
        $this->modeloUsuario->actualizarEstado($idUsuario, 'activo');
        return ['exito' => true, 'mensaje' => 'Usuario aprobado'];
    }

    public function rechazarUsuario(int $idUsuario): array
    {
        if (!$this->modeloUsuario->buscarPorId($idUsuario)) {
            return ['exito' => false, 'mensaje' => 'Usuario no encontrado'];
        }
        $this->modeloUsuario->actualizarEstado($idUsuario, 'inactivo');
        return ['exito' => true, 'mensaje' => 'Usuario rechazado'];
    }

    public function desactivarAviso(int $idAviso): array
    {
        if (!$this->modeloAviso->desactivar($idAviso)) {
            return ['exito' => false, 'mensaje' => 'Aviso no encontrado'];
        }
        return ['exito' => true, 'mensaje' => 'Aviso retirado'];
    }

    public function publicarAviso(string $titulo, string $mensaje, ?int $idZona): array
    {
        if (trim($titulo) === '' || trim($mensaje) === '') {
            return ['exito' => false, 'mensaje' => 'Título y mensaje son obligatorios'];
        }
        $titulo = trim($titulo);
        $mensaje = trim($mensaje);
        if (mb_strlen($titulo) > 150) {
            return ['exito' => false, 'mensaje' => 'El título no puede superar los 150 caracteres'];
        }
        $id = $this->modeloAviso->crear($titulo, $mensaje, $idZona);
        // Sistema de notificaciones: el aviso le llega a la campanita de cada vecino de la zona
        $notificados = $this->alertas->avisoPublicado($titulo, $mensaje, $idZona);
        return ['exito' => true, 'id' => $id, 'notificados' => $notificados, 'mensaje' => "Aviso publicado y enviado a {$notificados} vecino(s)"];
    }
}
