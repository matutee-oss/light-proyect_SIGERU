<?php

use PHPUnit\Framework\TestCase;

/**
 * CRUD de centros de acopio y sus recursos.
 */
class CentroAcopioControllerTest extends TestCase
{
    private $modelo;
    private CentroAcopioController $controlador;

    protected function setUp(): void
    {
        $this->modelo = $this->createMock(CentroAcopio::class);
        $this->controlador = new CentroAcopioController($this->createMock(PDO::class), $this->modelo);
    }

    public function testCrearFallaSinCapacidad(): void
    {
        $this->assertStringContainsString('capacidad', $this->controlador->crear(['nombre' => 'X', 'ubicacion' => 'Y'])['mensaje']);
    }

    public function testCrearFallaConCapacidadNegativa(): void
    {
        $this->assertFalse($this->controlador->crear(['nombre' => 'X', 'ubicacion' => 'Y', 'capacidad' => -5])['exito']);
    }

    public function testCrearCorrectamente(): void
    {
        $this->modelo->expects($this->once())->method('crear')->willReturn(3);
        $this->assertSame(3, $this->controlador->crear(['nombre' => 'Centro Cerro', 'ubicacion' => 'Cerro', 'capacidad' => 300])['id']);
    }

    public function testActualizarParcialConservaLosDemasCampos(): void
    {
        $this->modelo->method('buscarPorId')->willReturn(['id_centro' => 1, 'nombre' => 'Buceo', 'ubicacion' => 'Rambla', 'capacidad' => 500, 'latitud' => null, 'longitud' => null]);
        $this->modelo->expects($this->once())->method('actualizar')
            ->with(1, $this->callback(fn($d) => $d['nombre'] === 'Buceo' && (float) $d['capacidad'] === 650.0));
        $this->assertTrue($this->controlador->actualizar(1, ['capacidad' => 650])['exito']);
    }

    public function testNoSeEliminaConOperariosAsignados(): void
    {
        $this->modelo->method('buscarPorId')->willReturn(['id_centro' => 1]);
        $this->modelo->method('contarOperarios')->willReturn(2);
        $this->modelo->expects($this->never())->method('eliminar');
        $this->assertSame(409, $this->controlador->eliminar(1)['codigo']);
    }

    public function testAgregarRecursoValidaLaCantidad(): void
    {
        $this->modelo->method('buscarPorId')->willReturn(['id_centro' => 1]);
        $this->assertFalse($this->controlador->agregarRecurso(1, ['tipo' => 'Palas', 'cantidad' => '-2'])['exito']);
    }

    public function testAgregarRecursoCorrectamente(): void
    {
        $this->modelo->method('buscarPorId')->willReturn(['id_centro' => 1]);
        $this->modelo->expects($this->once())->method('agregarRecurso')->with(1, 'Palas', 3)->willReturn(8);
        $this->assertTrue($this->controlador->agregarRecurso(1, ['tipo' => 'Palas', 'cantidad' => 3])['exito']);
    }

    public function testEliminarRecursoInexistente(): void
    {
        $this->modelo->method('eliminarRecurso')->willReturn(false);
        $this->assertSame(404, $this->controlador->eliminarRecurso(1, 99)['codigo']);
    }
}
