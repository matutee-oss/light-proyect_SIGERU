<?php

class CentroAcopio
{
    private PDO $conexion;

    public function __construct(PDO $conexion)
    {
        $this->conexion = $conexion;
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
