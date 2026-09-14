<?php

class Incidencia
{
    private PDO $conexion;

    public function __construct(PDO $conexion)
    {
        $this->conexion = $conexion;
    }

    private const SELECT_BASE = "
        SELECT i.*, c.ubicacion AS contenedor_ubicacion, c.estado AS contenedor_estado,
               uv.nombre AS vecino_nombre, uv.apellido AS vecino_apellido,
               uc.nombre AS cuadrilla_nombre, uc.apellido AS cuadrilla_apellido
        FROM incidencias i
        JOIN contenedores c ON c.id_contenedor = i.id_contenedor
        LEFT JOIN usuarios uv ON uv.id_usuario = i.id_vecino
        LEFT JOIN usuarios uc ON uc.id_usuario = i.id_cuadrilla
    ";

    public function contarAbiertas(): int
    {
        $consulta = $this->conexion->query(
            "SELECT COUNT(*) AS total FROM incidencias WHERE estado != 'resuelta'"
        );
        return (int) $consulta->fetch()['total'];
    }

    public function feedActividad(int $limite = 12): array
    {
        $consulta = $this->conexion->query(
            "SELECT i.id_incidencia, i.tipo, i.estado, i.fecha_apertura, i.fecha_resolucion,
                    c.ubicacion AS contenedor_ubicacion,
                    uv.nombre AS vecino_nombre, uv.apellido AS vecino_apellido,
                    uc.nombre AS cuadrilla_nombre, uc.apellido AS cuadrilla_apellido
             FROM incidencias i
             JOIN contenedores c ON c.id_contenedor = i.id_contenedor
             LEFT JOIN usuarios uv ON uv.id_usuario = i.id_vecino
             LEFT JOIN usuarios uc ON uc.id_usuario = i.id_cuadrilla
             ORDER BY GREATEST(i.fecha_apertura, COALESCE(i.fecha_resolucion, i.fecha_apertura)) DESC
             LIMIT " . (int) $limite
        );
        return $consulta->fetchAll();
    }

    public function rankingZonas(): array
    {
        $consulta = $this->conexion->query(
            "SELECT z.nombre_zona, COUNT(i.id_incidencia) AS total_abiertas
             FROM incidencias i
             JOIN contenedores c ON c.id_contenedor = i.id_contenedor
             JOIN zonas z ON z.id_zona = c.id_zona
             WHERE i.estado != 'resuelta'
               AND i.fecha_apertura > DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY z.id_zona, z.nombre_zona
             ORDER BY total_abiertas DESC
             LIMIT 5"
        );
        return $consulta->fetchAll();
    }

    public function conteoPorTipo(): array
    {
        $consulta = $this->conexion->query(
            "SELECT tipo, COUNT(*) AS total
             FROM incidencias
             WHERE fecha_apertura > DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY tipo"
        );
        return $consulta->fetchAll();
    }

    public function progresoSemanal(): array
    {
        $consulta = $this->conexion->query(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN estado = 'resuelta' THEN 1 ELSE 0 END) AS resueltas
             FROM incidencias
             WHERE fecha_apertura > DATE_SUB(NOW(), INTERVAL 7 DAY)"
        );
        $fila = $consulta->fetch();
        $total = (int) $fila['total'];
        $resueltas = (int) $fila['resueltas'];
        $porcentaje = $total > 0 ? round(($resueltas / $total) * 100) : 0;
        return compact('total', 'resueltas', 'porcentaje');
    }

    public function contarResueltasPorVecino(int $idVecino): int
    {
        $consulta = $this->conexion->prepare(
            "SELECT COUNT(*) AS total FROM incidencias
             WHERE id_vecino = :id AND estado = 'resuelta'"
        );
        $consulta->execute(['id' => $idVecino]);
        return (int) $consulta->fetch()['total'];
    }

    public function crear(int $idContenedor, string $tipo, int $idVecino): int
    {
        $consulta = $this->conexion->prepare(
            'INSERT INTO incidencias (tipo, id_contenedor, id_vecino) VALUES (:tipo, :id_contenedor, :id_vecino)'
        );
        $consulta->execute([
            'tipo'          => $tipo,
            'id_contenedor' => $idContenedor,
            'id_vecino'     => $idVecino,
        ]);
        return (int) $this->conexion->lastInsertId();
    }

    public function listarTodas(): array
    {
        $consulta = $this->conexion->query(self::SELECT_BASE . ' ORDER BY i.fecha_apertura DESC');
        return $consulta->fetchAll();
    }

    public function listarPorVecino(int $idVecino): array
    {
        $consulta = $this->conexion->prepare(
            self::SELECT_BASE . ' WHERE i.id_vecino = :id ORDER BY i.fecha_apertura DESC'
        );
        $consulta->execute(['id' => $idVecino]);
        return $consulta->fetchAll();
    }

    public function listarParaCuadrilla(int $idCuadrilla): array
    {
        $consulta = $this->conexion->prepare(
            self::SELECT_BASE . '
             WHERE i.id_cuadrilla = :id OR (i.id_cuadrilla IS NULL AND i.estado = "abierta")
             ORDER BY i.fecha_apertura ASC'
        );
        $consulta->execute(['id' => $idCuadrilla]);
        return $consulta->fetchAll();
    }

    public function buscarPorId(int $id): ?array
    {
        $consulta = $this->conexion->prepare(self::SELECT_BASE . ' WHERE i.id_incidencia = :id');
        $consulta->execute(['id' => $id]);
        $resultado = $consulta->fetch();
        return $resultado ?: null;
    }

    public function asignarCuadrilla(int $idIncidencia, int $idCuadrilla): bool
    {
        $consulta = $this->conexion->prepare(
            'UPDATE incidencias SET id_cuadrilla = :id_cuadrilla, estado = "en_curso"
             WHERE id_incidencia = :id'
        );
        return $consulta->execute(['id_cuadrilla' => $idCuadrilla, 'id' => $idIncidencia]);
    }

    public function actualizarEstado(int $idIncidencia, string $estado): bool
    {
        $fechaResolucion = $estado === 'resuelta' ? 'NOW()' : 'NULL';
        $consulta = $this->conexion->prepare(
            "UPDATE incidencias SET estado = :estado, fecha_resolucion = {$fechaResolucion}
             WHERE id_incidencia = :id"
        );
        return $consulta->execute(['estado' => $estado, 'id' => $idIncidencia]);
    }
}
