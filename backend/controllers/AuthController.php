<?php

require_once __DIR__ . '/../models/Usuario.php';
require_once __DIR__ . '/../models/CodigoFuncionario.php';

class AuthController
{
    private PDO $conexion;
    private Usuario $modeloUsuario;
    private CodigoFuncionario $modeloCodigo;

    private const ROLES_QUE_REQUIEREN_CODIGO = ['administrador', 'cuadrilla', 'operario'];

    public function __construct(PDO $conexion)
    {
        $this->conexion = $conexion;
        $this->modeloUsuario = new Usuario($conexion);
        $this->modeloCodigo = new CodigoFuncionario($conexion);
    }

    public function login(string $email, string $password): array
    {
        $usuario = $this->modeloUsuario->buscarPorEmail($email);

        if (!$usuario) {
            return ['exito' => false, 'mensaje' => 'Usuario o contraseña incorrectos'];
        }

        if ($usuario['estado'] !== 'activo') {
            return ['exito' => false, 'mensaje' => 'El usuario aún no fue aprobado por un administrador'];
        }

        if (!password_verify($password, $usuario['password_hash'])) {
            return ['exito' => false, 'mensaje' => 'Usuario o contraseña incorrectos'];
        }

        $token = bin2hex(random_bytes(32));

        $consulta = $this->conexion->prepare(
            'INSERT INTO sesiones (token, id_usuario, expira_en)
             VALUES (:token, :id_usuario, DATE_ADD(NOW(), INTERVAL 12 HOUR))'
        );
        $consulta->execute([
            'token'      => $token,
            'id_usuario' => $usuario['id_usuario'],
        ]);

        return [
            'exito' => true,
            'token' => $token,
            'usuario' => [
                'id'     => $usuario['id_usuario'],
                'nombre' => $usuario['nombre'] . ' ' . $usuario['apellido'],
                'email'  => $usuario['email'],
                'tipo'   => $usuario['tipo_usuario'],
                'foto'   => $usuario['foto'],
            ],
        ];
    }

    public function registrar(array $datos): array
    {
        if ($this->modeloUsuario->buscarPorEmail($datos['email'])) {
            return ['exito' => false, 'mensaje' => 'Ya existe un usuario registrado con ese email'];
        }

        $requiereCodigo = in_array($datos['tipo_usuario'], self::ROLES_QUE_REQUIEREN_CODIGO, true);

        $this->conexion->beginTransaction();

        try {
            $codigoFila = null;

            if ($requiereCodigo) {
                $codigoIngresado = trim((string) ($datos['codigo_funcionario'] ?? ''));

                if ($codigoIngresado === '') {
                    $this->conexion->rollBack();
                    return ['exito' => false, 'mensaje' => 'Este rol requiere un código de funcionario'];
                }

                $codigoFila = $this->modeloCodigo->buscarParaConsumir($codigoIngresado, $datos['tipo_usuario']);

                if (!$codigoFila || (bool) $codigoFila['usado']) {
                    $this->conexion->rollBack();
                    return ['exito' => false, 'mensaje' => 'Código de funcionario inválido o ya utilizado'];
                }
            }

            $datos['password_hash'] = password_hash($datos['password'], PASSWORD_DEFAULT);
            $idUsuario = $this->modeloUsuario->crear($datos);

            if ($requiereCodigo) {
                $this->modeloCodigo->marcarUsado((int) $codigoFila['id_codigo'], $idUsuario);
            }

            $this->conexion->commit();

            return [
                'exito'   => true,
                'mensaje' => 'Registro recibido. Un administrador debe aprobar la cuenta.',
                'id'      => $idUsuario,
            ];

        } catch (Exception $e) {
            $this->conexion->rollBack();
            error_log('SIGERU REGISTRO ERROR: ' . $e->getMessage());
            return ['exito' => false, 'mensaje' => 'No se pudo completar el registro'];
        }
    }
}
