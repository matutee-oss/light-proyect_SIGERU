<?php

class Recoleccion
{
    private PDO $conexion;

    private const SELECT_BASE = "
        SELECT r.*, c.ubicacion AS contenedor_ubicacion, c.tipo_residuo, c.id_zona, z.nombre_zona,
               ca.matricula AS camion_matricula,
               u.nombre AS cuadrilla_nombre, u.apellido AS cuadrilla_apellido
        FROM recolecciones r
        JOIN contenedores c ON c.id_contenedor = r.id_contenedor
        LEFT JOIN zonas z ON z.id_zona = c.id_zona
        LEFT JOIN camiones ca ON ca.id_camion = r.id_camion
        LEFT JOIN usuarios u ON u.id_usuario = r.id_cuadrilla
    ";

    public function __construct(PDO $conexion)
    {
        $this->conexion = $conexion;
    }

    public function crear(array $datos): int
    {
        $consulta = $this->conexion->prepare(
            'INSERT INTO recolecciones (id_contenedor, id_camion, id_cuadrilla, id_ruta, kg_recolectados, nivel_previo, observaciones)
             VALUES (:id_contenedor, :id_camion, :id_cuadrilla, :id_ruta, :kg, :nivel_previo, :observaciones)'
        );
        $consulta->execute([
            'id_contenedor' => (int) $datos['id_contenedor'],
            'id_camion'     => $datos['id_camion'] ?? null,
            'id_cuadrilla'  => $datos['id_cuadrilla'] ?? null,
            'id_ruta'       => $datos['id_ruta'] ?? null,
            'kg'            => $datos['kg_recolectados'] ?? null,
            'nivel_previo'  => $datos['nivel_previo'] ?? null,
            'observaciones' => isset($datos['observaciones']) && trim($datos['observaciones']) !== ''
                ? mb_substr(trim($datos['observaciones']), 0, 255) : null,
        ]);
        return (int) $this->conexion->lastInsertId();
    }

    public function buscarPorId(int $id): ?array
    {
        $consulta = $this->conexion->prepare(self::SELECT_BASE . ' WHERE r.id_recoleccion = :id');
        $consulta->execute(['id' => $id]);
        $fila = $consulta->fetch();
        return $fila ?: null;
    }

    /** ¿Ya se registró la recolección de este contenedor dentro de esta ruta? */
    public function existeEnRuta(int $idRuta, int $idContenedor): bool
    {
        $consulta = $this->conexion->prepare(
            'SELECT 1 FROM recolecciones WHERE id_ruta = :ruta AND id_contenedor = :contenedor LIMIT 1'
        );
        $consulta->execute(['ruta' => $idRuta, 'contenedor' => $idContenedor]);
        return (bool) $consulta->fetchColumn();
    }

    /** Filtros: id_contenedor, id_cuadrilla, id_camion, id_zona, desde, hasta. */
    public function listar(array $filtros = [], int $limite = 200): array
    {
        $condiciones = [];
        $parametros = [];
        $exactos = [
            'id_contenedor' => 'r.id_contenedor', 'id_cuadrilla' => 'r.id_cuadrilla',
            'id_camion' => 'r.id_camion', 'id_zona' => 'c.id_zona',
        ];
        foreach ($exactos as $clave => $columna) {
            if (!empty($filtros[$clave])) {
                $condiciones[] = "{$columna} = :{$clave}";
                $parametros[$clave] = (int) $filtros[$clave];
            }
        }
        if (!empty($filtros['desde'])) {
            $condiciones[] = 'r.recolectado_en >= :desde';
            $parametros['desde'] = $filtros['desde'] . ' 00:00:00';
        }
        if (!empty($filtros['hasta'])) {
            $condiciones[] = 'r.recolectado_en <= :hasta';
            $parametros['hasta'] = $filtros['hasta'] . ' 23:59:59';
        }

        $sql = self::SELECT_BASE
             . ($condiciones ? ' WHERE ' . implode(' AND ', $condiciones) : '')
             . ' ORDER BY r.recolectado_en DESC, r.id_recoleccion DESC LIMIT ' . max(1, min(1000, $limite));
        $consulta = $this->conexion->prepare($sql);
        $consulta->execute($parametros);
        return $consulta->fetchAll();
    }

    public function eliminar(int $id): bool
    {
        $consulta = $this->conexion->prepare('DELETE FROM recolecciones WHERE id_recoleccion = :id');
        $consulta->execute(['id' => $id]);
        return $consulta->rowCount() > 0;
    }

    /** Kilos y cantidad de recolecciones por día (últimos N días, incluye días en cero). */
    public function kilosPorDia(int $dias = 14): array
    {
        $consulta = $this->conexion->prepare(
            'SELECT DATE(recolectado_en) AS dia,
                    COUNT(*) AS recolecciones,
                    COALESCE(SUM(kg_recolectados), 0) AS kg
             FROM recolecciones
             WHERE recolectado_en >= DATE_SUB(CURDATE(), INTERVAL :dias DAY)
             GROUP BY DATE(recolectado_en)'
        );
        $consulta->bindValue(':dias', $dias - 1, PDO::PARAM_INT);
        $consulta->execute();
        $porDia = [];
        foreach ($consulta->fetchAll() as $fila) {
            $porDia[$fila['dia']] = $fila;
        }

        $serie = [];
        for ($i = $dias - 1; $i >= 0; $i--) {
            $dia = date('Y-m-d', strtotime("-{$i} days"));
            $serie[] = [
                'dia'           => $dia,
                'recolecciones' => (int) ($porDia[$dia]['recolecciones'] ?? 0),
                'kg'            => (float) ($porDia[$dia]['kg'] ?? 0),
            ];
        }
        return $serie;
    }

    public function kilosPorZona(string $mes): array
    {
        $consulta = $this->conexion->prepare(
            "SELECT z.nombre_zona, COUNT(r.id_recoleccion) AS recolecciones,
                    COALESCE(SUM(r.kg_recolectados), 0) AS kg
             FROM zonas z
             LEFT JOIN contenedores c ON c.id_zona = z.id_zona
             LEFT JOIN recolecciones r ON r.id_contenedor = c.id_contenedor
                  AND DATE_FORMAT(r.recolectado_en, '%Y-%m') = :mes
             GROUP BY z.id_zona, z.nombre_zona
             ORDER BY z.id_zona"
        );
        $consulta->execute(['mes' => $mes]);
        return $consulta->fetchAll();
    }

    public function resumenMes(string $mes): array
    {
        $consulta = $this->conexion->prepare(
            "SELECT COUNT(*) AS recolecciones,
                    COALESCE(SUM(kg_recolectados), 0) AS kg,
                    COUNT(DISTINCT id_contenedor) AS contenedores_atendidos
             FROM recolecciones
             WHERE DATE_FORMAT(recolectado_en, '%Y-%m') = :mes"
        );
        $consulta->execute(['mes' => $mes]);
        $fila = $consulta->fetch();
        return [
            'recolecciones'          => (int) $fila['recolecciones'],
            'kg'                     => (float) $fila['kg'],
            'contenedores_atendidos' => (int) $fila['contenedores_atendidos'],
        ];
    }
}
