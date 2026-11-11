<?php

class Ruta
{
    private PDO $conexion;

    public function __construct(PDO $conexion)
    {
        $this->conexion = $conexion;
    }

    public function obtenerRutaHoy(int $idCuadrilla): ?array
    {
        $consulta = $this->conexion->prepare(
            'SELECT r.*, c.matricula AS camion_matricula
             FROM rutas r
             LEFT JOIN camiones c ON c.id_camion = r.id_camion
             WHERE r.id_cuadrilla = :id_cuadrilla AND r.fecha = CURDATE()
             ORDER BY r.id_ruta DESC
             LIMIT 1'
        );
        $consulta->execute(['id_cuadrilla' => $idCuadrilla]);
        $ruta = $consulta->fetch();

        if (!$ruta) return null;

        $consulta = $this->conexion->prepare(
            'SELECT rc.*, c.ubicacion, c.tipo_residuo, c.estado, c.latitud, c.longitud, c.nivel_llenado
             FROM ruta_contenedor rc
             JOIN contenedores c ON c.id_contenedor = rc.id_contenedor
             WHERE rc.id_ruta = :id_ruta
             ORDER BY rc.orden'
        );
        $consulta->execute(['id_ruta' => $ruta['id_ruta']]);
        $ruta['paradas'] = $consulta->fetchAll();

        return $ruta;
    }

    public function marcarParada(int $idRuta, int $idContenedor, bool $atendido): bool
    {
        $consulta = $this->conexion->prepare(
            'UPDATE ruta_contenedor
             SET atendido = :atendido, atendido_en = ' . ($atendido ? 'NOW()' : 'NULL') . '
             WHERE id_ruta = :id_ruta AND id_contenedor = :id_contenedor'
        );
        return $consulta->execute([
            'atendido'      => $atendido ? 1 : 0,
            'id_ruta'       => $idRuta,
            'id_contenedor' => $idContenedor,
        ]);
    }

    public function pertenece(int $idRuta, int $idCuadrilla): bool
    {
        $consulta = $this->conexion->prepare(
            'SELECT 1 FROM rutas WHERE id_ruta = :id_ruta AND id_cuadrilla = :id_cuadrilla'
        );
        $consulta->execute(['id_ruta' => $idRuta, 'id_cuadrilla' => $idCuadrilla]);
        return (bool) $consulta->fetchColumn();
    }

    public function buscarPorId(int $idRuta): ?array
    {
        $consulta = $this->conexion->prepare('SELECT * FROM rutas WHERE id_ruta = :id');
        $consulta->execute(['id' => $idRuta]);
        $fila = $consulta->fetch();
        return $fila ?: null;
    }

    /**
     * Corrección: antes insertaba en columnas cuadrilla_id / camion_id que no
     * existen (son id_cuadrilla / id_camion) y la ruta de demo siempre fallaba.
     * Ahora prioriza los contenedores más llenos o desbordados y asigna un
     * camión disponible si lo hay.
     */
    public function crearRutaDemo(int $idCuadrilla, ?int $idCamion = null, int $cantidadParadas = 6): int
    {
        if ($idCamion === null) {
            $idCamion = $this->conexion->query(
                "SELECT id_camion FROM camiones WHERE estado IN ('disponible', 'en_ruta') ORDER BY estado = 'disponible' DESC, id_camion LIMIT 1"
            )->fetchColumn() ?: null;
        }

        $this->conexion->beginTransaction();
        try {
            $consulta = $this->conexion->prepare(
                'INSERT INTO rutas (nombre, zona, id_cuadrilla, id_camion, descripcion, fecha)
                 VALUES (:nombre, :zona, :id_cuadrilla, :id_camion, :descripcion, CURDATE())'
            );
            $consulta->execute([
                'nombre'       => 'Ruta ' . date('d/m/Y'),
                'zona'         => 'Prioridad por nivel de llenado',
                'id_cuadrilla' => $idCuadrilla,
                'id_camion'    => $idCamion,
                'descripcion'  => 'Generada automáticamente con los contenedores más llenos o desbordados.',
            ]);
            $idRuta = (int) $this->conexion->lastInsertId();

            $contenedores = $this->conexion->query(
                "SELECT id_contenedor FROM contenedores
                 WHERE estado <> 'roto'
                 ORDER BY estado = 'desbordado' DESC, nivel_llenado DESC, id_contenedor
                 LIMIT " . max(1, min(20, $cantidadParadas))
            )->fetchAll();

            $insertar = $this->conexion->prepare(
                'INSERT INTO ruta_contenedor (id_ruta, id_contenedor, orden) VALUES (:r, :c, :o)'
            );
            foreach ($contenedores as $indice => $fila) {
                $insertar->execute(['r' => $idRuta, 'c' => $fila['id_contenedor'], 'o' => $indice + 1]);
            }

            $this->conexion->commit();
            return $idRuta;
        } catch (Throwable $e) {
            $this->conexion->rollBack();
            throw $e;
        }
    }
}
