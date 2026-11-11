<?php

use PHPUnit\Framework\TestCase;

/**
 * Registro y seguimiento de incidencias (reglas de negocio, sin base de datos).
 */
class IncidenciaControllerTest extends TestCase
{
    private $modelo;
    private $contenedores;
    private $usuarios;
    private $alertas;
    private IncidenciaController $controlador;

    protected function setUp(): void
    {
        $this->modelo = $this->createMock(Incidencia::class);
        $this->contenedores = $this->createMock(Contenedor::class);
        $this->usuarios = $this->createMock(Usuario::class);
        $this->alertas = $this->createMock(ServicioAlertas::class);
        $this->controlador = new IncidenciaController(
            $this->createMock(PDO::class),
            $this->modelo, $this->contenedores, $this->usuarios, $this->alertas
        );
    }

    private function usuario(string $rol, int $id = 10): array
    {
        return ['id_usuario' => $id, 'tipo_usuario' => $rol, 'nombre' => 'Test', 'apellido' => ucfirst($rol)];
    }

    private function incidencia(array $cambios = []): array
    {
        return array_merge([
            'id_incidencia' => 5, 'tipo' => 'desborde', 'estado' => 'abierta', 'prioridad' => 'alta',
            'id_contenedor' => 2, 'contenedor_estado' => 'desbordado', 'contenedor_ubicacion' => 'Bvar. Artigas',
            'id_cuadrilla' => null, 'id_vecino' => 3, 'reportado_por' => 3,
        ], $cambios);
    }

    // ------------------------------------------------------------
    // Reportar
    // ------------------------------------------------------------

    public function testReportarFallaSinDatosObligatorios(): void
    {
        $resultado = $this->controlador->reportar($this->usuario('vecino'), ['tipo' => 'desborde']);
        $this->assertFalse($resultado['exito']);
        $this->assertStringContainsString('id_contenedor', $resultado['mensaje']);
    }

    public function testReportarFallaConTipoInvalido(): void
    {
        $resultado = $this->controlador->reportar($this->usuario('vecino'), ['id_contenedor' => 1, 'tipo' => 'incendio']);
        $this->assertFalse($resultado['exito']);
        $this->assertStringContainsString('inválido', $resultado['mensaje']);
    }

    public function testTipoOtroExigeDescripcion(): void
    {
        $resultado = $this->controlador->reportar($this->usuario('vecino'), ['id_contenedor' => 1, 'tipo' => 'otro']);
        $this->assertFalse($resultado['exito']);
        $this->assertStringContainsString('descripción', $resultado['mensaje']);
    }

    public function testOperarioNoPuedeReportar(): void
    {
        $resultado = $this->controlador->reportar($this->usuario('operario'), ['id_contenedor' => 1, 'tipo' => 'rotura']);
        $this->assertSame(403, $resultado['codigo']);
    }

    public function testReportarFallaSiElContenedorNoExiste(): void
    {
        $this->contenedores->method('buscarPorId')->willReturn(null);
        $resultado = $this->controlador->reportar($this->usuario('vecino'), ['id_contenedor' => 999, 'tipo' => 'rotura']);
        $this->assertSame(404, $resultado['codigo']);
    }

    public function testNoSePermitenReportesDuplicados(): void
    {
        $this->contenedores->method('buscarPorId')->willReturn(['id_contenedor' => 2, 'estado' => 'funcional', 'nivel_llenado' => 50]);
        $this->modelo->method('listar')->willReturn([$this->incidencia()]);
        $this->modelo->expects($this->never())->method('crear');

        $resultado = $this->controlador->reportar($this->usuario('vecino'), ['id_contenedor' => 2, 'tipo' => 'desborde']);

        $this->assertSame(409, $resultado['codigo']);
        $this->assertStringContainsString('#5', $resultado['mensaje']);
    }

    public function testReporteDeVecinoCreaIncidenciaSeguimientoYAlertas(): void
    {
        $this->contenedores->method('buscarPorId')->willReturn(['id_contenedor' => 2, 'estado' => 'funcional', 'nivel_llenado' => 50]);
        $this->modelo->method('listar')->willReturn([]);
        $this->modelo->expects($this->once())
            ->method('crear')
            ->with(2, 'desborde', 10, 10, 'Bolsas en la vereda', 'alta')
            ->willReturn(42);
        $this->modelo->expects($this->once())
            ->method('registrarSeguimiento')
            ->with(42, 10, 'creada', null, 'abierta');
        $this->modelo->method('buscarPorId')->willReturn($this->incidencia(['id_incidencia' => 42]));
        $this->contenedores->expects($this->once())->method('marcarEstado')->with(2, 'desbordado');
        $this->alertas->expects($this->once())->method('incidenciaCreada');

        $resultado = $this->controlador->reportar($this->usuario('vecino'), [
            'id_contenedor' => 2, 'tipo' => 'desborde', 'descripcion' => 'Bolsas en la vereda',
            'prioridad' => 'baja', // un vecino no elige prioridad: se ignora
        ]);

        $this->assertTrue($resultado['exito']);
        $this->assertSame(42, $resultado['id']);
    }

    public function testPrioridadSugerida(): void
    {
        $this->assertSame('alta', IncidenciaController::prioridadSugerida('desborde', 10));
        $this->assertSame('alta', IncidenciaController::prioridadSugerida('rotura', 95));
        $this->assertSame('media', IncidenciaController::prioridadSugerida('rotura', 40));
        $this->assertSame('baja', IncidenciaController::prioridadSugerida('otro', 40));
    }

    // ------------------------------------------------------------
    // Tomar / asignar
    // ------------------------------------------------------------

    public function testSoloUnaCuadrillaPuedeTomar(): void
    {
        $resultado = $this->controlador->tomar(5, $this->usuario('vecino'));
        $this->assertSame(403, $resultado['codigo']);
    }

    public function testNoSePuedeTomarUnaIncidenciaDeOtraCuadrilla(): void
    {
        $this->modelo->method('buscarPorId')->willReturn($this->incidencia(['id_cuadrilla' => 99, 'estado' => 'en_curso']));
        $this->modelo->expects($this->never())->method('asignarCuadrilla');

        $resultado = $this->controlador->tomar(5, $this->usuario('cuadrilla', 10));
        $this->assertSame(409, $resultado['codigo']);
    }

    public function testNoSePuedeTomarUnaIncidenciaResuelta(): void
    {
        $this->modelo->method('buscarPorId')->willReturn($this->incidencia(['estado' => 'resuelta']));
        $resultado = $this->controlador->tomar(5, $this->usuario('cuadrilla'));
        $this->assertFalse($resultado['exito']);
    }

    public function testTomarAsignaRegistraSeguimientoYAvisaAlVecino(): void
    {
        $this->modelo->method('buscarPorId')->willReturn($this->incidencia());
        $this->modelo->expects($this->once())->method('asignarCuadrilla')->with(5, 10);
        $this->modelo->expects($this->once())->method('registrarSeguimiento')->with(5, 10, 'asignada', 'abierta', 'en_curso');
        $this->alertas->expects($this->once())->method('incidenciaAsignada');

        $this->assertTrue($this->controlador->tomar(5, $this->usuario('cuadrilla', 10))['exito']);
    }

    public function testAsignarRequiereCuadrillaActiva(): void
    {
        $this->modelo->method('buscarPorId')->willReturn($this->incidencia());
        $this->usuarios->method('esCuadrillaActiva')->willReturn(false);

        $resultado = $this->controlador->asignar(5, 77, $this->usuario('administrador'));
        $this->assertFalse($resultado['exito']);
        $this->assertStringContainsString('cuadrilla', $resultado['mensaje']);
    }

    // ------------------------------------------------------------
    // Cambio de estado
    // ------------------------------------------------------------

    public function testCambiarEstadoInvalido(): void
    {
        $resultado = $this->controlador->cambiarEstado(5, 'cerrada', $this->usuario('administrador'));
        $this->assertStringContainsString('inválido', $resultado['mensaje']);
    }

    public function testCuadrillaNoPuedeCambiarUnaIncidenciaQueNoTomo(): void
    {
        $this->modelo->method('buscarPorId')->willReturn($this->incidencia(['id_cuadrilla' => 99]));
        $resultado = $this->controlador->cambiarEstado(5, 'resuelta', $this->usuario('cuadrilla', 10));
        $this->assertSame(403, $resultado['codigo']);
    }

    public function testVecinoNoPuedeCambiarEstado(): void
    {
        $this->modelo->method('buscarPorId')->willReturn($this->incidencia());
        $resultado = $this->controlador->cambiarEstado(5, 'resuelta', $this->usuario('vecino', 3));
        $this->assertSame(403, $resultado['codigo']);
    }

    public function testNoSePuedePasarAEnCursoSinCuadrilla(): void
    {
        $this->modelo->method('buscarPorId')->willReturn($this->incidencia());
        $resultado = $this->controlador->cambiarEstado(5, 'en_curso', $this->usuario('administrador'));
        $this->assertFalse($resultado['exito']);
    }

    public function testResolverUnaRoturaDejaElContenedorFuncional(): void
    {
        $this->modelo->method('buscarPorId')->willReturn($this->incidencia([
            'tipo' => 'rotura', 'estado' => 'en_curso', 'id_cuadrilla' => 10, 'contenedor_estado' => 'roto',
        ]));
        $this->modelo->expects($this->once())->method('actualizarEstado')->with(5, 'resuelta');
        $this->modelo->expects($this->once())->method('registrarSeguimiento')->with(5, 10, 'estado', 'en_curso', 'resuelta', 'Tapa cambiada');
        $this->contenedores->expects($this->once())->method('marcarEstado')->with(2, 'funcional');
        $this->alertas->expects($this->once())->method('incidenciaCambioEstado');

        $resultado = $this->controlador->cambiarEstado(5, 'resuelta', $this->usuario('cuadrilla', 10), 'Tapa cambiada');
        $this->assertTrue($resultado['exito']);
    }

    public function testResolverUnDesbordeVaciaElContenedor(): void
    {
        $this->modelo->method('buscarPorId')->willReturn($this->incidencia(['estado' => 'en_curso', 'id_cuadrilla' => 10]));
        $this->contenedores->expects($this->once())->method('marcarVaciado')->with(2);

        $this->assertTrue($this->controlador->cambiarEstado(5, 'resuelta', $this->usuario('administrador', 1))['exito']);
    }

    // ------------------------------------------------------------
    // Consultas, comentarios y permisos
    // ------------------------------------------------------------

    public function testVecinoSoloVeSusPropiasIncidencias(): void
    {
        $this->modelo->method('buscarPorId')->willReturn($this->incidencia(['reportado_por' => 3, 'id_vecino' => 3]));
        $this->modelo->method('listarSeguimiento')->willReturn([]);

        $this->assertNotNull($this->controlador->obtener(5, $this->usuario('vecino', 3)));
        $this->assertNull($this->controlador->obtener(5, $this->usuario('vecino', 8)));
    }

    public function testOperarioNoVeIncidencias(): void
    {
        $this->assertSame([], $this->controlador->listarSegunRol($this->usuario('operario')));
    }

    public function testComentarioVacioNoSeGuarda(): void
    {
        $this->modelo->expects($this->never())->method('registrarSeguimiento');
        $this->assertFalse($this->controlador->comentar(5, $this->usuario('administrador'), '   ')['exito']);
    }

    public function testSoloAdministradorElimina(): void
    {
        $this->modelo->expects($this->never())->method('eliminar');
        $this->assertSame(403, $this->controlador->eliminar(5, $this->usuario('cuadrilla'))['codigo']);
    }
}
