<?php

use PHPUnit\Framework\TestCase;

/**
 * Validaciones puras (sin base de datos).
 */
class ValidacionTest extends TestCase
{
    public function testCedulasUruguayasValidas(): void
    {
        $this->assertTrue(Validacion::cedulaUruguaya('11111111'));
        $this->assertTrue(Validacion::cedulaUruguaya('5.748.431-2'));
        $this->assertTrue(Validacion::cedulaUruguaya('1.234.567-2'));
    }

    public function testCedulasUruguayasInvalidas(): void
    {
        $this->assertFalse(Validacion::cedulaUruguaya('12345678'), 'dígito verificador incorrecto');
        $this->assertFalse(Validacion::cedulaUruguaya('123'), 'muy corta');
        $this->assertFalse(Validacion::cedulaUruguaya('abcdefgh'));
    }

    public function testNormalizarCedulaDejaSoloDigitos(): void
    {
        $this->assertSame('12345672', Validacion::normalizarCedula('1.234.567-2'));
    }

    public function testEmail(): void
    {
        $this->assertTrue(Validacion::email('ana@sigeru.uy'));
        $this->assertFalse(Validacion::email('ana@'));
        $this->assertFalse(Validacion::email('sin-arroba'));
    }

    public function testCoordenadasOpcionalesPeroCompletas(): void
    {
        $this->assertTrue(Validacion::coordenadas(null, null), 'ambas vacías es válido');
        $this->assertTrue(Validacion::coordenadas('-34.9', '-56.16'));
        $this->assertFalse(Validacion::coordenadas('-34.9', null), 'solo una coordenada');
        $this->assertFalse(Validacion::coordenadas('-95', '-56'), 'latitud fuera de rango');
        $this->assertFalse(Validacion::coordenadas('abc', '-56'));
    }

    public function testPorcentaje(): void
    {
        $this->assertTrue(Validacion::porcentaje(0));
        $this->assertTrue(Validacion::porcentaje('100'));
        $this->assertFalse(Validacion::porcentaje(101));
        $this->assertFalse(Validacion::porcentaje(-1));
        $this->assertFalse(Validacion::porcentaje('lleno'));
    }

    public function testFecha(): void
    {
        $this->assertTrue(Validacion::fecha('2026-11-06'));
        $this->assertFalse(Validacion::fecha('2026-02-30'), 'día inexistente');
        $this->assertFalse(Validacion::fecha('06/11/2026'));
        $this->assertFalse(Validacion::fecha(null));
    }

    public function testMatricula(): void
    {
        $this->assertTrue(Validacion::matricula('SBC 1234'));
        $this->assertTrue(Validacion::matricula('sbc1234'));
        $this->assertFalse(Validacion::matricula('1234 SBC'));
    }

    public function testFaltanteDevuelveElPrimerCampoVacio(): void
    {
        $this->assertNull(Validacion::faltante(['a' => 'x', 'b' => 'y'], ['a', 'b']));
        $this->assertSame('b', Validacion::faltante(['a' => 'x', 'b' => '  '], ['a', 'b']));
        $this->assertSame('a', Validacion::faltante([], ['a', 'b']));
    }

    public function testContrasenaMinimoOchoCaracteres(): void
    {
        $this->assertTrue(Validacion::contrasena('test1234'));
        $this->assertFalse(Validacion::contrasena('1234567'));
    }
}
