<?php

use PHPUnit\Framework\TestCase;

/**
 * Panel de control (estadísticas) y mapa interactivo.
 */
class EstadisticaYMapaTest extends TestCase
{
    public function testMesValido(): void
    {
        $this->assertTrue(EstadisticaController::mesValido('2026-11'));
        $this->assertFalse(EstadisticaController::mesValido('2026-13'));
        $this->assertFalse(EstadisticaController::mesValido('noviembre'));
    }

    public function testMesAnteriorCruzaElAnio(): void
    {
        $this->assertSame('2025-12', EstadisticaController::mesAnterior('2026-01'));
        $this->assertSame('2026-10', EstadisticaController::mesAnterior('2026-11'));
    }

    public function testVariacionPorcentual(): void
    {
        $this->assertSame(50, EstadisticaController::variacion(15, 10));
        $this->assertSame(-20, EstadisticaController::variacion(4, 5));
        $this->assertNull(EstadisticaController::variacion(3, 0), 'sin mes anterior no hay variación');
    }

    public function testPanelRechazaMesInvalido(): void
    {
        $controlador = new EstadisticaController(
            $this->createMock(PDO::class),
            $this->createMock(Incidencia::class), $this->createMock(Contenedor::class),
            $this->createMock(Recoleccion::class), $this->createMock(CentroAcopio::class)
        );
        $this->assertSame(400, $controlador->panel('2026-99')['codigo']);
    }

    public function testPanelArmaElTopDeDesbordadosDelMes(): void
    {
        $incidencias = $this->createMock(Incidencia::class);
        $incidencias->method('resumenPorEstado')->willReturn(['total' => 10, 'abiertas' => 2, 'en_curso' => 1, 'resueltas' => 7, 'horas_promedio_resolucion' => 12]);
        $incidencias->method('topContenedoresDesbordados')->willReturnCallback(
            fn($mes) => $mes === '2026-11'
                ? [['ubicacion' => 'Bvar. Artigas', 'desbordes' => 3], ['ubicacion' => 'Grecia', 'desbordes' => 2]]
                : [['ubicacion' => 'Bvar. Artigas', 'desbordes' => 10]]
        );
        $contenedores = $this->createMock(Contenedor::class);
        $contenedores->method('resumenEstados')->willReturn(['total' => 14]);
        $recolecciones = $this->createMock(Recoleccion::class);
        $recolecciones->method('resumenMes')->willReturn(['recolecciones' => 5, 'kg' => 1000.0, 'contenedores_atendidos' => 4]);
        $centros = $this->createMock(CentroAcopio::class);
        $centros->method('listarTodos')->willReturn([]);

        $panel = (new EstadisticaController($this->createMock(PDO::class), $incidencias, $contenedores, $recolecciones, $centros))->panel('2026-11');

        $this->assertTrue($panel['exito']);
        $this->assertSame('Bvar. Artigas', $panel['top_desbordados'][0]['ubicacion']);
        $this->assertSame(5, $panel['indicadores']['desbordes_mes']);
        $this->assertSame(-50, $panel['indicadores']['desbordes_variacion']);
        $this->assertSame(70, $panel['indicadores']['porcentaje_resueltas']);
    }

    public function testElMapaNoMuestraDatosPersonalesAlVecino(): void
    {
        $incidencias = $this->createMock(Incidencia::class);
        $incidencias->method('listarParaMapa')->willReturn([[
            'id_incidencia' => 1, 'tipo' => 'desborde', 'reporta_nombre' => 'Ana', 'reportado_por' => 3,
            'descripcion' => 'Mi vecino tira basura', 'latitud' => -34.9, 'longitud' => -56.1,
        ]]);
        $camiones = $this->createMock(Camion::class);
        $camiones->expects($this->never())->method('listarGeolocalizados');
        $centros = $this->createMock(CentroAcopio::class);
        $centros->method('listarTodos')->willReturn([]);
        $contenedores = $this->createMock(Contenedor::class);
        $contenedores->method('listarConFiltros')->willReturn([]);

        $mapa = (new MapaController($this->createMock(PDO::class), $contenedores, $incidencias, $camiones, $centros, $this->createMock(Zona::class)))
            ->obtener(['id_usuario' => 7, 'tipo_usuario' => 'vecino'], []);

        $this->assertArrayNotHasKey('reporta_nombre', $mapa['incidencias'][0]);
        $this->assertArrayNotHasKey('descripcion', $mapa['incidencias'][0]);
        $this->assertSame([], $mapa['camiones'], 'el vecino no ve la ubicación de los camiones');
    }

    public function testElMapaIgnoraFiltrosInvalidos(): void
    {
        $contenedores = $this->createMock(Contenedor::class);
        $contenedores->expects($this->once())->method('listarConFiltros')->with(['estado' => 'roto'])->willReturn([]);
        $incidencias = $this->createMock(Incidencia::class);
        $incidencias->method('listarParaMapa')->willReturn([]);
        $centros = $this->createMock(CentroAcopio::class);
        $centros->method('listarTodos')->willReturn([]);

        (new MapaController($this->createMock(PDO::class), $contenedores, $incidencias, $this->createMock(Camion::class), $centros, $this->createMock(Zona::class)))
            ->obtener(['id_usuario' => 1, 'tipo_usuario' => 'administrador'], ['estado_contenedor' => 'roto', 'id_zona' => "1 OR 1=1", 'nivel_minimo' => '300']);
    }
}
