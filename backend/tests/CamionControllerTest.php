<?php
/**
 * Test unitario para CamionController
 * Ejecutar con PHPUnit: vendor/bin/phpunit tests/
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../controllers/CamionController.php';

class CamionControllerTest extends TestCase
{
    private PDO $pdoMock;
    private CamionController $controlador;

    protected function setUp(): void
    {
        $this->pdoMock = $this->createMock(PDO::class);
        $this->controlador = new CamionController($this->pdoMock);
    }

    /** @test */
    public function crearFallaSinMatricula(): void
    {
        $resultado = $this->controlador->crear([]);
        $this->assertFalse($resultado['exito']);
        $this->assertStringContainsString('matricula', $resultado['mensaje']);
    }

    /** @test */
    public function crearFallaSiMatriculaEstaVacia(): void
    {
        $resultado = $this->controlador->crear(['matricula' => '']);
        $this->assertFalse($resultado['exito']);
    }

    /** @test */
    public function eliminarFallaSiCamionNoExiste(): void
    {
        $stmtMock = $this->createMock(PDOStatement::class);
        $stmtMock->method('execute')->willReturn(true);
        $stmtMock->method('fetch')->willReturn(false);

        $this->pdoMock->method('prepare')->willReturn($stmtMock);

        $resultado = $this->controlador->eliminar(9999);
        $this->assertFalse($resultado['exito']);
        $this->assertStringContainsString('no encontrado', $resultado['mensaje']);
    }
}
