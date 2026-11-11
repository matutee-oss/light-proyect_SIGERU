<?php

use PHPUnit\Framework\TestCase;

/**
 * Informes simples: período, conteos y exportación CSV.
 */
class InformeControllerTest extends TestCase
{
    private $incidencias;
    private $recolecciones;
    private InformeController $controlador;

    protected function setUp(): void
    {
        $this->incidencias = $this->createMock(Incidencia::class);
        $this->recolecciones = $this->createMock(Recoleccion::class);
        $this->controlador = new InformeController($this->createMock(PDO::class), $this->incidencias, $this->recolecciones);
    }

    public function testPeriodoPorDefectoEsElMesEnCurso(): void
    {
        [$filtros, $error] = InformeController::normalizarPeriodo([]);
        $this->assertNull($error);
        $this->assertSame(date('Y-m-01'), $filtros['desde']);
        $this->assertSame(date('Y-m-d'), $filtros['hasta']);
    }

    public function testPeriodoInvalido(): void
    {
        $this->assertNotNull(InformeController::normalizarPeriodo(['desde' => '2026-11-10', 'hasta' => '2026-11-01'])[1]);
        $this->assertNotNull(InformeController::normalizarPeriodo(['desde' => '01/11/2026'])[1]);
        $this->assertNotNull(InformeController::normalizarPeriodo(['desde' => '2024-01-01', 'hasta' => '2026-01-01'])[1], 'más de un año');
    }

    public function testSituacionInvalida(): void
    {
        $this->assertSame(400, $this->controlador->incidencias(['situacion' => 'perdidas'])['codigo']);
    }

    public function testInformeDeCerradasFiltraPorResueltas(): void
    {
        $this->incidencias->expects($this->once())
            ->method('listar')
            ->with($this->callback(fn($f) => $f['estado'] === 'resuelta'))
            ->willReturn([[
                'id_incidencia' => 1, 'tipo' => 'desborde', 'nombre_zona' => 'Municipio C',
                'cuadrilla_nombre' => 'Carlos', 'cuadrilla_apellido' => 'López',
                'fecha_apertura' => '2026-11-01 10:00:00', 'fecha_resolucion' => '2026-11-01 16:30:00',
            ]]);
        $this->incidencias->method('resumenPorEstado')->willReturn([
            'total' => 4, 'abiertas' => 1, 'en_curso' => 1, 'resueltas' => 2, 'horas_promedio_resolucion' => 6.5,
        ]);

        $resultado = $this->controlador->incidencias(['situacion' => 'cerradas', 'desde' => '2026-11-01', 'hasta' => '2026-11-06']);

        $this->assertTrue($resultado['exito']);
        $this->assertSame(2, $resultado['resumen']['abiertas'], 'abiertas = sin asignar + en curso');
        $this->assertSame(50, $resultado['resumen']['porcentaje_cerradas']);
        $this->assertSame('Carlos López', $resultado['filas'][0]['cuadrilla']);
        $this->assertSame(6.5, $resultado['filas'][0]['horas_resolucion']);
    }

    public function testContarPorAgrupaYOrdena(): void
    {
        $filas = [['tipo' => 'rotura'], ['tipo' => 'desborde'], ['tipo' => 'desborde'], ['tipo' => null]];
        $this->assertSame(
            [['clave' => 'desborde', 'total' => 2], ['clave' => 'rotura', 'total' => 1], ['clave' => 'Sin dato', 'total' => 1]],
            InformeController::contarPor($filas, 'tipo')
        );
    }

    public function testCsvParaExcelEnEspanol(): void
    {
        $csv = InformeController::generarCsv(
            [['id' => 1, 'texto' => 'Bolsas; muchas'], ['id' => 2, 'texto' => '=HYPERLINK("malo")']],
            ['id' => 'N°', 'texto' => 'Descripción']
        );

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv, 'BOM para que Excel lea las tildes');
        $this->assertStringContainsString('N°;Descripción', $csv);
        $this->assertStringContainsString('"Bolsas; muchas"', $csv, 'los ; dentro del texto se encierran entre comillas');
        $this->assertStringContainsString("'=HYPERLINK", $csv, 'se neutralizan fórmulas (inyección CSV)');
    }

    public function testInformeDeRecoleccionesSumaKilos(): void
    {
        $this->recolecciones->method('listar')->willReturn([
            ['id_contenedor' => 1, 'kg_recolectados' => '300.00', 'nombre_zona' => 'Municipio B', 'cuadrilla_nombre' => null, 'cuadrilla_apellido' => null],
            ['id_contenedor' => 1, 'kg_recolectados' => '200.00', 'nombre_zona' => 'Municipio B', 'cuadrilla_nombre' => 'Jorge', 'cuadrilla_apellido' => 'Silva'],
        ]);
        $resultado = $this->controlador->recolecciones(['desde' => '2026-11-01', 'hasta' => '2026-11-06']);
        $this->assertSame(500.0, $resultado['resumen']['kg']);
        $this->assertSame(1, $resultado['resumen']['contenedores_atendidos']);
        $this->assertSame(250.0, $resultado['resumen']['kg_promedio']);
    }
}
