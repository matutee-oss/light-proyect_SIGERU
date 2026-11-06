<?php

use PHPUnit\Framework\TestCase;

/**
 * CRUD de contenedores.
 */
class ContenedorControllerTest extends TestCase
{
    private $modelo;
    private $alertas;
    private ContenedorController $controlador;

    protected function setUp(): void
    {
        $this->modelo = $this->createMock(Contenedor::class);
        $this->alertas = $this->createMock(ServicioAlertas::class);
        $this->controlador = new ContenedorController($this->createMock(PDO::class), $this->modelo, $this->alertas);
    }

    private function existente(array $cambios = []): array
    {
        return array_merge([
            'id_contenedor' => 1, 'ubicacion' => 'Av. Italia', 'tipo_residuo' => 'Comunes', 'estado' => 'funcional',
            'id_zona' => 4, 'latitud' => null, 'longitud' => null, 'nivel_llenado' => 40,
            'capacidad_litros' => 2400, 'incidencias_pendientes' => 0,
        ], $cambios);
    }

    public function testCrearFallaSinDatosObligatorios(): void
    {
        $resultado = $this->controlador->crear(['ubicacion' => 'Av. Italia']);
        $this->assertFalse($resultado['exito']);
        $this->assertStringContainsString('obligatorios', $resultado['mensaje']);
    }

    public function testCrearFallaConEstadoInvalido(): void
    {
        $this->assertFalse($this->controlador->crear(['ubicacion' => 'X', 'tipo_residuo' => 'Y', 'estado' => 'perdido'])['exito']);
    }

    public function testCrearFallaConUnaSolaCoordenada(): void
    {
        $resultado = $this->controlador->crear(['ubicacion' => 'X', 'tipo_residuo' => 'Y', 'latitud' => '-34.9']);
        $this->assertStringContainsString('Coordenadas', $resultado['mensaje']);
    }

    public function testCrearFallaConNivelFueraDeRango(): void
    {
        $this->assertFalse($this->controlador->crear(['ubicacion' => 'X', 'tipo_residuo' => 'Y', 'nivel_llenado' => 120])['exito']);
    }

    public function testCrearCorrectamente(): void
    {
        $this->modelo->expects($this->once())->method('crear')->willReturn(15);
        $resultado = $this->controlador->crear(['ubicacion' => 'Rincón y Misiones', 'tipo_residuo' => 'Reciclables', 'id_zona' => 2]);
        $this->assertTrue($resultado['exito']);
        $this->assertSame(15, $resultado['id']);
    }

    public function testActualizarInexistenteDevuelve404(): void
    {
        $this->modelo->method('buscarPorId')->willReturn(null);
        $this->assertSame(404, $this->controlador->actualizar(99, ['ubicacion' => 'X'])['codigo']);
    }

    public function testActualizarPermiteCambiosParcialesYGuardaLaZona(): void
    {
        $actual = $this->existente();
        $this->modelo->method('buscarPorId')->willReturn($actual);
        $this->modelo->expects($this->once())->method('actualizar')->with(1, ['id_zona' => 6], $actual);
        $this->assertTrue($this->controlador->actualizar(1, ['id_zona' => 6])['exito']);
    }

    public function testCambiarNivelDesdeElAbmAvisaSiCruzaElUmbral(): void
    {
        $this->modelo->method('buscarPorId')->willReturn($this->existente());
        $this->alertas->expects($this->once())->method('nivelContenedor')->with($this->anything(), 40.0, 90.0);
        $this->controlador->actualizar(1, ['nivel_llenado' => 90]);
    }

    public function testNoSeEliminaConIncidenciasPendientes(): void
    {
        $this->modelo->method('buscarPorId')->willReturn($this->existente(['incidencias_pendientes' => 2]));
        $this->modelo->expects($this->never())->method('eliminar');
        $this->assertSame(409, $this->controlador->eliminar(1)['codigo']);
    }

    public function testEliminarCorrectamente(): void
    {
        $this->modelo->method('buscarPorId')->willReturn($this->existente());
        $this->modelo->expects($this->once())->method('eliminar')->with(1);
        $this->assertTrue($this->controlador->eliminar(1)['exito']);
    }
}
