<?php

require_once __DIR__ . '/../models/CentroAcopio.php';
require_once __DIR__ . '/../models/Camion.php';
require_once __DIR__ . '/../models/Notificacion.php';
require_once __DIR__ . '/../services/ServicioAlertas.php';

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

        if ($cantidad < 0) {
            return ['exito' => false, 'mensaje' => 'La cantidad no puede ser negativa'];
        }
        if (!$this->modelo->buscarRecurso((int) $centro['id_centro'], $idRecurso)) {
            return ['exito' => false, 'mensaje' => 'Ese recurso no pertenece a tu centro'];
        }
        $this->modelo->actualizarRecurso((int) $centro['id_centro'], $idRecurso, $cantidad);
        return ['exito' => true, 'mensaje' => 'Recurso actualizado'];
    }

    public function registrarIngreso(int $idUsuarioOperario, array $datos): array
    {
        $centro = $this->modelo->buscarPorOperario($idUsuarioOperario);
        if (!$centro) return ['exito' => false, 'mensaje' => 'No tenés un centro asignado'];

        if (empty($datos['toneladas']) || !is_numeric($datos['toneladas']) || (float) $datos['toneladas'] <= 0 || (float) $datos['toneladas'] > 50) {
            return ['exito' => false, 'mensaje' => 'Ingresá una cantidad de toneladas válida (entre 0 y 50)'];
        }
        if (!empty($datos['id_camion']) && !(new Camion($this->conexion))->buscarPorId((int) $datos['id_camion'])) {
            return ['exito' => false, 'mensaje' => 'El camión indicado no existe'];
        }

        $idCentro = (int) $centro['id_centro'];
        $antes = $this->modelo->calcularCapacidadUsada($idCentro, (float) $centro['capacidad']);

        $this->modelo->registrarIngreso(
            (int) $centro['id_centro'],
            !empty($datos['id_camion']) ? (int) $datos['id_camion'] : null,
            (float) $datos['toneladas'],
            $datos['observaciones'] ?? null
        );

        // Alerta si el centro cruza el 80 % o el 100 % de su capacidad
        $despues = $this->modelo->calcularCapacidadUsada($idCentro, (float) $centro['capacidad']);
        (new ServicioAlertas(new Notificacion($this->conexion)))
            ->capacidadCentro($centro, (float) $antes['porcentaje'], (float) $despues['porcentaje']);

        return ['exito' => true, 'mensaje' => 'Ingreso registrado', 'ocupacion' => $despues['porcentaje']];
    }
}
