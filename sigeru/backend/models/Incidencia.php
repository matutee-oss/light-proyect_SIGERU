<?php

class Incidencia
{
    private PDO $conexion;

    public function __construct(PDO $conexion)
    {
        $this->conexion = $conexion;
    }

    private const SELECT_BASE = "
        SELECT i.*,
               c.ubicacion AS contenedor_ubicacion, c.estado AS contenedor_estado,
               c.latitud, c.longitud, c.id_zona, z.nombre_zona,
               uv.nombre AS vecino_nombre, uv.apellido AS vecino_apellido,
               uc.nombre AS cuadrilla_nombre, uc.apellido AS cuadrilla_apellido,
               ur.nombre AS reporta_nombre, ur.apellido AS reporta_apellido, ur.tipo_usuario AS reporta_rol
        FROM incidencias i
        JOIN contenedores c ON c.id_contenedor = i.id_contenedor
        LEFT JOIN zonas z ON z.id_zona = c.id_zona
        LEFT JOIN usuarios uv ON uv.id_usuario = i.id_vecino
        LEFT JOIN usuarios uc ON uc.id_usuario = i.id_cuadrilla
        LEFT JOIN usuarios ur ON ur.id_usuario = i.reportado_por
    ";

    // ------------------------------------------------------------
    // Alta, baja y modificación
    // ------------------------------------------------------------

    public function crear(int $idContenedor, string $tipo, ?int $idVecino, ?int $reportadoPor = null, ?string $descripcion = null, string $prioridad = 'media'): int
    {
        $consulta = $this->conexion->prepare(
            'INSERT INTO incidencias (tipo, prioridad, descripcion, id_contenedor, id_vecino, reportado_por)
             VALUES (:tipo, :prioridad, :descripcion, :id_contenedor, :id_vecino, :reportado_por)'
        );
        $consulta->execute([
            'tipo'          => $tipo,
            'prioridad'     => $prioridad,
            'descripcion'   => $descripcion !== null && trim($descripcion) !== '' ? trim($descripcion) : null,
            'id_contenedor' => $idContenedor,
            'id_vecino'     => $idVecino,
            'reportado_por' => $reportadoPor ?? $idVecino,
        ]);
        return (int) $this->conexion->lastInsertId();
    }

    public function asignarCuadrilla(int $idIncidencia, int $idCuadrilla): bool
    {
        $consulta = $this->conexion->prepare(
            "UPDATE incidencias
             SET id_cuadrilla = :id_cuadrilla,
                 estado = IF(estado = 'abierta', 'en_curso', estado)
             WHERE id_incidencia = :id"
        );
        return $consulta->execute(['id_cuadrilla' => $idCuadrilla, 'id' => $idIncidencia]);
    }

    public function actualizarEstado(int $idIncidencia, string $estado): bool
    {
        $consulta = $this->conexion->prepare(
            "UPDATE incidencias
             SET estado = :estado,
                 fecha_resolucion = IF(:estado2 = 'resuelta', NOW(), NULL)
             WHERE id_incidencia = :id"
        );
        return $consulta->execute(['estado' => $estado, 'estado2' => $estado, 'id' => $idIncidencia]);
    }

    public function actualizarPrioridad(int $idIncidencia, string $prioridad): bool
    {
        $consulta = $this->conexion->prepare('UPDATE incidencias SET prioridad = :prioridad WHERE id_incidencia = :id');
        return $consulta->execute(['prioridad' => $prioridad, 'id' => $idIncidencia]);
    }

    public function eliminar(int $idIncidencia): bool
    {
        $consulta = $this->conexion->prepare('DELETE FROM incidencias WHERE id_incidencia = :id');
        $consulta->execute(['id' => $idIncidencia]);
        return $consulta->rowCount() > 0;
    }

    /**
     * Cuando se vacía un contenedor, los desbordes y faltas de recolección
     * pendientes de ese contenedor quedan resueltos. Devuelve sus datos
     * (antes del cambio) para poder registrar el seguimiento y avisar.
     */
    public function resolverPorRecoleccion(int $idContenedor): array
    {
        $consulta = $this->conexion->prepare(
            self::SELECT_BASE . "
             WHERE i.id_contenedor = :id
               AND i.estado <> 'resuelta'
               AND i.tipo IN ('desborde', 'falta_recoleccion')"
        );
        $consulta->execute(['id' => $idContenedor]);
        $pendientes = $consulta->fetchAll();

        if ($pendientes) {
            $actualizar = $this->conexion->prepare(
                "UPDATE incidencias SET estado = 'resuelta', fecha_resolucion = NOW()
                 WHERE id_contenedor = :id AND estado <> 'resuelta'
                   AND tipo IN ('desborde', 'falta_recoleccion')"
            );
            $actualizar->execute(['id' => $idContenedor]);
        }
        return $pendientes;
    }

    // ------------------------------------------------------------
    // Consultas
    // ------------------------------------------------------------

    public function buscarPorId(int $id): ?array
    {
        $consulta = $this->conexion->prepare(self::SELECT_BASE . ' WHERE i.id_incidencia = :id');
        $consulta->execute(['id' => $id]);
        $resultado = $consulta->fetch();
        return $resultado ?: null;
    }

    /**
     * Listado con filtros opcionales:
     * estado, tipo, prioridad, id_zona, id_contenedor, id_cuadrilla, reportado_por, desde, hasta (AAAA-MM-DD), texto.
     */
    public function listar(array $filtros = [], int $limite = 500): array
    {
        [$where, $parametros] = $this->construirFiltros($filtros);

        $sql = self::SELECT_BASE . $where . "
            ORDER BY FIELD(i.estado, 'abierta', 'en_curso', 'resuelta'),
                     FIELD(i.prioridad, 'alta', 'media', 'baja'),
                     i.fecha_apertura DESC
            LIMIT " . max(1, min(2000, $limite));

        $consulta = $this->conexion->prepare($sql);
        $consulta->execute($parametros);
        return $consulta->fetchAll();
    }

    public function listarTodas(): array
    {
        return $this->listar();
    }

    public function listarPorVecino(int $idVecino): array
    {
        $consulta = $this->conexion->prepare(
            self::SELECT_BASE . ' WHERE i.reportado_por = :id OR i.id_vecino = :id2 ORDER BY i.fecha_apertura DESC'
        );
        $consulta->execute(['id' => $idVecino, 'id2' => $idVecino]);
        return $consulta->fetchAll();
    }

    /** Las de la cuadrilla + las abiertas que nadie tomó todavía. */
    public function listarParaCuadrilla(int $idCuadrilla): array
    {
        $consulta = $this->conexion->prepare(
            self::SELECT_BASE . "
             WHERE i.id_cuadrilla = :id OR (i.id_cuadrilla IS NULL AND i.estado = 'abierta')
             ORDER BY FIELD(i.estado, 'abierta', 'en_curso', 'resuelta'),
                      FIELD(i.prioridad, 'alta', 'media', 'baja'),
                      i.fecha_apertura ASC"
        );
        $consulta->execute(['id' => $idCuadrilla]);
        return $consulta->fetchAll();
    }

    /** Incidencias no resueltas con coordenadas, para el mapa interactivo. */
    public function listarParaMapa(array $filtros = []): array
    {
        if (!isset($filtros['estado'])) {
            $filtros['pendientes'] = true;
        }
        [$where, $parametros] = $this->construirFiltros($filtros);
        $where .= ($where ? ' AND' : ' WHERE') . ' c.latitud IS NOT NULL AND c.longitud IS NOT NULL';

        $consulta = $this->conexion->prepare(self::SELECT_BASE . $where . ' ORDER BY i.fecha_apertura DESC');
        $consulta->execute($parametros);
        return $consulta->fetchAll();
    }

    private function construirFiltros(array $filtros): array
    {
        $condiciones = [];
        $parametros = [];

        $exactos = [
            'estado' => 'i.estado', 'tipo' => 'i.tipo', 'prioridad' => 'i.prioridad',
            'id_zona' => 'c.id_zona', 'id_contenedor' => 'i.id_contenedor',
            'id_cuadrilla' => 'i.id_cuadrilla', 'reportado_por' => 'i.reportado_por',
        ];
        foreach ($exactos as $clave => $columna) {
            if (isset($filtros[$clave]) && $filtros[$clave] !== '') {
                $condiciones[] = "{$columna} = :{$clave}";
                $parametros[$clave] = $filtros[$clave];
            }
        }
        if (!empty($filtros['pendientes'])) {
            $condiciones[] = "i.estado <> 'resuelta'";
        }
        if (!empty($filtros['desde'])) {
            $condiciones[] = 'i.fecha_apertura >= :desde';
            $parametros['desde'] = $filtros['desde'] . ' 00:00:00';
        }
        if (!empty($filtros['hasta'])) {
            $condiciones[] = 'i.fecha_apertura <= :hasta';
            $parametros['hasta'] = $filtros['hasta'] . ' 23:59:59';
        }
        if (!empty($filtros['texto'])) {
            $condiciones[] = '(c.ubicacion LIKE :texto OR i.descripcion LIKE :texto2)';
            $parametros['texto'] = '%' . $filtros['texto'] . '%';
            $parametros['texto2'] = '%' . $filtros['texto'] . '%';
        }

        $where = $condiciones ? ' WHERE ' . implode(' AND ', $condiciones) : '';
        return [$where, $parametros];
    }

    // ------------------------------------------------------------
    // Seguimiento (historial)
    // ------------------------------------------------------------

    public function registrarSeguimiento(int $idIncidencia, ?int $idUsuario, string $accion, ?string $estadoAnterior = null, ?string $estadoNuevo = null, ?string $comentario = null): int
    {
        $consulta = $this->conexion->prepare(
            'INSERT INTO incidencia_seguimiento (id_incidencia, id_usuario, accion, estado_anterior, estado_nuevo, comentario)
             VALUES (:id_incidencia, :id_usuario, :accion, :estado_anterior, :estado_nuevo, :comentario)'
        );
        $consulta->execute([
            'id_incidencia'   => $idIncidencia,
            'id_usuario'      => $idUsuario,
            'accion'          => $accion,
            'estado_anterior' => $estadoAnterior,
            'estado_nuevo'    => $estadoNuevo,
            'comentario'      => $comentario !== null && trim($comentario) !== '' ? mb_substr(trim($comentario), 0, 500) : null,
        ]);
        return (int) $this->conexion->lastInsertId();
    }

    public function listarSeguimiento(int $idIncidencia): array
    {
        $consulta = $this->conexion->prepare(
            'SELECT s.*, u.nombre, u.apellido, u.tipo_usuario
             FROM incidencia_seguimiento s
             LEFT JOIN usuarios u ON u.id_usuario = s.id_usuario
             WHERE s.id_incidencia = :id
             ORDER BY s.creado_en ASC, s.id_seguimiento ASC'
        );
        $consulta->execute(['id' => $idIncidencia]);
        return $consulta->fetchAll();
    }

    // ------------------------------------------------------------
    // Indicadores (paneles, informes y estadísticas)
    // ------------------------------------------------------------

    public function contarAbiertas(): int
    {
        return (int) $this->conexion->query(
            "SELECT COUNT(*) FROM incidencias WHERE estado <> 'resuelta'"
        )->fetchColumn();
    }

    public function contarResueltasPorVecino(int $idVecino): int
    {
        $consulta = $this->conexion->prepare(
            "SELECT COUNT(*) FROM incidencias
             WHERE (reportado_por = :id OR id_vecino = :id2) AND estado = 'resuelta'"
        );
        $consulta->execute(['id' => $idVecino, 'id2' => $idVecino]);
        return (int) $consulta->fetchColumn();
    }

    public function feedActividad(int $limite = 12): array
    {
        $consulta = $this->conexion->query(
            "SELECT s.id_incidencia, s.accion, s.estado_nuevo, s.comentario, s.creado_en,
                    i.tipo, i.estado, i.prioridad, c.ubicacion AS contenedor_ubicacion,
                    u.nombre, u.apellido, u.tipo_usuario
             FROM incidencia_seguimiento s
             JOIN incidencias i ON i.id_incidencia = s.id_incidencia
             JOIN contenedores c ON c.id_contenedor = i.id_contenedor
             LEFT JOIN usuarios u ON u.id_usuario = s.id_usuario
             ORDER BY s.creado_en DESC, s.id_seguimiento DESC
             LIMIT " . max(1, min(50, $limite))
        );
        return $consulta->fetchAll();
    }

    public function rankingZonas(int $dias = 30): array
    {
        $consulta = $this->conexion->prepare(
            "SELECT z.id_zona, z.nombre_zona, COUNT(i.id_incidencia) AS total_abiertas
             FROM incidencias i
             JOIN contenedores c ON c.id_contenedor = i.id_contenedor
             JOIN zonas z ON z.id_zona = c.id_zona
             WHERE i.estado <> 'resuelta'
               AND i.fecha_apertura > DATE_SUB(NOW(), INTERVAL :dias DAY)
             GROUP BY z.id_zona, z.nombre_zona
             ORDER BY total_abiertas DESC
             LIMIT 5"
        );
        $consulta->bindValue(':dias', $dias, PDO::PARAM_INT);
        $consulta->execute();
        return $consulta->fetchAll();
    }

    public function conteoPorTipo(int $dias = 30): array
    {
        $consulta = $this->conexion->prepare(
            'SELECT tipo, COUNT(*) AS total
             FROM incidencias
             WHERE fecha_apertura > DATE_SUB(NOW(), INTERVAL :dias DAY)
             GROUP BY tipo
             ORDER BY total DESC'
        );
        $consulta->bindValue(':dias', $dias, PDO::PARAM_INT);
        $consulta->execute();
        return $consulta->fetchAll();
    }

    public function progresoSemanal(): array
    {
        $fila = $this->conexion->query(
            "SELECT COUNT(*) AS total,
                    COALESCE(SUM(estado = 'resuelta'), 0) AS resueltas
             FROM incidencias
             WHERE fecha_apertura > DATE_SUB(NOW(), INTERVAL 7 DAY)"
        )->fetch();
        $total = (int) $fila['total'];
        $resueltas = (int) $fila['resueltas'];
        $porcentaje = $total > 0 ? (int) round(($resueltas / $total) * 100) : 0;
        return compact('total', 'resueltas', 'porcentaje');
    }

    /** Totales por estado dentro de un período (para informes). */
    public function resumenPorEstado(array $filtros = []): array
    {
        $sinEstado = $filtros;
        unset($sinEstado['estado']);
        [$where, $parametros] = $this->construirFiltros($sinEstado);

        $consulta = $this->conexion->prepare(
            "SELECT
                COUNT(*) AS total,
                COALESCE(SUM(i.estado = 'abierta'), 0)  AS abiertas,
                COALESCE(SUM(i.estado = 'en_curso'), 0) AS en_curso,
                COALESCE(SUM(i.estado = 'resuelta'), 0) AS resueltas,
                ROUND(AVG(CASE WHEN i.estado = 'resuelta'
                    THEN TIMESTAMPDIFF(MINUTE, i.fecha_apertura, i.fecha_resolucion) END) / 60, 1) AS horas_promedio_resolucion
             FROM incidencias i
             JOIN contenedores c ON c.id_contenedor = i.id_contenedor" . $where
        );
        $consulta->execute($parametros);
        $fila = $consulta->fetch();
        return array_map(fn($v) => $v === null ? null : $v + 0, $fila);
    }

    /** Top de contenedores con más desbordes en un mes (AAAA-MM). */
    public function topContenedoresDesbordados(string $mes, int $limite = 5): array
    {
        $consulta = $this->conexion->prepare(
            "SELECT c.id_contenedor, c.ubicacion, c.estado, c.nivel_llenado, z.nombre_zona,
                    COUNT(i.id_incidencia) AS desbordes,
                    MAX(i.fecha_apertura) AS ultimo_desborde
             FROM incidencias i
             JOIN contenedores c ON c.id_contenedor = i.id_contenedor
             LEFT JOIN zonas z ON z.id_zona = c.id_zona
             WHERE i.tipo = 'desborde'
               AND DATE_FORMAT(i.fecha_apertura, '%Y-%m') = :mes
             GROUP BY c.id_contenedor, c.ubicacion, c.estado, c.nivel_llenado, z.nombre_zona
             ORDER BY desbordes DESC, ultimo_desborde DESC
             LIMIT " . max(1, min(20, $limite))
        );
        $consulta->execute(['mes' => $mes]);
        return $consulta->fetchAll();
    }

    /** Incidencias abiertas y resueltas por día en los últimos N días. */
    public function evolucionDiaria(int $dias = 30): array
    {
        $consulta = $this->conexion->prepare(
            "SELECT DATE(fecha_apertura) AS dia, COUNT(*) AS reportadas
             FROM incidencias
             WHERE fecha_apertura >= DATE_SUB(CURDATE(), INTERVAL :dias DAY)
             GROUP BY DATE(fecha_apertura)"
        );
        $consulta->bindValue(':dias', $dias - 1, PDO::PARAM_INT);
        $consulta->execute();
        $reportadas = array_column($consulta->fetchAll(), 'reportadas', 'dia');

        $consulta = $this->conexion->prepare(
            "SELECT DATE(fecha_resolucion) AS dia, COUNT(*) AS resueltas
             FROM incidencias
             WHERE fecha_resolucion >= DATE_SUB(CURDATE(), INTERVAL :dias DAY)
             GROUP BY DATE(fecha_resolucion)"
        );
        $consulta->bindValue(':dias', $dias - 1, PDO::PARAM_INT);
        $consulta->execute();
        $resueltas = array_column($consulta->fetchAll(), 'resueltas', 'dia');

        $serie = [];
        for ($i = $dias - 1; $i >= 0; $i--) {
            $dia = date('Y-m-d', strtotime("-{$i} days"));
            $serie[] = [
                'dia'        => $dia,
                'reportadas' => (int) ($reportadas[$dia] ?? 0),
                'resueltas'  => (int) ($resueltas[$dia] ?? 0),
            ];
        }
        return $serie;
    }

    public function conteoPorZona(?string $mes = null): array
    {
        $sql = "SELECT z.id_zona, z.nombre_zona,
                       COUNT(i.id_incidencia) AS total,
                       COALESCE(SUM(i.estado <> 'resuelta'), 0) AS pendientes
                FROM zonas z
                LEFT JOIN contenedores c ON c.id_zona = z.id_zona
                LEFT JOIN incidencias i ON i.id_contenedor = c.id_contenedor"
             . ($mes ? " AND DATE_FORMAT(i.fecha_apertura, '%Y-%m') = :mes" : '') . "
                GROUP BY z.id_zona, z.nombre_zona
                ORDER BY z.id_zona";
        $consulta = $this->conexion->prepare($sql);
        $consulta->execute($mes ? ['mes' => $mes] : []);
        return $consulta->fetchAll();
    }

    /** Rendimiento por cuadrilla en un mes: cuántas resolvió y en cuánto tiempo. */
    public function rendimientoCuadrillas(string $mes): array
    {
        $consulta = $this->conexion->prepare(
            "SELECT u.id_usuario, u.nombre, u.apellido,
                    COUNT(i.id_incidencia) AS resueltas,
                    ROUND(AVG(TIMESTAMPDIFF(MINUTE, i.fecha_apertura, i.fecha_resolucion)) / 60, 1) AS horas_promedio
             FROM cuadrillas cu
             JOIN usuarios u ON u.id_usuario = cu.id_usuario
             LEFT JOIN incidencias i ON i.id_cuadrilla = cu.id_usuario
                  AND i.estado = 'resuelta'
                  AND DATE_FORMAT(i.fecha_resolucion, '%Y-%m') = :mes
             GROUP BY u.id_usuario, u.nombre, u.apellido
             ORDER BY resueltas DESC"
        );
        $consulta->execute(['mes' => $mes]);
        return $consulta->fetchAll();
    }
}
