<?php

use PHPUnit\Framework\TestCase;

/**
 * API de recolección: validaciones y efectos de registrar un vaciado.
 */
class RecoleccionControllerTest extends TestCase
{
    private $pdo;
    private $modelo;
    private $contenedores;
    private $incidencias;
    private $camiones;
    private $rutas;
    private $alertas;
    private RecoleccionController $controlador;

    protected function setUp(): void
    {
        $this->pdo = $this->createMock(PDO::class);
        $this->modelo = $this->createMock(Recoleccion::class);
        $this->contenedores = $this->createMock(Contenedor::class);
        $this->incidencias = $this->createMock(Incidencia::class);
        $this->camiones = $this->createMock(Camion::class);
        $this->rutas = $this->createMock(Ruta::class);
        $this->alertas = $this->createMock(ServicioAlertas::class);
        $this->controlador = new RecoleccionController(
            $this->pdo, $this->modelo, $this->contenedores, $this->incidencias,
            $this->camiones, $this->rutas, $this->alertas
        );
    }

    private function cuadrilla(int $id = 4): array
    {
        return ['id_usuario' => $id, 'tipo_usuario' => 'cuadrilla', 'nombre' => 'Carlos', 'apellido' => 'López'];
    }

    private function contenedor(): array
    {
        return ['id_contenedor' => 2, 'ubicacion' => 'Bvar. Artigas', 'estado' => 'desbordado', 'nivel_llenado' => 100];
    }

    public function testVecinoNoPuedeRegistrarRecolecciones(): void
    {
        $resultado = $this->controlador->registrar(['id_usuario' => 3, 'tipo_usuario' => 'vecino'], ['id_contenedor' => 2]);
        $this->assertSame(403, $resultado['codigo']);
    }

    public function testFaltaContenedor(): void
    {
        $this->assertFalse($this->controlador->registrar($this->cuadrilla(), [])['exito']);
    }

    public function testKilosInvalidos(): void
    {
        foreach (['-5', 'mucho', 5000] as $kg) {
            $resultado = $this->controlador->registrar($this->cuadrilla(), ['id_contenedor' => 2, 'kg_recolectados' => $kg]);
            $this->assertFalse($resultado['exito'], "kg = {$kg} debería rechazarse");
        }
    }

    public function testContenedorInexistente(): void
    {
        $this->contenedores->method('buscarPorId')->willReturn(null);
        $this->assertSame(404, $this->controlador->registrar($this->cuadrilla(), ['id_contenedor' => 99])['codigo']);
    }

    public function testCamionInexistente(): void
    {
        $this->contenedores->method('buscarPorId')->willReturn($this->contenedor());
        $this->camiones->method('buscarPorId')->willReturn(null);
        $resultado = $this->controlador->registrar($this->cuadrilla(), ['id_contenedor' => 2, 'id_camion' => 50]);
        $this->assertSame(404, $resultado['codigo']);
    }

    public function testCuadrillaNoPuedeUsarRutaAjena(): void
    {
        $this->contenedores->method('buscarPorId')->willReturn($this->contenedor());
        $this->rutas->method('pertenece')->willReturn(false);
        $resultado = $this->controlador->registrar($this->cuadrilla(), ['id_contenedor' => 2, 'id_ruta' => 1]);
        $this->assertSame(403, $resultado['codigo']);
    }

    public function testRegistrarVaciaElContenedorYResuelveIncidencias(): void
    {
        $this->contenedores->method('buscarPorId')->willReturn($this->contenedor());
        $this->pdo->expects($this->once())->method('beginTransaction');
        $this->pdo->expects($this->once())->method('commit');

        $this->modelo->expects($this->once())
            ->method('crear')
            ->with($this->callback(fn($d) => $d['id_contenedor'] === 2 && $d['id_cuadrilla'] === 4
                && $d['kg_recolectados'] === 320.5 && $d['nivel_previo'] === 100))
            ->willReturn(77);
        $this->contenedores->expects($this->once())->method('marcarVaciado')->with(2);

        $pendientes = [
            ['id_incidencia' => 8, 'estado' => 'abierta', 'tipo' => 'desborde'],
            ['id_incidencia' => 9, 'estado' => 'en_curso', 'tipo' => 'falta_recoleccion'],
        ];
        $this->incidencias->method('resolverPorRecoleccion')->with(2)->willReturn($pendientes);
        $this->incidencias->expects($this->exactly(2))->method('registrarSeguimiento');
        $this->alertas->expects($this->exactly(2))->method('incidenciaCambioEstado');

        $resultado = $this->controlador->registrar($this->cuadrilla(), ['id_contenedor' => 2, 'kg_recolectados' => '320.5']);

        $this->assertTrue($resultado['exito']);
        $this->assertSame(77, $resultado['id']);
        $this->assertSame(2, $resultado['incidencias_resueltas']);
    }

    public function testSiFallaElGuardadoSeDeshaceLaTransaccion(): void
    {
        $this->contenedores->method('buscarPorId')->willReturn($this->contenedor());
        $this->modelo->method('crear')->willThrowException(new PDOException('error simulado'));
        $this->pdo->expects($this->once())->method('rollBack');
        $this->pdo->expects($this->never())->method('commit');

        $this->expectException(PDOException::class);
        $this->controlador->registrar($this->cuadrilla(), ['id_contenedor' => 2]);
    }

    public function testActualizarNivelValidaElPorcentaje(): void
    {
        $this->assertFalse($this->controlador->actualizarNivel(2, 150, $this->cuadrilla())['exito']);
    }

    public function testActualizarNivelDisparaAlerta(): void
    {
        $this->contenedores->method('buscarPorId')->willReturn(['id_contenedor' => 4, 'ubicacion' => 'Rambla', 'nivel_llenado' => 60]);
        $this->contenedores->expects($this->once())->method('actualizarNivel')->with(4, 85);
        $this->alertas->expects($this->once())->method('nivelContenedor')->with($this->anything(), 60.0, 85.0)->willReturn(80);

        $resultado = $this->controlador->actualizarNivel(4, '85', $this->cuadrilla());
        $this->assertTrue($resultado['exito']);
        $this->assertTrue($resultado['alerta']);
    }

    public function testCuadrillaSoloVeSuHistorial(): void
    {
        $this->modelo->expects($this->once())
            ->method('listar')
            ->with($this->callback(fn($f) => $f['id_cuadrilla'] === 4))
            ->willReturn([]);
        $this->controlador->listar($this->cuadrilla(4), ['id_cuadrilla' => 8]);
    }
}
