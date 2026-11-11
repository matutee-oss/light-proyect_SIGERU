<?php

require_once __DIR__ . '/../models/CentroAcopio.php';

class OperarioController
{
    private CentroAcopio $modelo;
    private PDO $conexion;

    public function __construct(PDO $conexion)
    {
        $this->conexion = $conexion;
        $this->modelo = new CentroAcopio($conexion);
    }

    public function obtenerPanel(int $idUsuarioOperario): array
    {
        $centro = $this->modelo->buscarPorOperario($idUsuarioOperario);

        if (!$centro) {
            return ['exito' => false, 'mensaje' => 'No tenés un centro de acopio asignado todavía'];
        }

        $idCentro = (int) $centro['id_centro'];
        $capacidad = $this->modelo->calcularCapacidadUsada($idCentro, (float) $centro['capacidad']);

        $consulta = $this->conexion->query(
            "SELECT id_camion, matricula FROM camiones ORDER BY matricula"
        );
        $camiones = $consulta->fetchAll();

        return [
            'exito'             => true,
            'centro'            => $centro,
            'recursos'          => $this->modelo->listarRecursos($idCentro),
            'capacidad'         => $capacidad,
            'ingresos_recientes' => $this->modelo->listarIngresosRecientes($idCentro),
            'ingresos_por_dia'  => $this->modelo->ingresosPorDia($idCentro, 7),
            'camiones'          => $camiones,
        ];
    }

    public function actualizarRecurso(int $idUsuarioOperario, int $idRecurso, int $cantidad): array
    {
        $centro = $this->modelo->buscarPorOperario($idUsuarioOperario);
        if (!$centro) return ['exito' => false, 'mensaje' => 'No tenés un centro asignado'];

        $this->modelo->actualizarRecurso((int) $centro['id_centro'], $idRecurso, $cantidad);
        return ['exito' => true, 'mensaje' => 'Recurso actualizado'];
    }

    public function registrarIngreso(int $idUsuarioOperario, array $datos): array
    {
        $centro = $this->modelo->buscarPorOperario($idUsuarioOperario);
        if (!$centro) return ['exito' => false, 'mensaje' => 'No tenés un centro asignado'];

        if (empty($datos['toneladas']) || (float) $datos['toneladas'] <= 0) {
            return ['exito' => false, 'mensaje' => 'Ingresá una cantidad de toneladas válida'];
        }

        $this->modelo->registrarIngreso(
            (int) $centro['id_centro'],
            !empty($datos['id_camion']) ? (int) $datos['id_camion'] : null,
            (float) $datos['toneladas'],
            $datos['observaciones'] ?? null
        );

        return ['exito' => true, 'mensaje' => 'Ingreso registrado'];
    }
}
