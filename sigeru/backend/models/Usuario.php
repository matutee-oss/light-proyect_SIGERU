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
        if (isset($datos['ci'])) {
            $campos[] = 'ci = :ci';
            $params['ci'] = $datos['ci'];
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

    /**
     * Corrección: cambiar el tipo_usuario desde el ABM no movía al usuario
     * de subtabla (seguía en vecinos aunque ahora fuera cuadrilla). Esto
     * rompía la especialización total y disjunta del modelo ER.
     */
    public function cambiarSubtipo(int $idUsuario, string $rolAnterior, string $rolNuevo, ?int $idCentro = null): void
    {
        if ($rolAnterior === $rolNuevo) {
            if ($rolNuevo === 'operario' && $idCentro) {
                $consulta = $this->conexion->prepare('UPDATE operarios SET id_centro = :centro WHERE id_usuario = :id');
                $consulta->execute(['centro' => $idCentro, 'id' => $idUsuario]);
            }
            return;
        }

        $tablaAnterior = $this->tablaSegunTipo($rolAnterior);
        $this->conexion->prepare("DELETE FROM {$tablaAnterior} WHERE id_usuario = :id")->execute(['id' => $idUsuario]);
        $this->insertarSubtipo($idUsuario, $rolNuevo, $idCentro);
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
            'SELECT u.id_usuario, u.nombre, u.apellido, u.ci, u.email, u.telefono, u.tipo_usuario,
                    u.estado, u.foto, u.creado_en, o.id_centro, v.id_zona
             FROM usuarios u
             LEFT JOIN operarios o ON o.id_usuario = u.id_usuario
             LEFT JOIN vecinos v ON v.id_usuario = u.id_usuario
             ORDER BY u.id_usuario DESC'
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
        $this->insertarSubtipo(
            $idUsuario,
            $datos['tipo_usuario'],
            !empty($datos['id_centro']) ? (int) $datos['id_centro'] : null,
            !empty($datos['id_zona']) ? (int) $datos['id_zona'] : null
        );

        return $idUsuario;
    }

    private function insertarSubtipo(int $idUsuario, string $tipoUsuario, ?int $idCentro = null, ?int $idZona = null): void
    {
        $tablaSubtipo = $this->tablaSegunTipo($tipoUsuario);

        if ($tablaSubtipo === 'operarios') {
            $sub = $this->conexion->prepare(
                'INSERT INTO operarios (id_usuario, id_centro) VALUES (:id_usuario, :id_centro)'
            );
            $sub->execute(['id_usuario' => $idUsuario, 'id_centro' => $idCentro]);
        } elseif ($tablaSubtipo === 'vecinos') {
            $sub = $this->conexion->prepare(
                'INSERT INTO vecinos (id_usuario, id_zona) VALUES (:id_usuario, :id_zona)'
            );
            $sub->execute(['id_usuario' => $idUsuario, 'id_zona' => $idZona]);
        } else {
            $sub = $this->conexion->prepare("INSERT INTO {$tablaSubtipo} (id_usuario) VALUES (:id_usuario)");
            $sub->execute(['id_usuario' => $idUsuario]);
        }
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

    public function listarCuadrillasActivas(): array
    {
        return $this->conexion->query(
            "SELECT u.id_usuario, u.nombre, u.apellido
             FROM usuarios u
             JOIN cuadrillas c ON c.id_usuario = u.id_usuario
             WHERE u.estado = 'activo'
             ORDER BY u.nombre"
        )->fetchAll();
    }

    public function esCuadrillaActiva(int $idUsuario): bool
    {
        $consulta = $this->conexion->prepare(
            "SELECT 1 FROM cuadrillas c JOIN usuarios u ON u.id_usuario = c.id_usuario
             WHERE c.id_usuario = :id AND u.estado = 'activo'"
        );
        $consulta->execute(['id' => $idUsuario]);
        return (bool) $consulta->fetchColumn();
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
            'INSERT INTO vecinos (id_usuario, id_zona) VALUES (:id, :id_zona)
             ON DUPLICATE KEY UPDATE id_zona = VALUES(id_zona)'
        );
        return $consulta->execute(['id_zona' => $idZona, 'id' => $idUsuario]);
    }

    /** Cierra todas las sesiones de un usuario (al desactivarlo o cambiarle la contraseña). */
    public function cerrarSesiones(int $idUsuario): void
    {
        $this->conexion->prepare('DELETE FROM sesiones WHERE id_usuario = :id')->execute(['id' => $idUsuario]);
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
