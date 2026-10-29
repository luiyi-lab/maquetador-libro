<?php

namespace Tests\Unit;

use App\DTOs\BookStructureThresholds;
use App\Services\BookStructureAnalyzer;
use PHPUnit\Framework\TestCase;

class BookStructureAnalyzerTest extends TestCase
{
    private BookStructureAnalyzer $analyzer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->analyzer = new BookStructureAnalyzer();
    }

    public function test_marcador_completo_supera_el_umbral_de_aceptacion(): void
    {
        $lines = ['Texto', '', 'CAPITULO 1', '', 'Más texto'];
        $candidates = $this->analyzer->findChapterCandidates($lines);

        $this->assertCount(1, $candidates);
        $this->assertTrue($candidates[0]->isAutoAccepted());
        $this->assertGreaterThanOrEqual(BookStructureThresholds::AUTO_ACCEPT, $candidates[0]->score);
        $this->assertSame(1, $candidates[0]->number);
    }

    public function test_roman_y_palabras_se_interpretan(): void
    {
        $this->assertSame(2, $this->analyzer->romanToInt('II'));
        $this->assertSame(1994, $this->analyzer->romanToInt('MCMXCIV'));
        $this->assertNull($this->analyzer->romanToInt('HELLO'));
    }

    public function test_linea_normal_no_es_candidata(): void
    {
        $lines = ['Este es un párrafo normal del manuscrito con contenido largo.', '', 'Otro párrafo.'];

        $this->assertSame([], $this->analyzer->findChapterCandidates($lines));
    }

    public function test_titulo_propuesto_en_mayusculas_tiene_confianza_alta(): void
    {
        $lines = ['CUANDO REGRESÓ EL RECUERDO', '', 'CAPITULO 1', ''];

        $proposal = $this->analyzer->proposeTitle($lines, 2);

        $this->assertNotNull($proposal);
        $this->assertSame('CUANDO REGRESÓ EL RECUERDO', $proposal[0]);
        $this->assertGreaterThanOrEqual(85, $proposal[1]);
    }
}
