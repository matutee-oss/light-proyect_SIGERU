<?php
/**
 * Test unitario para AuthController
 * Ejecutar con PHPUnit: vendor/bin/phpunit tests/
 */

use PHPUnit\Framework\TestCase;

// Mocks de dependencias
require_once __DIR__ . '/../controllers/AuthController.php';

class AuthTest extends TestCase
{
    private PDO $pdoMock;
    private AuthController $auth;

    protected function setUp(): void
    {
        // Crear mock de PDO
        $this->pdoMock = $this->createMock(PDO::class);
        $this->auth = new AuthController($this->pdoMock);
    }

    /** @test */
    public function loginFallaConEmailVacio(): void
    {
        $resultado = $this->auth->login('', 'password123');
        $this->assertFalse($resultado['exito']);
    }

    /** @test */
    public function loginFallaConPasswordVacio(): void
    {
        $resultado = $this->auth->login('test@test.com', '');
        $this->assertFalse($resultado['exito']);
    }

    /** @test */
    public function loginRetornaExitoFalsoParaUsuarioInexistente(): void
    {
        // Simular que buscarPorEmail retorna null
        $stmtMock = $this->createMock(PDOStatement::class);
        $stmtMock->method('execute')->willReturn(true);
        $stmtMock->method('fetch')->willReturn(false);

        $this->pdoMock->method('prepare')->willReturn($stmtMock);

        $resultado = $this->auth->login('noexiste@test.com', 'password123');
        $this->assertFalse($resultado['exito']);
        $this->assertStringContainsString('incorrectos', $resultado['mensaje']);
    }
}
