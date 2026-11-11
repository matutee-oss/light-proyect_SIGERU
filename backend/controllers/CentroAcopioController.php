<?php

require_once __DIR__ . '/../models/CentroAcopio.php';

class CentroAcopioController
{
    private CentroAcopio $modelo;
    private PDO $conexion;

    public function __construct(PDO $conexion)
    {
        $this->conexion = $conexion;
        $this->modelo = new CentroAcopio($conexion);
    }

    public function listar(): array
    {
        $consulta = $this->conexion->query(
            'SELECT ca.*, 
                    (SELECT COUNT(*) FROM recursos r WHERE r.id_centro = ca.id_centro) AS total_recursos
             FROM centros_acopio ca
             ORDER BY ca.id_centro DESC'
        );
        $centros = $consulta->fetchAll();

        foreach ($centros as &$centro) {
            $centro['recursos'] = $this->modelo->listarRecursos((int) $centro['id_centro']);
        }

        return $centros;
    }

    public function obtener(int $id): ?array
    {
        $consulta = $this->conexion->prepare('SELECT * FROM centros_acopio WHERE id_centro = :id');
        $consulta->execute(['id' => $id]);
        $centro = $consulta->fetch();

        if (!$centro) return null;

        $centro['recursos'] = $this->modelo->listarRecursos($id);
        return $centro;
    }

    public function crear(array $datos): array
    {
        $camposObligatorios = ['nombre', 'ubicacion', 'capacidad'];
        foreach ($camposObligatorios as $campo) {
            if (empty($datos[$campo])) {
                return ['exito' => false, 'mensaje' => "Falta el campo obligatorio: {$campo}"];
            }
        }

        if ((float) $datos['capacidad'] <= 0) {
            return ['exito' => false, 'mensaje' => 'La capacidad debe ser mayor a 0'];
        }

        $consulta = $this->conexion->prepare(
            'INSERT INTO centros_acopio (nombre, ubicacion, capacidad) VALUES (:nombre, :ubicacion, :capacidad)'
        );
        $consulta->execute([
            'nombre'    => $datos['nombre'],
            'ubicacion' => $datos['ubicacion'],
            'capacidad' => (float) $datos['capacidad'],
        ]);

        $id = (int) $this->conexion->lastInsertId();
        return ['exito' => true, 'id' => $id, 'mensaje' => 'Centro de acopio creado'];
    }

    public function actualizar(int $id, array $datos): array
    {
        if (!$this->obtener($id)) {
            return ['exito' => false, 'mensaje' => 'Centro de acopio no encontrado'];
        }

        $consulta = $this->conexion->prepare(
            'UPDATE centros_acopio SET nombre = :nombre, ubicacion = :ubicacion, capacidad = :capacidad WHERE id_centro = :id'
        );
        $consulta->execute([
            'nombre'    => $datos['nombre'],
            'ubicacion' => $datos['ubicacion'],
            'capacidad' => (float) $datos['capacidad'],
            'id'        => $id,
        ]);

        return ['exito' => true, 'mensaje' => 'Centro de acopio actualizado'];
    }

    public function eliminar(int $id): array
    {
        if (!$this->obtener($id)) {
            return ['exito' => false, 'mensaje' => 'Centro de acopio no encontrado'];
        }

        $consulta = $this->conexion->prepare('DELETE FROM centros_acopio WHERE id_centro = :id');
        $consulta->execute(['id' => $id]);

        return ['exito' => true, 'mensaje' => 'Centro de acopio eliminado'];
    }

    public function agregarRecurso(int $idCentro, array $datos): array
    {
        if (!$this->obtener($idCentro)) {
            return ['exito' => false, 'mensaje' => 'Centro de acopio no encontrado'];
        }

        if (empty($datos['tipo']) || !isset($datos['cantidad'])) {
            return ['exito' => false, 'mensaje' => 'Faltan datos: tipo y cantidad'];
        }

        $consulta = $this->conexion->prepare(
            'INSERT INTO recursos (id_centro, tipo, cantidad) VALUES (:id_centro, :tipo, :cantidad)'
        );
        $consulta->execute([
            'id_centro' => $idCentro,
            'tipo'      => $datos['tipo'],
            'cantidad'  => (int) $datos['cantidad'],
        ]);

        return ['exito' => true, 'id' => (int) $this->conexion->lastInsertId(), 'mensaje' => 'Recurso/maquinaria agregado'];
    }

    public function actualizarRecurso(int $idCentro, int $idRecurso, array $datos): array
    {
        $consulta = $this->conexion->prepare(
            'UPDATE recursos SET tipo = :tipo, cantidad = :cantidad WHERE id_recurso = :id_recurso AND id_centro = :id_centro'
        );
        $consulta->execute([
            'tipo'      => $datos['tipo'],
            'cantidad'  => (int) $datos['cantidad'],
            'id_recurso' => $idRecurso,
            'id_centro' => $idCentro,
        ]);

        if ($consulta->rowCount() === 0) {
            return ['exito' => false, 'mensaje' => 'Recurso no encontrado'];
        }

        return ['exito' => true, 'mensaje' => 'Recurso actualizado'];
    }
}
