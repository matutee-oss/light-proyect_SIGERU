<?php

/**
 * CRUD completo de las entidades principales contra MySQL real.
 * Cada test se deshace al terminar (ver BaseDatosTestCase).
 */
class CrudIntegracionTest extends BaseDatosTestCase
{
    public function testCrudDeContenedores(): void
    {
        $modelo = new Contenedor(self::$conexion);

        $id = $modelo->crear(['ubicacion' => 'Prueba y Test', 'tipo_residuo' => 'Reciclables', 'id_zona' => 2, 'nivel_llenado' => 10]);
        $creado = $modelo->buscarPorId($id);
        $this->assertSame('Prueba y Test', $creado['ubicacion']);
        $this->assertSame('Municipio B', $creado['nombre_zona']);

        // Corrección 3ra entrega: editar ahora sí cambia la zona
        $modelo->actualizar($id, ['id_zona' => 6, 'estado' => 'roto']);
        $editado = $modelo->buscarPorId($id);
        $this->assertSame(6, (int) $editado['id_zona']);
        $this->assertSame('roto', $editado['estado']);
        $this->assertSame('Prueba y Test', $editado['ubicacion'], 'la edición parcial conserva lo demás');

        $modelo->eliminar($id);
        $this->assertNull($modelo->buscarPorId($id));
    }

    public function testNivelDeLlenadoYVaciado(): void
    {
        $modelo = new Contenedor(self::$conexion);
        $id = $modelo->crear(['ubicacion' => 'Nivel', 'tipo_residuo' => 'Comunes']);

        $modelo->actualizarNivel($id, 100);
        $this->assertSame('desbordado', $modelo->buscarPorId($id)['estado'], 'al 100 % queda desbordado');

        $modelo->marcarVaciado($id);
        $vacio = $modelo->buscarPorId($id);
        $this->assertSame(0, (int) $vacio['nivel_llenado']);
        $this->assertSame('funcional', $vacio['estado']);
        $this->assertNotNull($vacio['ultima_recoleccion']);
    }

    public function testCrudDeCamiones(): void
    {
        $modelo = new Camion(self::$conexion);
        $id = $modelo->crear(['matricula' => 'stz 4321', 'capacidad_kg' => 7000]);

        $this->assertSame('STZ 4321', $modelo->buscarPorId($id)['matricula'], 'se guarda en mayúsculas');
        $this->assertSame($id, (int) $modelo->buscarPorMatricula('STZ4321')['id_camion'], 'busca sin importar el espacio');

        $modelo->actualizar($id, ['matricula' => 'STZ 4321', 'estado' => 'mantenimiento']);
        $this->assertSame('mantenimiento', $modelo->buscarPorId($id)['estado']);

        $modelo->eliminar($id);
        $this->assertNull($modelo->buscarPorId($id));
    }

    public function testEliminarCamionConHistorialNoRompeLasFK(): void
    {
        // Antes fallaba: ingresos_centro y rutas apuntaban al camión sin ON DELETE
        $modelo = new Camion(self::$conexion);
        $modelo->eliminar(1);
        $this->assertNull($modelo->buscarPorId(1));
        $this->assertSame(0, $this->contar('SELECT COUNT(*) FROM ingresos_centro WHERE id_camion = 1'));
        $this->assertGreaterThan(0, $this->contar('SELECT COUNT(*) FROM ingresos_centro WHERE id_camion IS NULL'), 'el historial se conserva');
    }

    public function testCrudDeUsuariosYCambioDeSubtipo(): void
    {
        $modelo = new Usuario(self::$conexion);
        $id = $modelo->crear([
            'nombre' => 'Prueba', 'apellido' => 'Integración', 'ci' => '45678903', 'email' => 'prueba@sigeru.uy',
            'password_hash' => password_hash('clave1234', PASSWORD_DEFAULT), 'tipo_usuario' => 'vecino',
            'estado' => 'activo', 'id_zona' => 3,
        ]);
        $this->assertSame(3, $modelo->obtenerZonaVecino($id));

        $modelo->actualizarCompleto($id, ['tipo_usuario' => 'cuadrilla', 'telefono' => '099000000']);
        $modelo->cambiarSubtipo($id, 'vecino', 'cuadrilla');
        $this->assertSame(0, $this->contar('SELECT COUNT(*) FROM vecinos WHERE id_usuario = ?', [$id]));
        $this->assertSame(1, $this->contar('SELECT COUNT(*) FROM cuadrillas WHERE id_usuario = ?', [$id]));
        $this->assertTrue($modelo->esCuadrillaActiva($id));

        $modelo->eliminar($id);
        $this->assertNull($modelo->buscarPorId($id));
        $this->assertSame(0, $this->contar('SELECT COUNT(*) FROM cuadrillas WHERE id_usuario = ?', [$id]), 'la subtabla se borra en cascada');
    }

    public function testEliminarVecinoConservaSusIncidencias(): void
    {
        $antes = $this->contar('SELECT COUNT(*) FROM incidencias');
        (new Usuario(self::$conexion))->eliminar(3);
        $this->assertSame($antes, $this->contar('SELECT COUNT(*) FROM incidencias'));
        $this->assertSame(0, $this->contar('SELECT COUNT(*) FROM incidencias WHERE id_vecino = 3 OR reportado_por = 3'));
    }

    public function testCrudDeCentrosYRecursos(): void
    {
        $modelo = new CentroAcopio(self::$conexion);
        $id = $modelo->crear(['nombre' => 'Centro Prueba', 'ubicacion' => 'Cerro', 'capacidad' => 200, 'latitud' => '-34.88', 'longitud' => '-56.25']);
        $idRecurso = $modelo->agregarRecurso($id, 'Balanza', 1);

        $modelo->editarRecurso($id, $idRecurso, 'Balanza digital', 2);
        $this->assertSame('Balanza digital', $modelo->buscarRecurso($id, $idRecurso)['tipo']);

        $modelo->actualizar($id, ['nombre' => 'Centro Prueba 2', 'ubicacion' => 'Cerro', 'capacidad' => 250]);
        $this->assertSame('Centro Prueba 2', $modelo->buscarPorId($id)['nombre']);

        $this->assertTrue($modelo->eliminarRecurso($id, $idRecurso));
        $modelo->eliminar($id);
        $this->assertNull($modelo->buscarPorId($id));
    }
}
