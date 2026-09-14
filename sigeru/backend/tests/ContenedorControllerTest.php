<?php
/**
 * Test unitario para ContenedorController
 * Ejecutar con PHPUnit: vendor/bin/phpunit tests/
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../controllers/ContenedorController.php';

class ContenedorControllerTest extends TestCase
{
    private PDO $pdoMock;
    private ContenedorController $controlador;

    protected function setUp(): void
    {
        $this->pdoMock = $this->createMock(PDO::class);
        $this->controlador = new ContenedorController($this->pdoMock);
    }

    /** @test */
    public function crearFallaSinUbicacion(): void
    {
        $resultado = $this->controlador->crear(['tipo_residuo' => 'comunes']);
        $this->assertFalse($resultado['exito']);
    }

    /** @test */
    public function crearFallaSinTipoResiduo(): void
    {
        $resultado = $this->controlador->crear(['ubicacion' => 'Av. Italia']);
        $this->assertFalse($resultado['exito']);
    }

    /** @test */
    public function crearFallaSinDatosObligatorios(): void
    {
        $resultado = $this->controlador->crear([]);
        $this->assertFalse($resultado['exito']);
        $this->assertStringContainsString('obligatorios', $resultado['mensaje']);
    }

    /** @test */
    public function actualizarFallaSiContenedorNoExiste(): void
    {
        $stmtMock = $this->createMock(PDOStatement::class);
        $stmtMock->method('execute')->willReturn(true);
        $stmtMock->method('fetch')->willReturn(false);

        $this->pdoMock->method('prepare')->willReturn($stmtMock);

        $resultado = $this->controlador->actualizar(9999, ['ubicacion' => 'X', 'tipo_residuo' => 'Y', 'estado' => 'funcional']);
        $this->assertFalse($resultado['exito']);
    }
}
