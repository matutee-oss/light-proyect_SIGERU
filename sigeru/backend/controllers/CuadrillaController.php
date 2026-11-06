<?php

require_once __DIR__ . '/../models/Ruta.php';
require_once __DIR__ . '/../models/Incidencia.php';
require_once __DIR__ . '/RecoleccionController.php';

class CuadrillaController
{
    private Ruta $modeloRuta;
    private Incidencia $modeloIncidencia;
    private RecoleccionController $recolecciones;

    public function __construct(PDO $conexion)
    {
        $this->modeloRuta = new Ruta($conexion);
        $this->modeloIncidencia = new Incidencia($conexion);
        $this->recolecciones = new RecoleccionController($conexion);
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

    /**
     * Al marcar una parada como atendida se registra la recolección (API de
     * recolección): el contenedor queda vacío y se resuelven sus desbordes.
     */
    public function marcarParada(array $cuadrilla, int $idRuta, int $idContenedor, bool $atendido, ?float $kg = null): array
    {
        $idCuadrilla = (int) $cuadrilla['id_usuario'];
        if (!$this->modeloRuta->pertenece($idRuta, $idCuadrilla)) {
            return ['exito' => false, 'mensaje' => 'Esa ruta no está asignada a tu cuadrilla'];
        }

        if ($atendido) {
            $resultado = $this->recolecciones->registrar($cuadrilla, [
                'id_contenedor'   => $idContenedor,
                'id_ruta'         => $idRuta,
                'kg_recolectados' => $kg,
            ]);
            // Si ya estaba registrada (se destildó y volvió a tildar) alcanza con marcar la parada
            if (!$resultado['exito'] && ($resultado['codigo'] ?? 0) !== 409) {
                return $resultado;
            }
            $this->modeloRuta->marcarParada($idRuta, $idContenedor, true);
            return ['exito' => true, 'mensaje' => $resultado['exito'] ? $resultado['mensaje'] : 'Contenedor marcado como atendido'];
        }

        $this->modeloRuta->marcarParada($idRuta, $idContenedor, false);
        return ['exito' => true, 'mensaje' => 'Marcado como pendiente'];
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
