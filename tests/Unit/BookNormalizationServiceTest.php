<?php

namespace Tests\Unit;

use App\Services\BookNormalizationService;
use PHPUnit\Framework\TestCase;

class BookNormalizationServiceTest extends TestCase
{
    private BookNormalizationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new BookNormalizationService();
    }

    public function test_convierte_windows_1252_a_utf8(): void
    {
        // "Décima edición" en Windows-1252
        $raw = "D\xE9cima edici\xF3n";

        $result = $this->service->normalizeEncoding($raw);

        $this->assertSame('Décima edición', $result);
    }

    public function test_deja_utf8_intacto(): void
    {
        $raw = 'La vida que parecía perfecta';

        $this->assertSame($raw, $this->service->normalizeEncoding($raw));
    }

    public function test_normaliza_operaciones_tecnicas_y_las_registra(): void
    {
        $raw = "\xEF\xBB\xBFUn\xC2\xA0párrafo con  espacios\r\n\r\n\r\n\r\ny saltos excesivos\ttabulado  \n";

        $result = $this->service->normalize($raw);
        $text = $result['text'];
        $ops = $result['ops'];

        $this->assertStringNotContainsString("\xEF\xBB\xBF", $text, 'BOM eliminado');
        $this->assertStringNotContainsString("\xC2\xA0", $text, 'NBSP convertido');
        $this->assertStringNotContainsString("\r", $text, 'CRLF normalizado');
        $this->assertStringNotContainsString("\t", $text, 'Tabulaciones convertidas');
        $this->assertStringNotContainsString("\n\n\n", $text, 'Saltos excesivos colapsados');
        $this->assertSame('Un párrafo con espacios', mb_substr($text, 0, 23));

        $this->assertSame(1, $ops['bom_removido']);
        $this->assertSame(1, $ops['fines_de_linea']);
        $this->assertSame(1, $ops['saltos_excesivos']);
    }

    public function test_no_altera_palabras(): void
    {
        $raw = "Cuando regresó el recuerdo\n\nCAPITULO 1";

        $result = $this->service->normalize($raw);

        $this->assertStringContainsString('regresó el recuerdo', $result['text']);
        $this->assertStringContainsString('CAPITULO 1', $result['text']);
    }
}
