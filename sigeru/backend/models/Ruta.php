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
             WHERE r.id_cuadrilla = :id_cuadrilla AND DATE(r.fecha) = CURDATE()
             LIMIT 1'
        );
        $consulta->execute(['id_cuadrilla' => $idCuadrilla]);
        $ruta = $consulta->fetch();

        if (!$ruta) return null;

        $consulta = $this->conexion->prepare(
            'SELECT rc.*, c.ubicacion, c.tipo_residuo, c.estado, c.latitud, c.longitud
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

    public function crearRutaDemo(int $idCuadrilla, ?int $idCamion): int
    {
        $consulta = $this->conexion->prepare(
            'INSERT INTO rutas (nombre, zona, cuadrilla_id, camion_id, fecha)
             VALUES (:nombre, :zona, :cuadrilla_id, :camion_id, CURDATE())'
        );
        $consulta->execute([
            'nombre'       => 'Ruta ' . date('Y-m-d'),
            'zona'         => 'Zona asignada',
            'cuadrilla_id' => $idCuadrilla,
            'camion_id'    => $idCamion,
        ]);
        $idRuta = (int) $this->conexion->lastInsertId();

        $contenedores = $this->conexion->query(
            'SELECT id_contenedor FROM contenedores ORDER BY id_contenedor LIMIT 4'
        )->fetchAll();

        $insertar = $this->conexion->prepare(
            'INSERT INTO ruta_contenedor (id_ruta, id_contenedor, orden) VALUES (:r, :c, :o)'
        );
        foreach ($contenedores as $indice => $fila) {
            $insertar->execute([
                'r' => $idRuta,
                'c' => $fila['id_contenedor'],
                'o' => $indice + 1,
            ]);
        }

        return $idRuta;
    }
}
