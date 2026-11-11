<?php

use PHPUnit\Framework\TestCase;

/**
 * CRUD de usuarios (ABM del administrador).
 */
class UsuarioControllerTest extends TestCase
{
    private $pdo;
    private $modelo;
    private $centros;
    private UsuarioController $controlador;

    protected function setUp(): void
    {
        $this->pdo = $this->createMock(PDO::class);
        $this->modelo = $this->createMock(Usuario::class);
        $this->centros = $this->createMock(CentroAcopio::class);
        $this->controlador = new UsuarioController($this->pdo, $this->modelo, $this->centros);
    }

    private function datos(array $cambios = []): array
    {
        return array_merge([
            'nombre' => 'Ana', 'apellido' => 'García', 'ci' => '1.234.567-2',
            'email' => 'Ana@Sigeru.uy', 'password' => 'clave1234', 'tipo_usuario' => 'vecino',
        ], $cambios);
    }

    public function testCrearFallaSinNombre(): void
    {
        $resultado = $this->controlador->crear($this->datos(['nombre' => '']));
        $this->assertStringContainsString('nombre', $resultado['mensaje']);
    }

    public function testCrearFallaConRolInvalido(): void
    {
        $this->assertStringContainsString('inválido', $this->controlador->crear($this->datos(['tipo_usuario' => 'superadmin']))['mensaje']);
    }

    public function testCrearFallaConCedulaInvalida(): void
    {
        $this->assertStringContainsString('cédula', $this->controlador->crear($this->datos(['ci' => '12345678']))['mensaje']);
    }

    public function testCrearFallaConContrasenaCorta(): void
    {
        $this->assertStringContainsString('8 caracteres', $this->controlador->crear($this->datos(['password' => '123']))['mensaje']);
    }

    public function testOperarioRequiereCentroValido(): void
    {
        $this->centros->method('buscarPorId')->willReturn(null);
        $resultado = $this->controlador->crear($this->datos(['tipo_usuario' => 'operario', 'id_centro' => 99]));
        $this->assertStringContainsString('centro de acopio', $resultado['mensaje']);
    }

    public function testEmailRepetido(): void
    {
        $this->modelo->method('buscarPorEmail')->willReturn(['id_usuario' => 1]);
        $this->assertSame(409, $this->controlador->crear($this->datos())['codigo']);
    }

    public function testCrearNormalizaYHasheaLaContrasena(): void
    {
        $this->modelo->method('buscarPorEmail')->willReturn(null);
        $this->modelo->method('buscarPorCI')->willReturn(null);
        $this->pdo->expects($this->once())->method('commit');
        $this->modelo->expects($this->once())
            ->method('crear')
            ->with($this->callback(function ($d) {
                return $d['email'] === 'ana@sigeru.uy'
                    && $d['ci'] === '12345672'
                    && password_verify('clave1234', $d['password_hash'])
                    && $d['estado'] === 'activo';
            }))
            ->willReturn(20);

        $resultado = $this->controlador->crear($this->datos());
        $this->assertTrue($resultado['exito']);
        $this->assertSame(20, $resultado['id']);
    }

    public function testActualizarConEstadoInvalido(): void
    {
        $this->modelo->method('buscarPorId')->willReturn(['id_usuario' => 1, 'tipo_usuario' => 'vecino']);
        $this->assertStringContainsString('inválido', $this->controlador->actualizar(1, ['estado' => 'suspendido'])['mensaje']);
    }

    public function testAdministradorNoPuedeDesactivarseASiMismo(): void
    {
        $this->modelo->method('buscarPorId')->willReturn(['id_usuario' => 1, 'tipo_usuario' => 'administrador']);
        $this->modelo->expects($this->never())->method('actualizarCompleto');
        $this->assertFalse($this->controlador->actualizar(1, ['estado' => 'inactivo'], 1)['exito']);
    }

    public function testCambiarDeRolMueveAlUsuarioDeSubtabla(): void
    {
        $this->modelo->method('buscarPorId')->willReturn(['id_usuario' => 3, 'tipo_usuario' => 'vecino']);
        $this->modelo->expects($this->once())->method('cambiarSubtipo')->with(3, 'vecino', 'cuadrilla', null);
        $this->assertTrue($this->controlador->actualizar(3, ['tipo_usuario' => 'cuadrilla'], 1)['exito']);
    }

    public function testDesactivarCierraSusSesiones(): void
    {
        $this->modelo->method('buscarPorId')->willReturn(['id_usuario' => 3, 'tipo_usuario' => 'vecino']);
        $this->modelo->expects($this->once())->method('cerrarSesiones')->with(3);
        $this->controlador->actualizar(3, ['estado' => 'inactivo'], 1);
    }

    public function testEliminarInexistente(): void
    {
        $this->modelo->method('buscarPorId')->willReturn(null);
        $this->assertSame(404, $this->controlador->eliminar(9999)['codigo']);
    }

    public function testObtenerNoDevuelveElHashDeLaContrasena(): void
    {
        $this->modelo->method('buscarPorId')->willReturn(['id_usuario' => 1, 'password_hash' => 'secreto']);
        $this->assertArrayNotHasKey('password_hash', $this->controlador->obtener(1));
    }
}
