<?php

/**
 * Pruebas unitarias de la lógica del acta (no requieren GLPI).
 * Ejecutar: composer install && composer test
 */

namespace GlpiPlugin\Actasentrega\Tests;

use GlpiPlugin\Actasentrega\ActaBuilder as A;
use PHPUnit\Framework\TestCase;

final class ActaBuilderTest extends TestCase
{
    private function acta(): array
    {
        return [
            'numero'        => 'AE-2026-000001',
            'tipo'          => 'entrega',
            'fecha'         => '2026-11-03 09:30:00',
            'entidad'       => 0,
            'entrega'       => ['id' => 3, 'nombre' => 'Técnico Soporte'],
            'recibe'        => ['id' => 12, 'nombre' => 'Usuaria Receptora'],
            'observaciones' => '',
            'activos'       => [
                ['itemtype' => 'Computer', 'items_id' => 41, 'nombre' => 'PC-ADM-041', 'serial' => '5CD1234XYZ',
                 'otherserial' => 'INV-00041', 'fabricante' => 'HP', 'modelo' => 'ProDesk 400', 'estado_fisico' => 'Bueno', 'users_id_asignado' => 12],
                ['itemtype' => 'Monitor', 'items_id' => 77, 'nombre' => 'MON-077', 'serial' => 'CN0ABC77',
                 'otherserial' => 'INV-00077', 'fabricante' => 'Dell', 'modelo' => 'P2422H', 'estado_fisico' => 'Bueno', 'users_id_asignado' => 12],
            ],
        ];
    }

    public function testActaValidaNoTieneErrores(): void
    {
        $this->assertSame([], A::validar($this->acta()));
    }

    public function testRechazaTipoNoPermitido(): void
    {
        $d = $this->acta();
        $d['tipo'] = 'prestamo';
        $this->assertContains('Tipo de acta no válido.', A::validar($d));
    }

    public function testRechazaMismaPersonaEntregaYRecibe(): void
    {
        $d = $this->acta();
        $d['entrega']['id'] = 12;
        $this->assertContains('Quien entrega y quien recibe deben ser personas distintas.', A::validar($d));
    }

    public function testExigeAlMenosUnActivo(): void
    {
        $d = $this->acta();
        $d['activos'] = [];
        $this->assertSame(['El acta debe incluir al menos un activo.'], A::validar($d));
    }

    public function testDetectaSerialVacio(): void
    {
        $d = $this->acta();
        $d['activos'][1]['serial'] = '';
        $this->assertSame(['Activo «MON-077»: falta número de serie.'], A::validar($d));
    }

    public function testDetectaInventarioVacio(): void
    {
        $d = $this->acta();
        $d['activos'][0]['otherserial'] = '  ';
        $this->assertSame(['Activo «PC-ADM-041»: falta número de inventario.'], A::validar($d));
    }

    public function testDetectaActivoRepetido(): void
    {
        $d = $this->acta();
        $d['activos'][1] = $d['activos'][0];
        $this->assertContains('Activo «PC-ADM-041»: activo repetido en el acta.', A::validar($d));
    }

    public function testEntregaExigeActivoAsignadoAQuienRecibe(): void
    {
        $d = $this->acta();
        $d['activos'][0]['users_id_asignado'] = 99;
        $this->assertSame(['Activo «PC-ADM-041»: en GLPI no está asignado a quien lo recibe.'], A::validar($d));
    }

    public function testDevolucionExigeActivoAsignadoAQuienDevuelve(): void
    {
        $d = $this->acta();
        $d['tipo'] = 'devolucion';
        $d['entrega']['id'] = 12; // devuelve la usuaria
        $d['recibe']['id'] = 3;   // recibe el técnico
        $this->assertSame([], A::validar($d));
        $d['activos'][1]['users_id_asignado'] = 3;
        $this->assertSame(['Activo «MON-077»: en GLPI no está asignado a quien lo devuelve.'], A::validar($d));
    }

    public function testTrasladoExigeActivoYaReasignadoAQuienRecibe(): void
    {
        $d = $this->acta();
        $d['tipo'] = 'traslado';
        $d['entrega']['id'] = 20; // custodio anterior
        $this->assertSame([], A::validar($d));
    }

    public function testNumeroConsecutivoPorTipo(): void
    {
        $this->assertSame('AE-2026-000123', A::numero('entrega', 2026, 123));
        $this->assertSame('AD-2026-000007', A::numero('devolucion', 2026, 7));
        $this->assertSame('AT-2027-000001', A::numero('traslado', 2027, 1));
    }

    public function testHuellaTiene64Hexadecimales(): void
    {
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', A::huella($this->acta()));
    }

    public function testHuellaNoDependeDelOrdenDeClavesNiDeActivos(): void
    {
        $d = $this->acta();
        $r = array_reverse($d, true);
        $r['activos'] = array_reverse($r['activos']);
        $r['activos'][0] = array_reverse($r['activos'][0], true);
        $this->assertSame(A::huella($d), A::huella($r));
    }

    public function testHuellaIgnoraCamposQueNoSonDelActa(): void
    {
        $d = $this->acta();
        $h = A::huella($d);
        $d['activos'][0]['users_id_asignado'] = 500; // dato de validación, no del documento
        $d['activos'][0]['tipo_activo'] = 'Computador';
        $this->assertSame($h, A::huella($d));
    }

    public function testVerificaActaIntegra(): void
    {
        $d = $this->acta();
        $this->assertTrue(A::verificar($d, A::huella($d)));
        $this->assertTrue(A::verificar($d, strtoupper(A::huella($d))));
    }

    public function testDetectaAlteracionDeUnCaracter(): void
    {
        $d = $this->acta();
        $h = A::huella($d);
        $d['activos'][0]['serial'] = '5CD1234XYY';
        $this->assertFalse(A::verificar($d, $h));
    }

    public function testDetectaCambioDeReceptor(): void
    {
        $d = $this->acta();
        $h = A::huella($d);
        $d['recibe']['nombre'] = 'Otra Persona';
        $this->assertFalse(A::verificar($d, $h));
    }

    public function testCanonicoConservaTildesSinEscapes(): void
    {
        $this->assertStringContainsString('Técnico Soporte', A::canonico($this->acta()));
    }

    public function testCompletitud(): void
    {
        $d = $this->acta();
        $this->assertSame(100.0, A::completitud($d));
        $d['activos'][1]['serial'] = '';
        $this->assertSame(91.7, A::completitud($d)); // 11 de 12 campos obligatorios
    }

    public function testAsignadoEsperadoPorTipo(): void
    {
        $this->assertSame(12, A::asignadoEsperado('entrega', 3, 12));
        $this->assertSame(3, A::asignadoEsperado('devolucion', 3, 12));
        $this->assertSame(12, A::asignadoEsperado('traslado', 3, 12));
        $this->assertNull(A::asignadoEsperado('otro', 3, 12));
    }
}
