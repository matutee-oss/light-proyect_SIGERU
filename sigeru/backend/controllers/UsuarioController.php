<?php

require_once __DIR__ . '/../models/Usuario.php';

class UsuarioController
{
    private PDO $conexion;
    private Usuario $modelo;

    private const ROLES_VALIDOS = ['administrador', 'cuadrilla', 'operario', 'vecino'];
    private const ESTADOS_VALIDOS = ['activo', 'pendiente', 'inactivo'];

    public function __construct(PDO $conexion)
    {
        $this->conexion = $conexion;
        $this->modelo = new Usuario($conexion);
    }

    public function listar(): array
    {
        return $this->modelo->listarTodos();
    }

    public function obtener(int $id): ?array
    {
        $usuario = $this->modelo->buscarPorId($id);
        if ($usuario) {
            unset($usuario['password_hash']);
        }
        return $usuario ?: null;
    }

    public function crear(array $datos): array
    {
        $camposObligatorios = ['nombre', 'apellido', 'ci', 'email', 'password', 'tipo_usuario'];
        foreach ($camposObligatorios as $campo) {
            if (empty($datos[$campo])) {
                return ['exito' => false, 'mensaje' => "Falta el campo obligatorio: {$campo}"];
            }
        }

        if (!in_array($datos['tipo_usuario'], self::ROLES_VALIDOS, true)) {
            return ['exito' => false, 'mensaje' => 'tipo_usuario inválido'];
        }

        if ($this->modelo->buscarPorEmail($datos['email'])) {
            return ['exito' => false, 'mensaje' => 'Ya existe un usuario con ese email'];
        }

        if ($this->modelo->buscarPorCI($datos['ci'])) {
            return ['exito' => false, 'mensaje' => 'Ya existe un usuario con esa cédula'];
        }

        $datos['password_hash'] = password_hash($datos['password'], PASSWORD_DEFAULT);
        // El admin crea usuarios directamente activos
        $datos['estado'] = $datos['estado'] ?? 'activo';

        try {
            $id = $this->modelo->crear($datos);
            return ['exito' => true, 'id' => $id, 'mensaje' => 'Usuario creado correctamente'];
        } catch (Exception $e) {
            return ['exito' => false, 'mensaje' => 'No se pudo crear el usuario'];
        }
    }

    public function actualizar(int $id, array $datos): array
    {
        if (!$this->modelo->buscarPorId($id)) {
            return ['exito' => false, 'mensaje' => 'Usuario no encontrado'];
        }

        if (isset($datos['tipo_usuario']) && !in_array($datos['tipo_usuario'], self::ROLES_VALIDOS, true)) {
            return ['exito' => false, 'mensaje' => 'tipo_usuario inválido'];
        }

        if (isset($datos['estado']) && !in_array($datos['estado'], self::ESTADOS_VALIDOS, true)) {
            return ['exito' => false, 'mensaje' => 'estado inválido'];
        }

        // Si viene nueva contraseña, hashearla
        if (!empty($datos['password'])) {
            $datos['password_hash'] = password_hash($datos['password'], PASSWORD_DEFAULT);
        }

        $this->modelo->actualizarCompleto($id, $datos);
        return ['exito' => true, 'mensaje' => 'Usuario actualizado correctamente'];
    }

    public function eliminar(int $id): array
    {
        if (!$this->modelo->buscarPorId($id)) {
            return ['exito' => false, 'mensaje' => 'Usuario no encontrado'];
        }

        $this->modelo->eliminar($id);
        return ['exito' => true, 'mensaje' => 'Usuario eliminado'];
    }
}
