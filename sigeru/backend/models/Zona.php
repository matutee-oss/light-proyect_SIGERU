<?php

class Zona
{
    private PDO $conexion;

    public function __construct(PDO $conexion)
    {
        $this->conexion = $conexion;
    }

    public function listarTodas(): array
    {
        $consulta = $this->conexion->query('SELECT * FROM zonas ORDER BY id_zona');
        return $consulta->fetchAll();
    }

    public function contar(): int
    {
        $consulta = $this->conexion->query('SELECT COUNT(*) AS total FROM zonas');
        return (int) $consulta->fetch()['total'];
    }
}
