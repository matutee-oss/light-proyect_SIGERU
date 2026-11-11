<?php

require_once __DIR__ . '/../models/Contenedor.php';

class ContenedorController
{
    private Contenedor $modelo;

    public function __construct(PDO $conexion)
    {
        $this->modelo = new Contenedor($conexion);
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
        if (empty($datos['ubicacion']) || empty($datos['tipo_residuo'])) {
            return ['exito' => false, 'mensaje' => 'Faltan datos obligatorios (ubicacion, tipo_residuo)'];
        }

        $id = $this->modelo->crear($datos);
        return ['exito' => true, 'id' => $id, 'mensaje' => 'Contenedor creado'];
    }

    public function actualizar(int $id, array $datos): array
    {
        if (!$this->modelo->buscarPorId($id)) {
            return ['exito' => false, 'mensaje' => 'Contenedor no encontrado'];
        }

        $this->modelo->actualizar($id, $datos);
        return ['exito' => true, 'mensaje' => 'Contenedor actualizado'];
    }

    public function eliminar(int $id): array
    {
        if (!$this->modelo->buscarPorId($id)) {
            return ['exito' => false, 'mensaje' => 'Contenedor no encontrado'];
        }

        $this->modelo->eliminar($id);
        return ['exito' => true, 'mensaje' => 'Contenedor eliminado'];
    }
}
