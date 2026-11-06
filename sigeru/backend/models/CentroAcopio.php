<?php

require_once __DIR__ . '/../helpers/validacion.php';

class CentroAcopio
{
    private PDO $conexion;

    public function __construct(PDO $conexion)
    {
        $this->conexion = $conexion;
    }

    public function listarTodos(): array
    {
        return $this->conexion->query(
            'SELECT ca.*,
                    (SELECT COUNT(*) FROM recursos r WHERE r.id_centro = ca.id_centro) AS total_recursos,
                    (SELECT COUNT(*) FROM operarios o WHERE o.id_centro = ca.id_centro) AS total_operarios
             FROM centros_acopio ca
             ORDER BY ca.id_centro DESC'
        )->fetchAll();
    }

    public function buscarPorId(int $id): ?array
    {
        $consulta = $this->conexion->prepare('SELECT * FROM centros_acopio WHERE id_centro = :id');
        $consulta->execute(['id' => $id]);
        $fila = $consulta->fetch();
        return $fila ?: null;
    }

    public function crear(array $datos): int
    {
        $consulta = $this->conexion->prepare(
            'INSERT INTO centros_acopio (nombre, ubicacion, capacidad, latitud, longitud)
             VALUES (:nombre, :ubicacion, :capacidad, :latitud, :longitud)'
        );
        $consulta->execute([
            'nombre'    => trim($datos['nombre']),
            'ubicacion' => trim($datos['ubicacion']),
            'capacidad' => (float) $datos['capacidad'],
            'latitud'   => Validacion::numeroONulo($datos['latitud'] ?? null),
            'longitud'  => Validacion::numeroONulo($datos['longitud'] ?? null),
        ]);
        return (int) $this->conexion->lastInsertId();
    }

    public function actualizar(int $id, array $datos): bool
    {
        $consulta = $this->conexion->prepare(
            'UPDATE centros_acopio
             SET nombre = :nombre, ubicacion = :ubicacion, capacidad = :capacidad,
                 latitud = :latitud, longitud = :longitud
             WHERE id_centro = :id'
        );
        return $consulta->execute([
            'nombre'    => trim($datos['nombre']),
            'ubicacion' => trim($datos['ubicacion']),
            'capacidad' => (float) $datos['capacidad'],
            'latitud'   => Validacion::numeroONulo($datos['latitud'] ?? null),
            'longitud'  => Validacion::numeroONulo($datos['longitud'] ?? null),
            'id'        => $id,
        ]);
    }

    public function contarOperarios(int $idCentro): int
    {
        $consulta = $this->conexion->prepare('SELECT COUNT(*) FROM operarios WHERE id_centro = :id');
        $consulta->execute(['id' => $idCentro]);
        return (int) $consulta->fetchColumn();
    }

    public function eliminar(int $id): bool
    {
        $consulta = $this->conexion->prepare('DELETE FROM centros_acopio WHERE id_centro = :id');
        return $consulta->execute(['id' => $id]);
    }

    public function agregarRecurso(int $idCentro, string $tipo, int $cantidad): int
    {
        $consulta = $this->conexion->prepare(
            'INSERT INTO recursos (id_centro, tipo, cantidad) VALUES (:id_centro, :tipo, :cantidad)'
        );
        $consulta->execute(['id_centro' => $idCentro, 'tipo' => trim($tipo), 'cantidad' => $cantidad]);
        return (int) $this->conexion->lastInsertId();
    }

    public function buscarRecurso(int $idCentro, int $idRecurso): ?array
    {
        $consulta = $this->conexion->prepare(
            'SELECT * FROM recursos WHERE id_recurso = :id_recurso AND id_centro = :id_centro'
        );
        $consulta->execute(['id_recurso' => $idRecurso, 'id_centro' => $idCentro]);
        $fila = $consulta->fetch();
        return $fila ?: null;
    }

    public function editarRecurso(int $idCentro, int $idRecurso, string $tipo, int $cantidad): bool
    {
        $consulta = $this->conexion->prepare(
            'UPDATE recursos SET tipo = :tipo, cantidad = :cantidad
             WHERE id_recurso = :id_recurso AND id_centro = :id_centro'
        );
        return $consulta->execute([
            'tipo' => trim($tipo), 'cantidad' => $cantidad,
            'id_recurso' => $idRecurso, 'id_centro' => $idCentro,
        ]);
    }

    public function eliminarRecurso(int $idCentro, int $idRecurso): bool
    {
        $consulta = $this->conexion->prepare(
            'DELETE FROM recursos WHERE id_recurso = :id_recurso AND id_centro = :id_centro'
        );
        $consulta->execute(['id_recurso' => $idRecurso, 'id_centro' => $idCentro]);
        return $consulta->rowCount() > 0;
    }

    public function buscarPorOperario(int $idUsuarioOperario): ?array
    {
        $consulta = $this->conexion->prepare(
            'SELECT ca.*
             FROM centros_acopio ca
             JOIN operarios o ON o.id_centro = ca.id_centro
             WHERE o.id_usuario = :id_usuario'
        );
        $consulta->execute(['id_usuario' => $idUsuarioOperario]);
        $resultado = $consulta->fetch();
        return $resultado ?: null;
    }

    public function listarRecursos(int $idCentro): array
    {
        $consulta = $this->conexion->prepare(
            'SELECT * FROM recursos WHERE id_centro = :id_centro ORDER BY tipo'
        );
        $consulta->execute(['id_centro' => $idCentro]);
        return $consulta->fetchAll();
    }

    public function actualizarRecurso(int $idCentro, int $idRecurso, int $cantidad): bool
    {
        $consulta = $this->conexion->prepare(
            'UPDATE recursos SET cantidad = :cantidad
             WHERE id_centro = :id_centro AND id_recurso = :id_recurso'
        );
        return $consulta->execute([
            'cantidad'   => $cantidad,
            'id_centro'  => $idCentro,
            'id_recurso' => $idRecurso,
        ]);
    }

    public function calcularCapacidadUsada(int $idCentro, float $capacidadTotal): array
    {
        $consulta = $this->conexion->prepare(
            'SELECT COALESCE(SUM(toneladas), 0) AS usadas
             FROM ingresos_centro
             WHERE id_centro = :id_centro
               AND registrado_en > DATE_SUB(NOW(), INTERVAL 30 DAY)'
        );
        $consulta->execute(['id_centro' => $idCentro]);
        $toneladas = (float) $consulta->fetch()['usadas'];

        $usadoM3 = $toneladas * 1.5;
        $porcentaje = $capacidadTotal > 0 ? min(100, round(($usadoM3 / $capacidadTotal) * 100)) : 0;

        return ['toneladas_30d' => $toneladas, 'porcentaje' => $porcentaje];
    }

    public function listarIngresosRecientes(int $idCentro, int $limite = 8): array
    {
        $consulta = $this->conexion->prepare(
            'SELECT i.*, c.matricula
             FROM ingresos_centro i
             LEFT JOIN camiones c ON c.id_camion = i.id_camion
             WHERE i.id_centro = :id_centro
             ORDER BY i.registrado_en DESC
             LIMIT ' . (int) $limite
        );
        $consulta->execute(['id_centro' => $idCentro]);
        return $consulta->fetchAll();
    }

    public function ingresosPorDia(int $idCentro, int $dias = 7): array
    {
        $consulta = $this->conexion->prepare(
            'SELECT DATE(registrado_en) AS dia, SUM(toneladas) AS total
             FROM ingresos_centro
             WHERE id_centro = :id_centro
               AND registrado_en > DATE_SUB(CURDATE(), INTERVAL :dias DAY)
             GROUP BY DATE(registrado_en)
             ORDER BY dia'
        );
        $consulta->bindValue(':id_centro', $idCentro, PDO::PARAM_INT);
        $consulta->bindValue(':dias', $dias, PDO::PARAM_INT);
        $consulta->execute();
        return $consulta->fetchAll();
    }

    public function registrarIngreso(int $idCentro, ?int $idCamion, float $toneladas, ?string $observaciones): int
    {
        $consulta = $this->conexion->prepare(
            'INSERT INTO ingresos_centro (id_centro, id_camion, toneladas, observaciones)
             VALUES (:id_centro, :id_camion, :toneladas, :observaciones)'
        );
        $consulta->execute([
            'id_centro'     => $idCentro,
            'id_camion'     => $idCamion,
            'toneladas'     => $toneladas,
            'observaciones' => $observaciones,
        ]);
        return (int) $this->conexion->lastInsertId();
    }
}
