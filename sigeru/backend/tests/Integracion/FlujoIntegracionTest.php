<?php

/**
 * Flujos completos: reportar → tomar → recolectar → notificar, y las consultas
 * de informes, estadísticas y mapa sobre los datos de prueba de sigeru.sql.
 */
class FlujoIntegracionTest extends BaseDatosTestCase
{
    public function testFlujoCompletoDeUnaIncidencia(): void
    {
        $incidencias = new IncidenciaController(self::$conexion);
        $vecina = $this->usuario(7);     // Lucía
        $cuadrilla = $this->usuario(4);  // Carlos

        // 1. La vecina reporta un desborde
        $reporte = $incidencias->reportar($vecina, ['id_contenedor' => 12, 'tipo' => 'desborde', 'descripcion' => 'Prueba']);
        $this->assertTrue($reporte['exito'], $reporte['mensaje']);
        $id = $reporte['id'];
        $this->assertSame('desbordado', (new Contenedor(self::$conexion))->buscarPorId(12)['estado']);
        $this->assertGreaterThan(0, $this->contar(
            "SELECT COUNT(*) FROM notificaciones WHERE enlace = ? AND id_usuario IN (SELECT id_usuario FROM cuadrillas)",
            ["incidencias.html?id={$id}"]
        ), 'las cuadrillas reciben la alerta');

        // 2. La cuadrilla la toma
        $this->assertTrue($incidencias->tomar($id, $cuadrilla)['exito']);
        $this->assertSame('en_curso', $incidencias->obtener($id, $cuadrilla)['estado']);

        // 3. Registra la recolección: se resuelve sola
        $recolecciones = new RecoleccionController(new PdoConTransaccionAnidada(self::$conexion));
        $resultado = $recolecciones->registrar($cuadrilla, ['id_contenedor' => 12, 'kg_recolectados' => 280]);
        $this->assertTrue($resultado['exito'], $resultado['mensaje']);
        $this->assertSame(1, $resultado['incidencias_resueltas']);

        $final = $incidencias->obtener($id, $vecina);
        $this->assertSame('resuelta', $final['estado']);
        $this->assertNotNull($final['fecha_resolucion']);
        $this->assertSame(['creada', 'asignada', 'estado'], array_column($final['seguimiento'], 'accion'));
        $this->assertSame(0, (int) (new Contenedor(self::$conexion))->buscarPorId(12)['nivel_llenado']);

        // 4. La vecina fue notificada al tomarse y al resolverse
        $this->assertSame(2, $this->contar(
            'SELECT COUNT(*) FROM notificaciones WHERE id_usuario = 7 AND enlace = ?',
            ["incidencias.html?id={$id}"]
        ));
    }

    public function testRutaDeDemoYMarcarParadaRegistraRecoleccion(): void
    {
        $ruta = new Ruta(new PdoConTransaccionAnidada(self::$conexion));
        $idRuta = $ruta->crearRutaDemo(4);
        $hoy = $ruta->obtenerRutaHoy(4);
        $this->assertSame($idRuta, (int) $hoy['id_ruta']);
        $this->assertNotEmpty($hoy['paradas']);
        $this->assertSame('desbordado', $hoy['paradas'][0]['estado'], 'primero los desbordados');

        $controlador = new RecoleccionController(new PdoConTransaccionAnidada(self::$conexion));
        $idContenedor = (int) $hoy['paradas'][0]['id_contenedor'];
        $this->assertTrue($controlador->registrar($this->usuario(4), ['id_contenedor' => $idContenedor, 'id_ruta' => $idRuta])['exito']);
        $this->assertSame(409, $controlador->registrar($this->usuario(4), ['id_contenedor' => $idContenedor, 'id_ruta' => $idRuta])['codigo'], 'no se duplica');
        $this->assertSame(1, (int) $ruta->obtenerRutaHoy(4)['paradas'][0]['atendido']);
    }

    public function testNotificacionesSoloDelPropioUsuario(): void
    {
        $modelo = new Notificacion(self::$conexion);
        $creadas = $modelo->crearParaRoles(['administrador'], 'sistema', 'info', 'Hola', 'Mensaje de prueba');
        $this->assertSame(2, $creadas, 'hay dos administradores activos');

        $ultima = $modelo->listarPorUsuario(1, true, 1)[0];
        $this->assertFalse($modelo->marcarLeida((int) $ultima['id_notificacion'], 3), 'otro usuario no puede marcarla');
        $this->assertTrue($modelo->marcarLeida((int) $ultima['id_notificacion'], 1));
    }

    public function testInformesYEstadisticasCorrenSobreLosDatosDePrueba(): void
    {
        $informes = new InformeController(self::$conexion);
        $desde = date('Y-m-d', strtotime('-60 days'));
        $hasta = date('Y-m-d');

        $abiertas = $informes->incidencias(['situacion' => 'abiertas', 'desde' => $desde, 'hasta' => $hasta]);
        $this->assertTrue($abiertas['exito']);
        foreach ($abiertas['filas'] as $fila) {
            $this->assertNotSame('resuelta', $fila['estado']);
        }
        $cerradas = $informes->incidencias(['situacion' => 'cerradas', 'desde' => $desde, 'hasta' => $hasta]);
        $this->assertSame($cerradas['resumen']['cerradas'], count($cerradas['filas']));

        $this->assertGreaterThan(0, $informes->recolecciones(['desde' => $desde, 'hasta' => $hasta])['resumen']['kg']);

        $panel = (new EstadisticaController(self::$conexion))->panel();
        $this->assertTrue($panel['exito']);
        $this->assertCount(30, $panel['evolucion_incidencias']);
        $this->assertCount(14, $panel['kg_por_dia']);
        $this->assertCount(8, $panel['incidencias_por_zona']);
        if ($panel['top_desbordados']) {
            $this->assertGreaterThanOrEqual($panel['top_desbordados'][count($panel['top_desbordados']) - 1]['desbordes'], $panel['top_desbordados'][0]['desbordes']);
        }
    }

    public function testMapaConFiltros(): void
    {
        $mapa = (new MapaController(self::$conexion))->obtener($this->usuario(1), ['estado_contenedor' => 'desbordado', 'prioridad' => 'alta']);
        $this->assertNotEmpty($mapa['contenedores']);
        foreach ($mapa['contenedores'] as $c) {
            $this->assertSame('desbordado', $c['estado']);
        }
        foreach ($mapa['incidencias'] as $i) {
            $this->assertSame('alta', $i['prioridad']);
            $this->assertNotSame('resuelta', $i['estado'], 'por defecto solo pendientes');
        }
        $this->assertNotEmpty($mapa['centros'], 'los centros ahora tienen coordenadas');
    }
}
