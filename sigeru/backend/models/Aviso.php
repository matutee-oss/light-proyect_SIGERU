<?php

class Aviso
{
    private PDO $conexion;

    public function __construct(PDO $conexion)
    {
        $this->conexion = $conexion;
    }

    public function listarActivos(?int $idZona = null): array
    {

        $sql = 'SELECT a.*, z.nombre_zona
                FROM avisos a
                LEFT JOIN zonas z ON z.id_zona = a.id_zona
                WHERE a.activo = TRUE
                  AND (a.id_zona IS NULL' . ($idZona ? ' OR a.id_zona = :id_zona' : '') . ')
                ORDER BY a.publicado_en DESC
                LIMIT 10';

        $consulta = $this->conexion->prepare($sql);
        if ($idZona) {
            $consulta->execute(['id_zona' => $idZona]);
        } else {
            $consulta->execute();
        }
        return $consulta->fetchAll();
    }

    public function crear(string $titulo, string $mensaje, ?int $idZona): int
    {
        $consulta = $this->conexion->prepare(
            'INSERT INTO avisos (titulo, mensaje, id_zona) VALUES (:titulo, :mensaje, :id_zona)'
        );
        $consulta->execute(['titulo' => $titulo, 'mensaje' => $mensaje, 'id_zona' => $idZona]);
        return (int) $this->conexion->lastInsertId();
    }

    public function desactivar(int $idAviso): bool
    {
        $consulta = $this->conexion->prepare('UPDATE avisos SET activo = 0 WHERE id_aviso = :id AND activo = 1');
        $consulta->execute(['id' => $idAviso]);
        return $consulta->rowCount() > 0;
    }
}
