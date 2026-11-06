<?php

use PHPUnit\Framework\TestCase;

/**
 * Sistema de notificaciones y alertas: quién se entera de cada evento.
 */
class ServicioAlertasTest extends TestCase
{
    private $notificaciones;
    private ServicioAlertas $alertas;

    protected function setUp(): void
    {
        $this->notificaciones = $this->createMock(Notificacion::class);
        $this->alertas = new ServicioAlertas($this->notificaciones);
    }

    private function incidencia(array $cambios = []): array
    {
        return array_merge([
            'id_incidencia' => 7, 'tipo' => 'desborde', 'prioridad' => 'alta',
            'contenedor_ubicacion' => 'Rambla y Massini', 'descripcion' => null,
            'reportado_por' => 3, 'id_cuadrilla' => null,
        ], $cambios);
    }

    public function testUmbralCruzado(): void
    {
        $this->assertSame(80, ServicioAlertas::umbralCruzado(70, 85));
        $this->assertSame(100, ServicioAlertas::umbralCruzado(70, 100), 'si salta los dos, gana el crítico');
        $this->assertSame(100, ServicioAlertas::umbralCruzado(85, 100));
        $this->assertNull(ServicioAlertas::umbralCruzado(85, 90), 'ya estaba arriba de 80: no se repite la alerta');
        $this->assertNull(ServicioAlertas::umbralCruzado(90, 50), 'bajar no genera alerta');
    }

    public function testDesbordeAvisaAAdministradoresYCuadrillas(): void
    {
        $roles = [];
        $this->notificaciones->expects($this->exactly(2))
            ->method('crearParaRoles')
            ->willReturnCallback(function (array $r) use (&$roles) {
                $roles[] = $r;
                return 1;
            });

        $this->alertas->incidenciaCreada($this->incidencia(), 3);

        $this->assertSame([['administrador'], ['cuadrilla']], $roles);
    }

    public function testRoturaDePrioridadMediaSoloAvisaAAdministradores(): void
    {
        $this->notificaciones->expects($this->once())
            ->method('crearParaRoles')
            ->with(['administrador']);

        $this->alertas->incidenciaCreada($this->incidencia(['tipo' => 'rotura', 'prioridad' => 'media']), 3);
    }

    public function testAlResolverSeAvisaAlVecinoQueReporto(): void
    {
        $this->notificaciones->expects($this->once())
            ->method('crear')
            ->with(3, 'incidencia', 'info', 'Tu reporte fue resuelto', $this->anything(), 'incidencias.html?id=7');

        $this->alertas->incidenciaCambioEstado($this->incidencia(), 'resuelta', 4);
    }

    public function testNoSeNotificaAQuienHizoElCambio(): void
    {
        $this->notificaciones->expects($this->never())->method('crear');
        // El vecino 3 reportó y él mismo es el actor: no se avisa a sí mismo
        $this->alertas->incidenciaCambioEstado($this->incidencia(), 'resuelta', 3);
    }

    public function testNivelDeContenedorSoloAlertaAlCruzarUmbral(): void
    {
        $this->notificaciones->expects($this->once())
            ->method('crearParaRoles')
            ->with(['administrador', 'cuadrilla'], 'contenedor', 'advertencia', 'Contenedor casi lleno');

        $contenedor = ['id_contenedor' => 4, 'ubicacion' => 'Rambla y Massini'];
        $this->assertSame(80, $this->alertas->nivelContenedor($contenedor, 60, 82));
        $this->assertNull($this->alertas->nivelContenedor($contenedor, 82, 88));
    }

    public function testAvisoPublicadoLlegaALosVecinosDeLaZona(): void
    {
        $this->notificaciones->expects($this->once())
            ->method('crearParaVecinos')
            ->with(3, 'Corte de calle', 'Mañana no pasa el camión', 'panel-vecino.html')
            ->willReturn(5);

        $this->assertSame(5, $this->alertas->avisoPublicado('Corte de calle', 'Mañana no pasa el camión', 3));
    }
}
