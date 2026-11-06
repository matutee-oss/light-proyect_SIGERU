<?php

use PHPUnit\Framework\TestCase;

/**
 * CRUD de camiones (flota).
 */
class CamionControllerTest extends TestCase
{
    private $modelo;
    private CamionController $controlador;

    protected function setUp(): void
    {
        $this->modelo = $this->createMock(Camion::class);
        $this->controlador = new CamionController($this->createMock(PDO::class), $this->modelo);
    }

    public function testCrearFallaSinMatricula(): void
    {
        $resultado = $this->controlador->crear([]);
        $this->assertFalse($resultado['exito']);
        $this->assertStringContainsString('matricula', $resultado['mensaje']);
    }

    public function testCrearFallaConMatriculaInvalida(): void
    {
        $this->assertStringContainsString('inválida', $this->controlador->crear(['matricula' => '12-ABC'])['mensaje']);
    }

    public function testCrearFallaConMatriculaRepetida(): void
    {
        $this->modelo->method('buscarPorMatricula')->willReturn(['id_camion' => 1]);
        $this->assertStringContainsString('Ya existe', $this->controlador->crear(['matricula' => 'SBC 1234'])['mensaje']);
    }

    public function testCrearFallaConEstadoInvalido(): void
    {
        $this->modelo->method('buscarPorMatricula')->willReturn(null);
        $this->assertFalse($this->controlador->crear(['matricula' => 'SBZ 1111', 'estado' => 'volando'])['exito']);
    }

    public function testCrearCorrectamente(): void
    {
        $this->modelo->method('buscarPorMatricula')->willReturn(null);
        $this->modelo->expects($this->once())->method('crear')->willReturn(9);
        $resultado = $this->controlador->crear(['matricula' => 'SBZ 1111', 'capacidad_kg' => 8000]);
        $this->assertTrue($resultado['exito']);
        $this->assertSame(9, $resultado['id']);
    }

    public function testEditarConservandoSuPropiaMatricula(): void
    {
        $actual = ['id_camion' => 1, 'matricula' => 'SBC 1234', 'estado' => 'disponible', 'capacidad_kg' => 6000, 'latitud' => null, 'longitud' => null];
        $this->modelo->method('buscarPorId')->willReturn($actual);
        $this->modelo->method('buscarPorMatricula')->willReturn($actual);
        $this->modelo->expects($this->once())->method('actualizar');

        $this->assertTrue($this->controlador->actualizar(1, ['estado' => 'mantenimiento'])['exito']);
    }

    public function testEliminarInexistente(): void
    {
        $this->modelo->method('buscarPorId')->willReturn(null);
        $this->assertSame(404, $this->controlador->eliminar(9999)['codigo']);
    }

    public function testEliminarCorrectamente(): void
    {
        $this->modelo->method('buscarPorId')->willReturn(['id_camion' => 1]);
        $this->modelo->expects($this->once())->method('eliminar')->with(1);
        $this->assertTrue($this->controlador->eliminar(1)['exito']);
    }
}
