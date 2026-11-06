<?php

require_once __DIR__ . '/../helpers/validacion.php';
require_once __DIR__ . '/../models/Usuario.php';
require_once __DIR__ . '/../models/CodigoFuncionario.php';
require_once __DIR__ . '/../models/Notificacion.php';
require_once __DIR__ . '/../services/ServicioAlertas.php';

class AuthController
{
    private PDO $conexion;
    private Usuario $modeloUsuario;
    private CodigoFuncionario $modeloCodigo;
    private ServicioAlertas $alertas;

    private const ROLES_QUE_REQUIEREN_CODIGO = ['administrador', 'cuadrilla', 'operario'];
    public const HORAS_SESION = 12;

    public function __construct(
        PDO $conexion,
        ?Usuario $modeloUsuario = null,
        ?CodigoFuncionario $modeloCodigo = null,
        ?ServicioAlertas $alertas = null
    ) {
        $this->conexion = $conexion;
        $this->modeloUsuario = $modeloUsuario ?? new Usuario($conexion);
        $this->modeloCodigo = $modeloCodigo ?? new CodigoFuncionario($conexion);
        $this->alertas = $alertas ?? new ServicioAlertas(new Notificacion($conexion));
    }

    public function login(string $email, string $password): array
    {
        $email = mb_strtolower(trim($email));
        if ($email === '' || $password === '') {
            return ['exito' => false, 'mensaje' => 'Debe enviar email y contraseña'];
        }

        $usuario = $this->modeloUsuario->buscarPorEmail($email);

        // Corrección: primero se verifica la contraseña y después el estado,
        // así no se revela si un email está registrado sin conocer la clave.
        if (!$usuario || !password_verify($password, $usuario['password_hash'])) {
            return ['exito' => false, 'mensaje' => 'Usuario o contraseña incorrectos'];
        }

        if ($usuario['estado'] === 'pendiente') {
            return ['exito' => false, 'mensaje' => 'El usuario aún no fue aprobado por un administrador'];
        }
        if ($usuario['estado'] !== 'activo') {
            return ['exito' => false, 'mensaje' => 'El usuario está inactivo. Contactá a un administrador.'];
        }

        $token = bin2hex(random_bytes(32));

        // Limpia sesiones vencidas para que la tabla no crezca sin fin
        $this->conexion->exec('DELETE FROM sesiones WHERE expira_en < NOW()');

        $consulta = $this->conexion->prepare(
            'INSERT INTO sesiones (token, id_usuario, expira_en)
             VALUES (:token, :id_usuario, DATE_ADD(NOW(), INTERVAL ' . self::HORAS_SESION . ' HOUR))'
        );
        $consulta->execute([
            'token'      => $token,
            'id_usuario' => $usuario['id_usuario'],
        ]);

        return [
            'exito' => true,
            'token' => $token,
            'usuario' => [
                'id'     => (int) $usuario['id_usuario'],
                'nombre' => $usuario['nombre'] . ' ' . $usuario['apellido'],
                'email'  => $usuario['email'],
                'tipo'   => $usuario['tipo_usuario'],
                'foto'   => $usuario['foto'],
            ],
        ];
    }

    /** Valida los datos del formulario de registro. Devuelve el error o null. */
    public static function validarRegistro(array $datos): ?string
    {
        $faltante = Validacion::faltante($datos, ['nombre', 'apellido', 'ci', 'email', 'password', 'tipo_usuario']);
        if ($faltante !== null) {
            return "Falta el campo obligatorio: {$faltante}";
        }
        if (!Validacion::enLista($datos['tipo_usuario'], Validacion::ROLES)) {
            return 'tipo_usuario inválido';
        }
        if (!Validacion::email($datos['email'])) {
            return 'El email no tiene un formato válido';
        }
        if (!Validacion::cedulaUruguaya($datos['ci'])) {
            return 'La cédula no es válida (revisá el dígito verificador)';
        }
        if (!Validacion::contrasena($datos['password'])) {
            return 'La contraseña debe tener al menos 8 caracteres';
        }
        if (in_array($datos['tipo_usuario'], self::ROLES_QUE_REQUIEREN_CODIGO, true)
            && trim((string) ($datos['codigo_funcionario'] ?? '')) === '') {
            return 'Este rol requiere un código de funcionario';
        }
        if ($datos['tipo_usuario'] === 'operario' && empty($datos['id_centro'])) {
            return 'Los operarios deben indicar el centro de acopio donde trabajan';
        }
        return null;
    }

    public function registrar(array $datos): array
    {
        if ($error = self::validarRegistro($datos)) {
            return ['exito' => false, 'mensaje' => $error, 'codigo' => 400];
        }

        $datos['email'] = mb_strtolower(trim($datos['email']));
        $datos['ci'] = Validacion::normalizarCedula($datos['ci']);

        if ($this->modeloUsuario->buscarPorEmail($datos['email'])) {
            return ['exito' => false, 'mensaje' => 'Ya existe un usuario registrado con ese email', 'codigo' => 409];
        }
        if ($this->modeloUsuario->buscarPorCI($datos['ci'])) {
            return ['exito' => false, 'mensaje' => 'Ya existe un usuario registrado con esa cédula', 'codigo' => 409];
        }

        $requiereCodigo = in_array($datos['tipo_usuario'], self::ROLES_QUE_REQUIEREN_CODIGO, true);
        // Nadie puede registrarse ya activo: siempre lo aprueba un administrador
        $datos['estado'] = 'pendiente';

        $this->conexion->beginTransaction();

        try {
            $codigoFila = null;

            if ($requiereCodigo) {
                $codigoFila = $this->modeloCodigo->buscarParaConsumir(trim($datos['codigo_funcionario']), $datos['tipo_usuario']);

                if (!$codigoFila || (bool) $codigoFila['usado']) {
                    $this->conexion->rollBack();
                    return ['exito' => false, 'mensaje' => 'Código de funcionario inválido o ya utilizado', 'codigo' => 400];
                }
            }

            $datos['password_hash'] = password_hash($datos['password'], PASSWORD_DEFAULT);
            $idUsuario = $this->modeloUsuario->crear($datos);

            if ($requiereCodigo) {
                $this->modeloCodigo->marcarUsado((int) $codigoFila['id_codigo'], $idUsuario);
            }

            $this->conexion->commit();
        } catch (Throwable $e) {
            $this->conexion->rollBack();
            return ['exito' => false, 'mensaje' => 'No se pudo completar el registro', 'codigo' => 500];
        }

        $this->alertas->usuarioPendiente($datos);

        return [
            'exito'   => true,
            'mensaje' => 'Registro recibido. Un administrador debe aprobar la cuenta.',
            'id'      => $idUsuario,
        ];
    }
}
