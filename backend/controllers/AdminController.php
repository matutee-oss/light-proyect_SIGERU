<?php

require_once __DIR__ . '/../models/Usuario.php';
require_once __DIR__ . '/../models/CodigoFuncionario.php';
require_once __DIR__ . '/../models/Contenedor.php';
require_once __DIR__ . '/../models/Camion.php';
require_once __DIR__ . '/../models/Zona.php';
require_once __DIR__ . '/../models/Incidencia.php';
require_once __DIR__ . '/../models/Aviso.php';

class AdminController
{
    private Usuario $modeloUsuario;
    private Contenedor $modeloContenedor;
    private Camion $modeloCamion;
    private Zona $modeloZona;
    private Incidencia $modeloIncidencia;
    private Aviso $modeloAviso;
    private PDO $conexion;
    private CodigoFuncionario $modeloCodigo;

    public function __construct(PDO $conexion)
    {
        $this->conexion = $conexion;
        $this->modeloUsuario = new Usuario($conexion);
        $this->modeloContenedor = new Contenedor($conexion);
        $this->modeloCamion = new Camion($conexion);
        $this->modeloZona = new Zona($conexion);
        $this->modeloIncidencia = new Incidencia($conexion);
        $this->modeloAviso = new Aviso($conexion);
        $this->modeloCodigo = new CodigoFuncionario($conexion);
    }

    public function obtenerResumen(): array
    {
        $usuarios = $this->modeloUsuario->listarTodos();
        $contenedores = $this->modeloContenedor->listarTodos();
        $camiones = $this->modeloCamion->listarTodos();
        $zonas = $this->modeloZona->listarTodas();

        $consulta = $this->conexion->query('SELECT * FROM centros_acopio');
        $centros = $consulta->fetchAll();

        return [
            'estadisticas' => [
                'usuarios'            => count($usuarios),
                'contenedores'        => count($contenedores),
                'camiones'            => count($camiones),
                'zonas'               => count($zonas),
                'incidencias_abiertas' => $this->modeloIncidencia->contarAbiertas(),
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
            'codigos'          => $this->modeloCodigo->listar(),
            'avisos'           => $this->modeloAviso->listarActivos(),
        ];
    }

    public function generarCodigo(string $tipoUsuario, int $generadoPor): array
    {
        $tipos = ['administrador', 'operario', 'cuadrilla'];
        if (!in_array($tipoUsuario, $tipos)) {
            return ['exito' => false, 'mensaje' => 'Tipo inválido'];
        }
        $codigo = $this->modeloCodigo->generar($tipoUsuario, $generadoPor);
        return ['exito' => true, 'codigo' => $codigo];
    }

    public function listarCodigos(): array
    {
        return $this->modeloCodigo->listar();
    }

    public function aprobarUsuario(int $idUsuario): array
    {
        $this->modeloUsuario->actualizarEstado($idUsuario, 'activo');
        return ['exito' => true, 'mensaje' => 'Usuario aprobado'];
    }

    public function rechazarUsuario(int $idUsuario): array
    {
        $this->modeloUsuario->actualizarEstado($idUsuario, 'inactivo');
        return ['exito' => true, 'mensaje' => 'Usuario rechazado'];
    }

    public function publicarAviso(string $titulo, string $mensaje, ?int $idZona): array
    {
        if (empty($titulo) || empty($mensaje)) {
            return ['exito' => false, 'mensaje' => 'Título y mensaje son obligatorios'];
        }
        $id = $this->modeloAviso->crear($titulo, $mensaje, $idZona);
        return ['exito' => true, 'id' => $id, 'mensaje' => 'Aviso publicado'];
    }
}
