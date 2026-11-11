<?php

require_once __DIR__ . '/../helpers/validacion.php';

class Contenedor
{
    private PDO $conexion;

    private const SELECT_BASE = "
        SELECT c.*, z.nombre_zona,
               (SELECT COUNT(*) FROM incidencias i
                 WHERE i.id_contenedor = c.id_contenedor AND i.estado <> 'resuelta') AS incidencias_pendientes
        FROM contenedores c
        LEFT JOIN zonas z ON z.id_zona = c.id_zona
    ";

    public function __construct(PDO $conexion)
    {
        $this->conexion = $conexion;
    }

    public function listarTodos(): array
    {
        return $this->conexion->query(self::SELECT_BASE . ' ORDER BY c.id_contenedor DESC')->fetchAll();
    }

    /** Filtros opcionales: id_zona, estado, nivel_minimo. */
    public function listarConFiltros(array $filtros = []): array
    {
        $condiciones = [];
        $parametros = [];
        if (!empty($filtros['id_zona'])) {
            $condiciones[] = 'c.id_zona = :id_zona';
            $parametros['id_zona'] = (int) $filtros['id_zona'];
        }
        if (!empty($filtros['estado'])) {
            $condiciones[] = 'c.estado = :estado';
            $parametros['estado'] = $filtros['estado'];
        }
        if (isset($filtros['nivel_minimo']) && $filtros['nivel_minimo'] !== '') {
            $condiciones[] = 'c.nivel_llenado >= :nivel';
            $parametros['nivel'] = (int) $filtros['nivel_minimo'];
        }

        $sql = self::SELECT_BASE
             . ($condiciones ? ' WHERE ' . implode(' AND ', $condiciones) : '')
             . ' ORDER BY c.nivel_llenado DESC, c.ubicacion';
        $consulta = $this->conexion->prepare($sql);
        $consulta->execute($parametros);
        return $consulta->fetchAll();
    }

    public function listarPorZona(int $idZona): array
    {
        return $this->listarConFiltros(['id_zona' => $idZona]);
    }

    public function buscarPorId(int $id): ?array
    {
        $consulta = $this->conexion->prepare(self::SELECT_BASE . ' WHERE c.id_contenedor = :id');
        $consulta->execute(['id' => $id]);
        $resultado = $consulta->fetch();
        return $resultado ?: null;
    }

    public function crear(array $datos): int
    {
        $consulta = $this->conexion->prepare(
            'INSERT INTO contenedores (ubicacion, tipo_residuo, estado, id_zona, latitud, longitud, capacidad_litros, nivel_llenado)
             VALUES (:ubicacion, :tipo_residuo, :estado, :id_zona, :latitud, :longitud, :capacidad_litros, :nivel_llenado)'
        );
        $consulta->execute([
            'ubicacion'        => trim($datos['ubicacion']),
            'tipo_residuo'     => trim($datos['tipo_residuo']),
            'estado'           => $datos['estado'] ?? 'funcional',
            'id_zona'          => Validacion::enteroONulo($datos['id_zona'] ?? null),
            'latitud'          => Validacion::numeroONulo($datos['latitud'] ?? null),
            'longitud'         => Validacion::numeroONulo($datos['longitud'] ?? null),
            'capacidad_litros' => (int) ($datos['capacidad_litros'] ?? 2400),
            'nivel_llenado'    => (int) ($datos['nivel_llenado'] ?? 0),
        ]);
        return (int) $this->conexion->lastInsertId();
    }

    /**
     * Corrección: antes no se actualizaba la zona (id_zona) al editar.
     * Se mezclan los datos nuevos con los actuales para permitir ediciones parciales.
     */
    public function actualizar(int $id, array $datos, ?array $actual = null): bool
    {
        $actual = $actual ?? $this->buscarPorId($id) ?? [];
        $valor = fn(string $campo) => array_key_exists($campo, $datos) ? $datos[$campo] : ($actual[$campo] ?? null);

        $consulta = $this->conexion->prepare(
            'UPDATE contenedores
             SET ubicacion = :ubicacion, tipo_residuo = :tipo_residuo, estado = :estado,
                 id_zona = :id_zona, latitud = :latitud, longitud = :longitud,
                 capacidad_litros = :capacidad_litros, nivel_llenado = :nivel_llenado
             WHERE id_contenedor = :id'
        );
        return $consulta->execute([
            'ubicacion'        => trim((string) $valor('ubicacion')),
            'tipo_residuo'     => trim((string) $valor('tipo_residuo')),
            'estado'           => $valor('estado') ?? 'funcional',
            'id_zona'          => Validacion::enteroONulo($valor('id_zona')),
            'latitud'          => Validacion::numeroONulo($valor('latitud')),
            'longitud'         => Validacion::numeroONulo($valor('longitud')),
            'capacidad_litros' => (int) ($valor('capacidad_litros') ?? 2400),
            'nivel_llenado'    => (int) ($valor('nivel_llenado') ?? 0),
            'id'               => $id,
        ]);
    }

    /** Nivel de llenado (lectura de sensor o reporte manual). Al llegar a 100 % queda desbordado. */
    public function actualizarNivel(int $id, int $nivel): bool
    {
        $consulta = $this->conexion->prepare(
            "UPDATE contenedores
             SET nivel_llenado = :nivel,
                 estado = CASE
                     WHEN :nivel2 >= 100 AND estado = 'funcional' THEN 'desbordado'
                     WHEN :nivel3 < 100 AND estado = 'desbordado' THEN 'funcional'
                     ELSE estado END
             WHERE id_contenedor = :id"
        );
        return $consulta->execute(['nivel' => $nivel, 'nivel2' => $nivel, 'nivel3' => $nivel, 'id' => $id]);
    }

    /** Después de una recolección: vacío y, si estaba desbordado, vuelve a funcional. */
    public function marcarVaciado(int $id): bool
    {
        $consulta = $this->conexion->prepare(
            "UPDATE contenedores
             SET nivel_llenado = 0,
                 ultima_recoleccion = NOW(),
                 estado = IF(estado = 'desbordado', 'funcional', estado)
             WHERE id_contenedor = :id"
        );
        return $consulta->execute(['id' => $id]);
    }

    public function marcarEstado(int $id, string $estado): bool
    {
        $consulta = $this->conexion->prepare('UPDATE contenedores SET estado = :estado WHERE id_contenedor = :id');
        return $consulta->execute(['estado' => $estado, 'id' => $id]);
    }

    public function eliminar(int $id): bool
    {
        $consulta = $this->conexion->prepare('DELETE FROM contenedores WHERE id_contenedor = :id');
        return $consulta->execute(['id' => $id]);
    }

    public function resumenEstados(): array
    {
        $fila = $this->conexion->query(
            "SELECT COUNT(*) AS total,
                    COALESCE(SUM(estado = 'funcional'), 0)  AS funcionales,
                    COALESCE(SUM(estado = 'roto'), 0)       AS rotos,
                    COALESCE(SUM(estado = 'desbordado'), 0) AS desbordados,
                    COALESCE(SUM(nivel_llenado >= 80), 0)   AS casi_llenos,
                    ROUND(COALESCE(AVG(nivel_llenado), 0))  AS nivel_promedio
             FROM contenedores"
        )->fetch();
        return array_map('intval', $fila);
    }
}
