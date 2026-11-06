<?php

require_once __DIR__ . '/../helpers/validacion.php';
require_once __DIR__ . '/../models/Usuario.php';
require_once __DIR__ . '/../models/CentroAcopio.php';

class UsuarioController
{
    private PDO $conexion;
    private Usuario $modelo;
    private CentroAcopio $modeloCentro;

    public function __construct(PDO $conexion, ?Usuario $modelo = null, ?CentroAcopio $modeloCentro = null)
    {
        $this->conexion = $conexion;
        $this->modelo = $modelo ?? new Usuario($conexion);
        $this->modeloCentro = $modeloCentro ?? new CentroAcopio($conexion);
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
        $faltante = Validacion::faltante($datos, ['nombre', 'apellido', 'ci', 'email', 'password', 'tipo_usuario']);
        if ($faltante !== null) {
            return ['exito' => false, 'mensaje' => "Falta el campo obligatorio: {$faltante}"];
        }

        if (!Validacion::enLista($datos['tipo_usuario'], Validacion::ROLES)) {
            return ['exito' => false, 'mensaje' => 'tipo_usuario inválido'];
        }
        if (isset($datos['estado']) && !Validacion::enLista($datos['estado'], Validacion::ESTADOS_USUARIO)) {
            return ['exito' => false, 'mensaje' => 'estado inválido'];
        }
        if (!Validacion::email($datos['email'])) {
            return ['exito' => false, 'mensaje' => 'El email no tiene un formato válido'];
        }
        if (!Validacion::cedulaUruguaya($datos['ci'])) {
            return ['exito' => false, 'mensaje' => 'La cédula no es válida (revisá el dígito verificador)'];
        }
        if (!Validacion::contrasena($datos['password'])) {
            return ['exito' => false, 'mensaje' => 'La contraseña debe tener al menos 8 caracteres'];
        }

        $datos['ci'] = Validacion::normalizarCedula($datos['ci']);
        $datos['email'] = mb_strtolower(trim($datos['email']));

        if ($datos['tipo_usuario'] === 'operario') {
            if (empty($datos['id_centro']) || !$this->modeloCentro->buscarPorId((int) $datos['id_centro'])) {
                return ['exito' => false, 'mensaje' => 'Los operarios deben tener un centro de acopio válido'];
            }
        }

        if ($this->modelo->buscarPorEmail($datos['email'])) {
            return ['exito' => false, 'mensaje' => 'Ya existe un usuario con ese email', 'codigo' => 409];
        }
        if ($this->modelo->buscarPorCI($datos['ci'])) {
            return ['exito' => false, 'mensaje' => 'Ya existe un usuario con esa cédula', 'codigo' => 409];
        }

        $datos['password_hash'] = password_hash($datos['password'], PASSWORD_DEFAULT);
        // El admin crea usuarios directamente activos
        $datos['estado'] = $datos['estado'] ?? 'activo';

        $this->conexion->beginTransaction();
        try {
            $id = $this->modelo->crear($datos);
            $this->conexion->commit();
            return ['exito' => true, 'id' => $id, 'mensaje' => 'Usuario creado correctamente'];
        } catch (Throwable $e) {
            $this->conexion->rollBack();
            return ['exito' => false, 'mensaje' => 'No se pudo crear el usuario'];
        }
    }

    /**
     * @param int|null $idAdministrador el admin que hace el cambio (no puede quitarse permisos a sí mismo)
     */
    public function actualizar(int $id, array $datos, ?int $idAdministrador = null): array
    {
        $actual = $this->modelo->buscarPorId($id);
        if (!$actual) {
            return ['exito' => false, 'mensaje' => 'Usuario no encontrado', 'codigo' => 404];
        }

        if (isset($datos['tipo_usuario']) && !Validacion::enLista($datos['tipo_usuario'], Validacion::ROLES)) {
            return ['exito' => false, 'mensaje' => 'tipo_usuario inválido'];
        }
        if (isset($datos['estado']) && !Validacion::enLista($datos['estado'], Validacion::ESTADOS_USUARIO)) {
            return ['exito' => false, 'mensaje' => 'estado inválido'];
        }
        if ($idAdministrador === $id) {
            if ((isset($datos['estado']) && $datos['estado'] !== 'activo')
                || (isset($datos['tipo_usuario']) && $datos['tipo_usuario'] !== 'administrador')) {
                return ['exito' => false, 'mensaje' => 'No podés desactivarte ni quitarte el rol de administrador'];
            }
        }
        if (isset($datos['email'])) {
            if (!Validacion::email($datos['email'])) {
                return ['exito' => false, 'mensaje' => 'El email no tiene un formato válido'];
            }
            $datos['email'] = mb_strtolower(trim($datos['email']));
            $otro = $this->modelo->buscarPorEmail($datos['email']);
            if ($otro && (int) $otro['id_usuario'] !== $id) {
                return ['exito' => false, 'mensaje' => 'Ya existe un usuario con ese email', 'codigo' => 409];
            }
        }
        if (isset($datos['ci']) && $datos['ci'] !== '') {
            if (!Validacion::cedulaUruguaya($datos['ci'])) {
                return ['exito' => false, 'mensaje' => 'La cédula no es válida (revisá el dígito verificador)'];
            }
            $datos['ci'] = Validacion::normalizarCedula($datos['ci']);
            $otro = $this->modelo->buscarPorCI($datos['ci']);
            if ($otro && (int) $otro['id_usuario'] !== $id) {
                return ['exito' => false, 'mensaje' => 'Ya existe un usuario con esa cédula', 'codigo' => 409];
            }
        } else {
            unset($datos['ci']);
        }

        if (!empty($datos['password'])) {
            if (!Validacion::contrasena($datos['password'])) {
                return ['exito' => false, 'mensaje' => 'La contraseña debe tener al menos 8 caracteres'];
            }
            $datos['password_hash'] = password_hash($datos['password'], PASSWORD_DEFAULT);
        }

        $rolNuevo = $datos['tipo_usuario'] ?? $actual['tipo_usuario'];
        $idCentro = !empty($datos['id_centro']) ? (int) $datos['id_centro'] : null;
        if ($rolNuevo === 'operario' && $actual['tipo_usuario'] !== 'operario') {
            if (!$idCentro || !$this->modeloCentro->buscarPorId($idCentro)) {
                return ['exito' => false, 'mensaje' => 'Para pasar a operario hay que indicar un centro de acopio válido'];
            }
        }

        $this->conexion->beginTransaction();
        try {
            $this->modelo->actualizarCompleto($id, $datos);
            $this->modelo->cambiarSubtipo($id, $actual['tipo_usuario'], $rolNuevo, $idCentro);

            // Si se desactiva o se cambia la contraseña, se cierran sus sesiones abiertas
            if ((isset($datos['estado']) && $datos['estado'] !== 'activo') || isset($datos['password_hash'])) {
                $this->modelo->cerrarSesiones($id);
            }
            $this->conexion->commit();
        } catch (Throwable $e) {
            $this->conexion->rollBack();
            return ['exito' => false, 'mensaje' => 'No se pudo actualizar el usuario'];
        }

        return ['exito' => true, 'mensaje' => 'Usuario actualizado correctamente'];
    }

    public function eliminar(int $id): array
    {
        if (!$this->modelo->buscarPorId($id)) {
            return ['exito' => false, 'mensaje' => 'Usuario no encontrado', 'codigo' => 404];
        }

        $this->modelo->eliminar($id);
        return ['exito' => true, 'mensaje' => 'Usuario eliminado'];
    }
}
