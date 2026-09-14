<?php

require_once __DIR__ . '/../models/Camion.php';

class CamionController
{
    private Camion $modelo;

    public function __construct(PDO $conexion)
    {
        $this->modelo = new Camion($conexion);
    }

    public function listar(): array
    {
        return $this->modelo->listarTodos();
    }

    public function obtener(int $id): ?array
    {
        return $this->modelo->buscarPorId($id);
    }

    public function crear(array $datos): array
    {
        if (empty($datos['matricula'])) {
            return ['exito' => false, 'mensaje' => 'Falta el dato obligatorio: matricula'];
        }

        $id = $this->modelo->crear($datos);
        return ['exito' => true, 'id' => $id, 'mensaje' => 'Camión creado'];
    }

    public function actualizar(int $id, array $datos): array
    {
        if (!$this->modelo->buscarPorId($id)) {
            return ['exito' => false, 'mensaje' => 'Camión no encontrado'];
        }

        $this->modelo->actualizar($id, $datos);
        return ['exito' => true, 'mensaje' => 'Camión actualizado'];
    }

    public function eliminar(int $id): array
    {
        if (!$this->modelo->buscarPorId($id)) {
            return ['exito' => false, 'mensaje' => 'Camión no encontrado'];
        }

        $this->modelo->eliminar($id);
        return ['exito' => true, 'mensaje' => 'Camión eliminado'];
    }
}
