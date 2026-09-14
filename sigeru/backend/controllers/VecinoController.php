<?php

require_once __DIR__ . '/../models/Usuario.php';
require_once __DIR__ . '/../models/Contenedor.php';
require_once __DIR__ . '/../models/Aviso.php';
require_once __DIR__ . '/../models/Incidencia.php';
require_once __DIR__ . '/../models/Zona.php';

class VecinoController
{
    private Usuario $modeloUsuario;
    private Contenedor $modeloContenedor;
    private Aviso $modeloAviso;
    private Incidencia $modeloIncidencia;
    private Zona $modeloZona;

    public function __construct(PDO $conexion)
    {
        $this->modeloUsuario = new Usuario($conexion);
        $this->modeloContenedor = new Contenedor($conexion);
        $this->modeloAviso = new Aviso($conexion);
        $this->modeloIncidencia = new Incidencia($conexion);
        $this->modeloZona = new Zona($conexion);
    }

    public function obtenerPanel(int $idUsuario): array
    {
        $idZona = $this->modeloUsuario->obtenerZonaVecino($idUsuario);

        $contenedoresZona = $idZona
            ? $this->modeloContenedor->listarPorZona($idZona)
            : $this->modeloContenedor->listarTodos();

        return [
            'id_zona'             => $idZona,
            'zonas_disponibles'   => $this->modeloZona->listarTodas(),
            'contenedores_zona'   => $contenedoresZona,
            'avisos'              => $this->modeloAviso->listarActivos($idZona),
            'reportes_propios'    => $this->modeloIncidencia->listarPorVecino($idUsuario),
            'reportes_resueltos'  => $this->modeloIncidencia->contarResueltasPorVecino($idUsuario),
        ];
    }

    public function elegirZona(int $idUsuario, int $idZona): array
    {
        $this->modeloUsuario->asignarZonaVecino($idUsuario, $idZona);
        return ['exito' => true, 'mensaje' => 'Zona actualizada'];
    }
}
