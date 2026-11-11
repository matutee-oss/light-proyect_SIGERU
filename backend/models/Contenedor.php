<?php

class Contenedor
{
    private PDO $conexion;

    public function __construct(PDO $conexion)
    {
        $this->conexion = $conexion;
    }

    public function listarTodos(): array
    {
        $consulta = $this->conexion->query(
            'SELECT * FROM contenedores ORDER BY id_contenedor DESC'
        );
        return $consulta->fetchAll();
    }

    public function listarPorZona(int $idZona): array
    {
        $consulta = $this->conexion->prepare(
            'SELECT * FROM contenedores WHERE id_zona = :id_zona ORDER BY ubicacion'
        );
        $consulta->execute(['id_zona' => $idZona]);
        return $consulta->fetchAll();
    }

    public function buscarPorId(int $id): ?array
    {
        $consulta = $this->conexion->prepare('SELECT * FROM contenedores WHERE id_contenedor = :id');
        $consulta->execute(['id' => $id]);
        $resultado = $consulta->fetch();
        return $resultado ?: null;
    }

    public function crear(array $datos): int
    {
        $consulta = $this->conexion->prepare(
            'INSERT INTO contenedores (ubicacion, tipo_residuo, estado, id_zona, latitud, longitud)
             VALUES (:ubicacion, :tipo_residuo, :estado, :id_zona, :latitud, :longitud)'
        );
        $consulta->execute([
            'ubicacion'    => $datos['ubicacion'],
            'tipo_residuo' => $datos['tipo_residuo'],
            'estado'       => $datos['estado'] ?? 'funcional',
            'id_zona'      => $datos['id_zona'] ?? null,
            'latitud'      => $datos['latitud'] ?? null,
            'longitud'     => $datos['longitud'] ?? null,
        ]);
        return (int) $this->conexion->lastInsertId();
    }

    public function actualizar(int $id, array $datos): bool
    {
        $consulta = $this->conexion->prepare(
            'UPDATE contenedores
             SET ubicacion = :ubicacion,
                 tipo_residuo = :tipo_residuo,
                 estado = :estado,
                 latitud = :latitud,
                 longitud = :longitud
             WHERE id_contenedor = :id'
        );
        return $consulta->execute([
            'ubicacion'    => $datos['ubicacion'],
            'tipo_residuo' => $datos['tipo_residuo'],
            'estado'       => $datos['estado'],
            'latitud'      => $datos['latitud'] ?? null,
            'longitud'     => $datos['longitud'] ?? null,
            'id'           => $id,
        ]);
    }

    public function eliminar(int $id): bool
    {
        $consulta = $this->conexion->prepare('DELETE FROM contenedores WHERE id_contenedor = :id');
        return $consulta->execute(['id' => $id]);
    }
}
