<?php

class Camion
{
    private PDO $conexion;

    public function __construct(PDO $conexion)
    {
        $this->conexion = $conexion;
    }

    public function listarTodos(): array
    {
        $consulta = $this->conexion->query('SELECT * FROM camiones ORDER BY id_camion DESC');
        return $consulta->fetchAll();
    }

    public function listarGeolocalizados(): array
    {
        $consulta = $this->conexion->query(
            'SELECT id_camion, matricula, estado, latitud, longitud
             FROM camiones
             WHERE latitud IS NOT NULL AND longitud IS NOT NULL
             ORDER BY matricula'
        );
        return $consulta->fetchAll();
    }

    public function buscarPorId(int $id): ?array
    {
        $consulta = $this->conexion->prepare('SELECT * FROM camiones WHERE id_camion = :id');
        $consulta->execute(['id' => $id]);
        $resultado = $consulta->fetch();
        return $resultado ?: null;
    }

    public function buscarPorMatricula(string $matricula): ?array
    {
        $consulta = $this->conexion->prepare('SELECT * FROM camiones WHERE REPLACE(matricula, " ", "") = REPLACE(:matricula, " ", "")');
        $consulta->execute(['matricula' => strtoupper(trim($matricula))]);
        $resultado = $consulta->fetch();
        return $resultado ?: null;
    }

    public function crear(array $datos): int
    {
        $consulta = $this->conexion->prepare(
            'INSERT INTO camiones (matricula, estado, capacidad_kg, latitud, longitud)
             VALUES (:matricula, :estado, :capacidad_kg, :latitud, :longitud)'
        );
        $consulta->execute([
            'matricula'    => strtoupper(trim($datos['matricula'])),
            'estado'       => $datos['estado'] ?? 'disponible',
            'capacidad_kg' => ($datos['capacidad_kg'] ?? '') !== '' ? (float) $datos['capacidad_kg'] : 6000,
            'latitud'      => ($datos['latitud'] ?? '') !== '' ? (float) $datos['latitud'] : null,
            'longitud'     => ($datos['longitud'] ?? '') !== '' ? (float) $datos['longitud'] : null,
        ]);
        return (int) $this->conexion->lastInsertId();
    }

    public function actualizar(int $id, array $datos): bool
    {
        $consulta = $this->conexion->prepare(
            'UPDATE camiones
             SET matricula = :matricula,
                 estado = :estado,
                 capacidad_kg = :capacidad_kg,
                 latitud = :latitud,
                 longitud = :longitud
             WHERE id_camion = :id'
        );
        return $consulta->execute([
            'matricula'    => strtoupper(trim($datos['matricula'])),
            'estado'       => $datos['estado'] ?? 'disponible',
            'capacidad_kg' => ($datos['capacidad_kg'] ?? '') !== '' ? (float) $datos['capacidad_kg'] : 6000,
            'latitud'      => ($datos['latitud'] ?? '') !== '' ? (float) $datos['latitud'] : null,
            'longitud'     => ($datos['longitud'] ?? '') !== '' ? (float) $datos['longitud'] : null,
            'id'           => $id,
        ]);
    }

    public function eliminar(int $id): bool
    {
        $consulta = $this->conexion->prepare('DELETE FROM camiones WHERE id_camion = :id');
        return $consulta->execute(['id' => $id]);
    }
}
