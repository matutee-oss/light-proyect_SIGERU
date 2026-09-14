<?php
/**
 * Test unitario para UsuarioController
 * Ejecutar con PHPUnit: vendor/bin/phpunit tests/
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../controllers/UsuarioController.php';

class UsuarioControllerTest extends TestCase
{
    private PDO $pdoMock;
    private UsuarioController $controlador;

    protected function setUp(): void
    {
        $this->pdoMock = $this->createMock(PDO::class);
        $this->controlador = new UsuarioController($this->pdoMock);
    }

    /** @test */
    public function crearFallaSinNombre(): void
    {
        $resultado = $this->controlador->crear([
            'apellido' => 'Test', 'ci' => '12345678',
            'email' => 'test@test.com', 'password' => 'pass123', 'tipo_usuario' => 'vecino'
        ]);
        $this->assertFalse($resultado['exito']);
    }

    /** @test */
    public function crearFallaConRolInvalido(): void
    {
        $resultado = $this->controlador->crear([
            'nombre' => 'Test', 'apellido' => 'User', 'ci' => '12345678',
            'email' => 'test@test.com', 'password' => 'pass123', 'tipo_usuario' => 'superadmin'
        ]);
        $this->assertFalse($resultado['exito']);
        $this->assertStringContainsString('inválido', $resultado['mensaje']);
    }

    /** @test */
    public function actualizarFallaConEstadoInvalido(): void
    {
        $stmtMock = $this->createMock(PDOStatement::class);
        $stmtMock->method('execute')->willReturn(true);
        $stmtMock->method('fetch')->willReturn(['id_usuario' => 1, 'nombre' => 'Test']);
        $this->pdoMock->method('prepare')->willReturn($stmtMock);

        $resultado = $this->controlador->actualizar(1, ['estado' => 'suspendido']);
        $this->assertFalse($resultado['exito']);
        $this->assertStringContainsString('inválido', $resultado['mensaje']);
    }

    /** @test */
    public function eliminarFallaSiUsuarioNoExiste(): void
    {
        $stmtMock = $this->createMock(PDOStatement::class);
        $stmtMock->method('execute')->willReturn(true);
        $stmtMock->method('fetch')->willReturn(false);
        $this->pdoMock->method('prepare')->willReturn($stmtMock);

        $resultado = $this->controlador->eliminar(9999);
        $this->assertFalse($resultado['exito']);
    }
}
