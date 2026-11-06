<?php

use PHPUnit\Framework\TestCase;

/**
 * Notificaciones del usuario (campanita).
 */
class NotificacionControllerTest extends TestCase
{
    private $modelo;
    private NotificacionController $controlador;

    protected function setUp(): void
    {
        $this->modelo = $this->createMock(Notificacion::class);
        $this->controlador = new NotificacionController($this->createMock(PDO::class), $this->modelo);
    }

    public function testListarDevuelveContadorYLista(): void
    {
        $this->modelo->method('contarNoLeidas')->with(3)->willReturn(2);
        $this->modelo->method('listarPorUsuario')->with(3, false, 30)->willReturn([['id_notificacion' => 1]]);
        $this->assertSame(['no_leidas' => 2, 'notificaciones' => [['id_notificacion' => 1]]], $this->controlador->listar(3));
    }

    public function testNuevasSinPuntoDePartidaNoDevuelveNada(): void
    {
        $this->modelo->expects($this->never())->method('listarNuevas');
        $this->assertSame([], $this->controlador->nuevas(3, 0)['nuevas']);
    }

    public function testNoSePuedeMarcarUnaNotificacionAjena(): void
    {
        // El modelo filtra por id_usuario: si la notificación es de otro, no actualiza nada
        $this->modelo->method('marcarLeida')->with(50, 3)->willReturn(false);
        $this->assertSame(404, $this->controlador->marcarLeida(50, 3)['codigo']);
    }

    public function testMarcarTodas(): void
    {
        $this->modelo->method('marcarTodasLeidas')->willReturn(4);
        $this->assertSame(4, $this->controlador->marcarTodas(3)['actualizadas']);
    }
}
