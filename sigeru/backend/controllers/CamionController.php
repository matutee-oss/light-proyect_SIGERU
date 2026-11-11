<?php

require_once __DIR__ . '/../helpers/validacion.php';
require_once __DIR__ . '/../models/Camion.php';

class CamionController
{
    private Camion $modelo;

    public function __construct(PDO $conexion, ?Camion $modelo = null)
    {
        $this->modelo = $modelo ?? new Camion($conexion);
    }

    public function listar(): array
    {
        return $this->modelo->listarTodos();
    }

    public function obtener(int $id): ?array
    {
        return $this->modelo->buscarPorId($id);
    }

    /** Validaciones comunes a alta y edición. Devuelve el mensaje de error o null. */
    private function validar(array $datos, ?int $idActual = null): ?string
    {
        if (empty($datos['matricula'])) {
            return 'Falta el dato obligatorio: matricula';
        }
        if (!Validacion::matricula($datos['matricula'])) {
            return 'Matrícula inválida (formato esperado: ABC 1234)';
        }
        $existente = $this->modelo->buscarPorMatricula($datos['matricula']);
        if ($existente && (int) $existente['id_camion'] !== $idActual) {
            return 'Ya existe un camión con esa matrícula';
        }
        if (isset($datos['estado']) && !Validacion::enLista($datos['estado'], Validacion::ESTADOS_CAMION)) {
            return 'Estado de camión inválido';
        }
        if (isset($datos['capacidad_kg']) && $datos['capacidad_kg'] !== '' && !Validacion::numeroPositivo($datos['capacidad_kg'])) {
            return 'La capacidad debe ser un número mayor a 0';
        }
        if (!Validacion::coordenadas($datos['latitud'] ?? null, $datos['longitud'] ?? null)) {
            return 'Coordenadas inválidas: indicá latitud y longitud, o dejá ambas vacías';
        }
        return null;
    }

    public function crear(array $datos): array
    {
        if ($error = $this->validar($datos)) {
            return ['exito' => false, 'mensaje' => $error];
        }

        $id = $this->modelo->crear($datos);
        return ['exito' => true, 'id' => $id, 'mensaje' => 'Camión creado'];
    }

    public function actualizar(int $id, array $datos): array
    {
        $actual = $this->modelo->buscarPorId($id);
        if (!$actual) {
            return ['exito' => false, 'mensaje' => 'Camión no encontrado', 'codigo' => 404];
        }

        $datos = array_merge($actual, $datos);
        if ($error = $this->validar($datos, $id)) {
            return ['exito' => false, 'mensaje' => $error];
        }

        $this->modelo->actualizar($id, $datos);
        return ['exito' => true, 'mensaje' => 'Camión actualizado'];
    }

    public function eliminar(int $id): array
    {
        if (!$this->modelo->buscarPorId($id)) {
            return ['exito' => false, 'mensaje' => 'Camión no encontrado', 'codigo' => 404];
        }

        $this->modelo->eliminar($id);
        return ['exito' => true, 'mensaje' => 'Camión eliminado'];
    }
}
