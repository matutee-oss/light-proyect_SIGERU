<?php

require_once __DIR__ . '/../models/Ruta.php';
require_once __DIR__ . '/../models/Incidencia.php';

class CuadrillaController
{
    private Ruta $modeloRuta;
    private Incidencia $modeloIncidencia;

    public function __construct(PDO $conexion)
    {
        $this->modeloRuta = new Ruta($conexion);
        $this->modeloIncidencia = new Incidencia($conexion);
    }

    public function obtenerPanel(int $idCuadrilla): array
    {
        $ruta = $this->modeloRuta->obtenerRutaHoy($idCuadrilla);
        $incidencias = $this->modeloIncidencia->listarParaCuadrilla($idCuadrilla);

        $atendidos = 0;
        $totalParadas = 0;
        if ($ruta) {
            $totalParadas = count($ruta['paradas']);
            foreach ($ruta['paradas'] as $parada) {
                if ($parada['atendido']) $atendidos++;
            }
        }

        return [
            'ruta'             => $ruta,
            'incidencias'      => $incidencias,
            'total_paradas'    => $totalParadas,
            'paradas_atendidas' => $atendidos,
            'restantes'        => $totalParadas - $atendidos,
        ];
    }

    public function marcarParada(int $idCuadrilla, int $idRuta, int $idContenedor, bool $atendido): array
    {
        if (!$this->modeloRuta->pertenece($idRuta, $idCuadrilla)) {
            return ['exito' => false, 'mensaje' => 'Esa ruta no está asignada a tu cuadrilla'];
        }

        $this->modeloRuta->marcarParada($idRuta, $idContenedor, $atendido);
        return ['exito' => true, 'mensaje' => $atendido ? 'Contenedor marcado como atendido' : 'Marcado como pendiente'];
    }

    public function generarRutaDemo(int $idCuadrilla): array
    {

        if ($this->modeloRuta->obtenerRutaHoy($idCuadrilla)) {
            return ['exito' => false, 'mensaje' => 'Ya tenés una ruta asignada para hoy'];
        }
        $idRuta = $this->modeloRuta->crearRutaDemo($idCuadrilla, null);
        return ['exito' => true, 'id_ruta' => $idRuta, 'mensaje' => 'Ruta de demo generada'];
    }
}
