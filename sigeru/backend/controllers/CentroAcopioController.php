<?php

require_once __DIR__ . '/../helpers/validacion.php';
require_once __DIR__ . '/../models/CentroAcopio.php';

class CentroAcopioController
{
    private CentroAcopio $modelo;

    public function __construct(PDO $conexion, ?CentroAcopio $modelo = null)
    {
        $this->modelo = $modelo ?? new CentroAcopio($conexion);
    }

    public function listar(): array
    {
        $centros = $this->modelo->listarTodos();
        foreach ($centros as &$centro) {
            $centro['recursos'] = $this->modelo->listarRecursos((int) $centro['id_centro']);
            $centro['ocupacion'] = $this->modelo->calcularCapacidadUsada((int) $centro['id_centro'], (float) $centro['capacidad']);
        }
        return $centros;
    }

    public function obtener(int $id): ?array
    {
        $centro = $this->modelo->buscarPorId($id);
        if (!$centro) {
            return null;
        }
        $centro['recursos'] = $this->modelo->listarRecursos($id);
        return $centro;
    }

    private function validar(array $datos): ?string
    {
        $faltante = Validacion::faltante($datos, ['nombre', 'ubicacion', 'capacidad']);
        if ($faltante !== null) {
            return "Falta el campo obligatorio: {$faltante}";
        }
        if (!Validacion::numeroPositivo($datos['capacidad'])) {
            return 'La capacidad debe ser mayor a 0';
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
        return ['exito' => true, 'id' => $id, 'mensaje' => 'Centro de acopio creado'];
    }

    public function actualizar(int $id, array $datos): array
    {
        $actual = $this->modelo->buscarPorId($id);
        if (!$actual) {
            return ['exito' => false, 'mensaje' => 'Centro de acopio no encontrado', 'codigo' => 404];
        }
        // Corrección: antes un PUT sin algún campo dejaba nombre/ubicación en NULL y fallaba
        $datos = array_merge($actual, $datos);
        if ($error = $this->validar($datos)) {
            return ['exito' => false, 'mensaje' => $error];
        }
        $this->modelo->actualizar($id, $datos);
        return ['exito' => true, 'mensaje' => 'Centro de acopio actualizado'];
    }

    public function eliminar(int $id): array
    {
        if (!$this->modelo->buscarPorId($id)) {
            return ['exito' => false, 'mensaje' => 'Centro de acopio no encontrado', 'codigo' => 404];
        }
        // Corrección: antes fallaba con error 500 por la FK de operarios
        if ($this->modelo->contarOperarios($id) > 0) {
            return ['exito' => false, 'mensaje' => 'No se puede eliminar: hay operarios asignados a este centro. Reasignalos primero.', 'codigo' => 409];
        }
        $this->modelo->eliminar($id);
        return ['exito' => true, 'mensaje' => 'Centro de acopio eliminado'];
    }

    public function agregarRecurso(int $idCentro, array $datos): array
    {
        if (!$this->modelo->buscarPorId($idCentro)) {
            return ['exito' => false, 'mensaje' => 'Centro de acopio no encontrado', 'codigo' => 404];
        }
        if (Validacion::faltante($datos, ['tipo']) !== null || !isset($datos['cantidad'])) {
            return ['exito' => false, 'mensaje' => 'Faltan datos: tipo y cantidad'];
        }
        if (!ctype_digit((string) $datos['cantidad'])) {
            return ['exito' => false, 'mensaje' => 'La cantidad debe ser un número entero mayor o igual a 0'];
        }

        $id = $this->modelo->agregarRecurso($idCentro, $datos['tipo'], (int) $datos['cantidad']);
        return ['exito' => true, 'id' => $id, 'mensaje' => 'Recurso/maquinaria agregado'];
    }

    public function actualizarRecurso(int $idCentro, int $idRecurso, array $datos): array
    {
        $recurso = $this->modelo->buscarRecurso($idCentro, $idRecurso);
        if (!$recurso) {
            return ['exito' => false, 'mensaje' => 'Recurso no encontrado', 'codigo' => 404];
        }
        $tipo = trim((string) ($datos['tipo'] ?? $recurso['tipo']));
        $cantidad = $datos['cantidad'] ?? $recurso['cantidad'];
        if ($tipo === '' || !ctype_digit((string) $cantidad)) {
            return ['exito' => false, 'mensaje' => 'Tipo obligatorio y cantidad entera mayor o igual a 0'];
        }

        $this->modelo->editarRecurso($idCentro, $idRecurso, $tipo, (int) $cantidad);
        return ['exito' => true, 'mensaje' => 'Recurso actualizado'];
    }

    public function eliminarRecurso(int $idCentro, int $idRecurso): array
    {
        if (!$this->modelo->eliminarRecurso($idCentro, $idRecurso)) {
            return ['exito' => false, 'mensaje' => 'Recurso no encontrado', 'codigo' => 404];
        }
        return ['exito' => true, 'mensaje' => 'Recurso eliminado'];
    }
}
