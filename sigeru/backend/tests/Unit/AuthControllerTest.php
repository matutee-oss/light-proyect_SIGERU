<?php

use PHPUnit\Framework\TestCase;

/**
 * Inicio de sesión y registro.
 */
class AuthControllerTest extends TestCase
{
    private $pdo;
    private $usuarios;
    private $codigos;
    private $alertas;
    private AuthController $auth;

    protected function setUp(): void
    {
        $this->pdo = $this->createMock(PDO::class);
        $this->usuarios = $this->createMock(Usuario::class);
        $this->codigos = $this->createMock(CodigoFuncionario::class);
        $this->alertas = $this->createMock(ServicioAlertas::class);
        $this->auth = new AuthController($this->pdo, $this->usuarios, $this->codigos, $this->alertas);
    }

    private function usuario(string $estado = 'activo'): array
    {
        return [
            'id_usuario' => 3, 'nombre' => 'Ana', 'apellido' => 'García', 'email' => 'ana@sigeru.uy',
            'tipo_usuario' => 'vecino', 'foto' => null, 'estado' => $estado,
            'password_hash' => password_hash('test1234', PASSWORD_DEFAULT),
        ];
    }

    public function testLoginFallaConDatosVacios(): void
    {
        $this->assertFalse($this->auth->login('', 'x')['exito']);
        $this->assertFalse($this->auth->login('a@b.uy', '')['exito']);
    }

    public function testLoginUsuarioInexistente(): void
    {
        $this->usuarios->method('buscarPorEmail')->willReturn(null);
        $this->assertStringContainsString('incorrectos', $this->auth->login('no@existe.uy', 'test1234')['mensaje']);
    }

    public function testLoginContrasenaIncorrectaNoRevelaElEstado(): void
    {
        $this->usuarios->method('buscarPorEmail')->willReturn($this->usuario('pendiente'));
        $resultado = $this->auth->login('ana@sigeru.uy', 'otra-clave');
        $this->assertStringContainsString('incorrectos', $resultado['mensaje']);
    }

    public function testLoginUsuarioPendiente(): void
    {
        $this->usuarios->method('buscarPorEmail')->willReturn($this->usuario('pendiente'));
        $this->assertStringContainsString('aprobado', $this->auth->login('ana@sigeru.uy', 'test1234')['mensaje']);
    }

    public function testLoginUsuarioInactivo(): void
    {
        $this->usuarios->method('buscarPorEmail')->willReturn($this->usuario('inactivo'));
        $this->assertStringContainsString('inactivo', $this->auth->login('ana@sigeru.uy', 'test1234')['mensaje']);
    }

    public function testLoginCorrectoDevuelveToken(): void
    {
        $this->usuarios->method('buscarPorEmail')->with('ana@sigeru.uy')->willReturn($this->usuario());
        $consulta = $this->createMock(PDOStatement::class);
        $consulta->expects($this->once())->method('execute')->willReturn(true);
        $this->pdo->method('prepare')->willReturn($consulta);

        $resultado = $this->auth->login('  ANA@sigeru.uy ', 'test1234');

        $this->assertTrue($resultado['exito']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $resultado['token']);
        $this->assertSame('vecino', $resultado['usuario']['tipo']);
        $this->assertArrayNotHasKey('password_hash', $resultado['usuario']);
    }

    public function testValidarRegistro(): void
    {
        $base = ['nombre' => 'A', 'apellido' => 'B', 'ci' => '11111111', 'email' => 'a@b.uy', 'password' => 'clave1234', 'tipo_usuario' => 'vecino'];
        $this->assertNull(AuthController::validarRegistro($base));
        $this->assertStringContainsString('código', AuthController::validarRegistro(array_merge($base, ['tipo_usuario' => 'cuadrilla'])));
        $this->assertStringContainsString('centro', AuthController::validarRegistro(array_merge($base, ['tipo_usuario' => 'operario', 'codigo_funcionario' => 'X'])));
        $this->assertStringContainsString('cédula', AuthController::validarRegistro(array_merge($base, ['ci' => '11111112'])));
    }

    public function testRegistroConCodigoYaUsado(): void
    {
        $this->usuarios->method('buscarPorEmail')->willReturn(null);
        $this->usuarios->method('buscarPorCI')->willReturn(null);
        $this->codigos->method('buscarParaConsumir')->willReturn(['id_codigo' => 1, 'usado' => 1]);
        $this->pdo->expects($this->once())->method('rollBack');
        $this->usuarios->expects($this->never())->method('crear');

        $resultado = $this->auth->registrar([
            'nombre' => 'C', 'apellido' => 'D', 'ci' => '33333333', 'email' => 'c@d.uy',
            'password' => 'clave1234', 'tipo_usuario' => 'cuadrilla', 'codigo_funcionario' => 'SIGERU-ADM-7F2K',
        ]);
        $this->assertStringContainsString('ya utilizado', $resultado['mensaje']);
    }

    public function testRegistroDeVecinoQuedaPendienteYAvisaAlAdmin(): void
    {
        $this->usuarios->method('buscarPorEmail')->willReturn(null);
        $this->usuarios->method('buscarPorCI')->willReturn(null);
        $this->usuarios->expects($this->once())->method('crear')
            ->with($this->callback(fn($d) => $d['estado'] === 'pendiente'))
            ->willReturn(30);
        $this->alertas->expects($this->once())->method('usuarioPendiente');

        $resultado = $this->auth->registrar([
            'nombre' => 'E', 'apellido' => 'F', 'ci' => '22222222', 'email' => 'e@f.uy',
            'password' => 'clave1234', 'tipo_usuario' => 'vecino', 'estado' => 'activo', // intento de saltear la aprobación
        ]);
        $this->assertTrue($resultado['exito']);
    }
}
