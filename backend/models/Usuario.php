<?php

class Usuario
{
    private PDO $conexion;

    public function __construct(PDO $conexion)
    {
        $this->conexion = $conexion;
    }

    public function buscarPorEmail(string $email): ?array
    {
        $consulta = $this->conexion->prepare(
            'SELECT * FROM usuarios WHERE email = :email'
        );
        $consulta->execute(['email' => $email]);
        $resultado = $consulta->fetch();
        return $resultado ?: null;
    }

    public function buscarPorId(int $idUsuario): ?array
    {
        $consulta = $this->conexion->prepare('SELECT * FROM usuarios WHERE id_usuario = :id');
        $consulta->execute(['id' => $idUsuario]);
        $resultado = $consulta->fetch();
        return $resultado ?: null;
    }

    public function buscarPorCI(string $ci): ?array
    {
        $consulta = $this->conexion->prepare('SELECT * FROM usuarios WHERE ci = :ci');
        $consulta->execute(['ci' => $ci]);
        $resultado = $consulta->fetch();
        return $resultado ?: null;
    }

    public function actualizarPerfil(int $idUsuario, array $datos): bool
    {
        $consulta = $this->conexion->prepare(
            'UPDATE usuarios SET nombre = :nombre, apellido = :apellido, telefono = :telefono
             WHERE id_usuario = :id_usuario'
        );
        return $consulta->execute([
            'nombre'     => $datos['nombre'],
            'apellido'   => $datos['apellido'],
            'telefono'   => $datos['telefono'] ?? null,
            'id_usuario' => $idUsuario,
        ]);
    }

    public function actualizarCompleto(int $idUsuario, array $datos): bool
    {
        $campos = [];
        $params = ['id_usuario' => $idUsuario];

        if (isset($datos['nombre'])) {
            $campos[] = 'nombre = :nombre';
            $params['nombre'] = $datos['nombre'];
        }
        if (isset($datos['apellido'])) {
            $campos[] = 'apellido = :apellido';
            $params['apellido'] = $datos['apellido'];
        }
        if (isset($datos['telefono'])) {
            $campos[] = 'telefono = :telefono';
            $params['telefono'] = $datos['telefono'];
        }
        if (isset($datos['email'])) {
            $campos[] = 'email = :email';
            $params['email'] = $datos['email'];
        }
        if (isset($datos['tipo_usuario'])) {
            $campos[] = 'tipo_usuario = :tipo_usuario';
            $params['tipo_usuario'] = $datos['tipo_usuario'];
        }
        if (isset($datos['estado'])) {
            $campos[] = 'estado = :estado';
            $params['estado'] = $datos['estado'];
        }
        if (isset($datos['password_hash'])) {
            $campos[] = 'password_hash = :password_hash';
            $params['password_hash'] = $datos['password_hash'];
        }

        if (empty($campos)) return false;

        $sql = 'UPDATE usuarios SET ' . implode(', ', $campos) . ' WHERE id_usuario = :id_usuario';
        $consulta = $this->conexion->prepare($sql);
        return $consulta->execute($params);
    }

    public function actualizarFoto(int $idUsuario, string $rutaFoto): bool
    {
        $consulta = $this->conexion->prepare(
            'UPDATE usuarios SET foto = :foto WHERE id_usuario = :id_usuario'
        );
        return $consulta->execute(['foto' => $rutaFoto, 'id_usuario' => $idUsuario]);
    }

    public function listarTodos(): array
    {
        $consulta = $this->conexion->query(
            'SELECT id_usuario, nombre, apellido, ci, email, telefono, tipo_usuario, estado, foto, creado_en
             FROM usuarios
             ORDER BY id_usuario DESC'
        );
        return $consulta->fetchAll();
    }

    public function crear(array $datos): int
    {
        $consulta = $this->conexion->prepare(
            'INSERT INTO usuarios (nombre, apellido, ci, telefono, email, password_hash, tipo_usuario, estado)
             VALUES (:nombre, :apellido, :ci, :telefono, :email, :password_hash, :tipo_usuario, :estado)'
        );
        $consulta->execute([
            'nombre'        => $datos['nombre'],
            'apellido'      => $datos['apellido'],
            'ci'            => $datos['ci'],
            'telefono'      => $datos['telefono'] ?? null,
            'email'         => $datos['email'],
            'password_hash' => $datos['password_hash'],
            'tipo_usuario'  => $datos['tipo_usuario'],
            'estado'        => $datos['estado'] ?? 'pendiente',
        ]);

        $idUsuario = (int) $this->conexion->lastInsertId();
        $tablaSubtipo = $this->tablaSegunTipo($datos['tipo_usuario']);

        if ($tablaSubtipo === 'operarios') {
            $sub = $this->conexion->prepare(
                'INSERT INTO operarios (id_usuario, id_centro) VALUES (:id_usuario, :id_centro)'
            );
            $sub->execute([
                'id_usuario' => $idUsuario,
                'id_centro'  => $datos['id_centro'] ?? null,
            ]);
        } else {
            $sub = $this->conexion->prepare(
                "INSERT INTO {$tablaSubtipo} (id_usuario) VALUES (:id_usuario)"
            );
            $sub->execute(['id_usuario' => $idUsuario]);
        }

        return $idUsuario;
    }

    public function eliminar(int $idUsuario): bool
    {
        $consulta = $this->conexion->prepare('DELETE FROM usuarios WHERE id_usuario = :id');
        return $consulta->execute(['id' => $idUsuario]);
    }

    public function actualizarEstado(int $idUsuario, string $estado): bool
    {
        $consulta = $this->conexion->prepare(
            'UPDATE usuarios SET estado = :estado WHERE id_usuario = :id_usuario'
        );
        return $consulta->execute(['estado' => $estado, 'id_usuario' => $idUsuario]);
    }

    public function listarPendientes(): array
    {
        $consulta = $this->conexion->query(
            "SELECT id_usuario, nombre, apellido, ci, email, tipo_usuario, creado_en
             FROM usuarios
             WHERE estado = 'pendiente'
             ORDER BY creado_en DESC"
        );
        return $consulta->fetchAll();
    }

    public function obtenerZonaVecino(int $idUsuario): ?int
    {
        $consulta = $this->conexion->prepare(
            'SELECT id_zona FROM vecinos WHERE id_usuario = :id'
        );
        $consulta->execute(['id' => $idUsuario]);
        $zona = $consulta->fetchColumn();
        return $zona ? (int) $zona : null;
    }

    public function asignarZonaVecino(int $idUsuario, int $idZona): bool
    {
        $consulta = $this->conexion->prepare(
            'UPDATE vecinos SET id_zona = :id_zona WHERE id_usuario = :id'
        );
        return $consulta->execute(['id_zona' => $idZona, 'id' => $idUsuario]);
    }

    private function tablaSegunTipo(string $tipoUsuario): string
    {
        $mapa = [
            'administrador' => 'administradores',
            'cuadrilla'     => 'cuadrillas',
            'operario'      => 'operarios',
            'vecino'        => 'vecinos',
        ];

        if (!isset($mapa[$tipoUsuario])) {
            throw new InvalidArgumentException('tipo_usuario inválido');
        }

        return $mapa[$tipoUsuario];
    }
}
