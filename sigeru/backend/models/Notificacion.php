<?php

class Notificacion
{
    private PDO $conexion;

    public function __construct(PDO $conexion)
    {
        $this->conexion = $conexion;
    }

    public function crear(int $idUsuario, string $tipo, string $nivel, string $titulo, string $mensaje, ?string $enlace = null): int
    {
        $consulta = $this->conexion->prepare(
            'INSERT INTO notificaciones (id_usuario, tipo, nivel, titulo, mensaje, enlace)
             VALUES (:id_usuario, :tipo, :nivel, :titulo, :mensaje, :enlace)'
        );
        $consulta->execute([
            'id_usuario' => $idUsuario,
            'tipo'       => $tipo,
            'nivel'      => $nivel,
            'titulo'     => mb_substr($titulo, 0, 150),
            'mensaje'    => mb_substr($mensaje, 0, 500),
            'enlace'     => $enlace,
        ]);
        return (int) $this->conexion->lastInsertId();
    }

    /** Envía la misma notificación a todos los usuarios activos de los roles indicados. */
    public function crearParaRoles(array $roles, string $tipo, string $nivel, string $titulo, string $mensaje, ?string $enlace = null, ?int $excluirUsuario = null): int
    {
        if (empty($roles)) {
            return 0;
        }

        $marcadores = [];
        $parametros = [
            'tipo' => $tipo, 'nivel' => $nivel,
            'titulo' => mb_substr($titulo, 0, 150), 'mensaje' => mb_substr($mensaje, 0, 500),
            'enlace' => $enlace, 'excluir' => $excluirUsuario ?? 0,
        ];
        foreach (array_values($roles) as $i => $rol) {
            $marcadores[] = ":rol{$i}";
            $parametros["rol{$i}"] = $rol;
        }

        $consulta = $this->conexion->prepare(
            "INSERT INTO notificaciones (id_usuario, tipo, nivel, titulo, mensaje, enlace)
             SELECT id_usuario, :tipo, :nivel, :titulo, :mensaje, :enlace
             FROM usuarios
             WHERE estado = 'activo'
               AND tipo_usuario IN (" . implode(', ', $marcadores) . ")
               AND id_usuario <> :excluir"
        );
        $consulta->execute($parametros);
        return $consulta->rowCount();
    }

    /** Notifica a los vecinos de una zona (o a todos si la zona es null). */
    public function crearParaVecinos(?int $idZona, string $titulo, string $mensaje, ?string $enlace = null): int
    {
        $sql = "INSERT INTO notificaciones (id_usuario, tipo, nivel, titulo, mensaje, enlace)
                SELECT v.id_usuario, 'aviso', 'info', :titulo, :mensaje, :enlace
                FROM vecinos v
                JOIN usuarios u ON u.id_usuario = v.id_usuario
                WHERE u.estado = 'activo'";
        $parametros = [
            'titulo' => mb_substr($titulo, 0, 150),
            'mensaje' => mb_substr($mensaje, 0, 500),
            'enlace' => $enlace,
        ];
        if ($idZona !== null) {
            $sql .= ' AND v.id_zona = :id_zona';
            $parametros['id_zona'] = $idZona;
        }

        $consulta = $this->conexion->prepare($sql);
        $consulta->execute($parametros);
        return $consulta->rowCount();
    }

    /** Notifica a los operarios de un centro de acopio. */
    public function crearParaOperariosDeCentro(int $idCentro, string $tipo, string $nivel, string $titulo, string $mensaje, ?string $enlace = null): int
    {
        $consulta = $this->conexion->prepare(
            "INSERT INTO notificaciones (id_usuario, tipo, nivel, titulo, mensaje, enlace)
             SELECT o.id_usuario, :tipo, :nivel, :titulo, :mensaje, :enlace
             FROM operarios o
             JOIN usuarios u ON u.id_usuario = o.id_usuario
             WHERE u.estado = 'activo' AND o.id_centro = :id_centro"
        );
        $consulta->execute([
            'tipo' => $tipo, 'nivel' => $nivel, 'titulo' => $titulo,
            'mensaje' => $mensaje, 'enlace' => $enlace, 'id_centro' => $idCentro,
        ]);
        return $consulta->rowCount();
    }

    public function listarPorUsuario(int $idUsuario, bool $soloNoLeidas = false, int $limite = 30): array
    {
        $consulta = $this->conexion->prepare(
            'SELECT * FROM notificaciones
             WHERE id_usuario = :id_usuario' . ($soloNoLeidas ? ' AND leida = 0' : '') . '
             ORDER BY creada_en DESC, id_notificacion DESC
             LIMIT ' . max(1, min(100, $limite))
        );
        $consulta->execute(['id_usuario' => $idUsuario]);
        return $consulta->fetchAll();
    }

    public function contarNoLeidas(int $idUsuario): int
    {
        $consulta = $this->conexion->prepare(
            'SELECT COUNT(*) FROM notificaciones WHERE id_usuario = :id_usuario AND leida = 0'
        );
        $consulta->execute(['id_usuario' => $idUsuario]);
        return (int) $consulta->fetchColumn();
    }

    /** Devuelve las notificaciones posteriores a un id (para el aviso emergente del frontend). */
    public function listarNuevas(int $idUsuario, int $despuesDeId): array
    {
        $consulta = $this->conexion->prepare(
            'SELECT * FROM notificaciones
             WHERE id_usuario = :id_usuario AND id_notificacion > :desde AND leida = 0
             ORDER BY id_notificacion ASC
             LIMIT 10'
        );
        $consulta->execute(['id_usuario' => $idUsuario, 'desde' => $despuesDeId]);
        return $consulta->fetchAll();
    }

    /** Solo marca si la notificación es del usuario (un usuario no puede tocar las de otro). */
    public function marcarLeida(int $idNotificacion, int $idUsuario): bool
    {
        $consulta = $this->conexion->prepare(
            'UPDATE notificaciones SET leida = 1
             WHERE id_notificacion = :id AND id_usuario = :id_usuario'
        );
        $consulta->execute(['id' => $idNotificacion, 'id_usuario' => $idUsuario]);
        return $consulta->rowCount() > 0;
    }

    public function marcarTodasLeidas(int $idUsuario): int
    {
        $consulta = $this->conexion->prepare(
            'UPDATE notificaciones SET leida = 1 WHERE id_usuario = :id_usuario AND leida = 0'
        );
        $consulta->execute(['id_usuario' => $idUsuario]);
        return $consulta->rowCount();
    }

    public function eliminar(int $idNotificacion, int $idUsuario): bool
    {
        $consulta = $this->conexion->prepare(
            'DELETE FROM notificaciones WHERE id_notificacion = :id AND id_usuario = :id_usuario'
        );
        $consulta->execute(['id' => $idNotificacion, 'id_usuario' => $idUsuario]);
        return $consulta->rowCount() > 0;
    }
}
