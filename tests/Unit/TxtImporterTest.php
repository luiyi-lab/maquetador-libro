<?php

namespace Tests\Unit;

use App\Importers\TxtImporter;
use PHPUnit\Framework\TestCase;

/**
 * Casos de §18 y §19: manuscritos desordenados con marcadores mezclados.
 * El sistema debe detectar, proponer y marcar ambigüedad — nunca fallar en silencio.
 */
class TxtImporterTest extends TestCase
{
    private TxtImporter $importer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importer = new TxtImporter();
    }

    public function test_ejemplo_del_agents_produce_titulo_y_dos_capitulos(): void
    {
        $txt = "CUANDO REGRESÓ EL RECUERDO\n\n\nCAPITULO 1\n\nLa vida que parecía perfecta\n\n\nSi alguien me hubiera dicho...\n\n\nCAPÍTULO II\n\nEl recuerdo de mi tía\n\nNunca hablamos de ella.";

        $doc = $this->importer->parse($txt, 'manuscrito.txt');

        $this->assertSame('CUANDO REGRESÓ EL RECUERDO', $doc->title);
        $this->assertCount(2, $doc->chapters);

        $this->assertSame(1, $doc->chapters[0]->number);
        $this->assertSame('La vida que parecía perfecta', $doc->chapters[0]->title);
        $this->assertSame('Si alguien me hubiera dicho...', $doc->chapters[0]->blocks[0]->content);

        $this->assertSame(2, $doc->chapters[1]->number);
        $this->assertSame('El recuerdo de mi tía', $doc->chapters[1]->title);
        $this->assertSame('Nunca hablamos de ella.', $doc->chapters[1]->blocks[0]->content);
    }

    public function test_marcadores_mezclados_capitulo_uno_romano_y_minusculas(): void
    {
        $txt = "Libro de prueba\n\nCAPITULO 1\n\nPrimer contenido.\n\nCapítulo uno\n\nSegundo contenido.\n\nCAPITULO I\n\nTercer contenido.\n\ncapitulo 3\n\nCuarto contenido.";

        $doc = $this->importer->parse($txt, 'mezclado.txt');

        $this->assertCount(4, $doc->chapters);
        $this->assertSame(1, $doc->chapters[0]->number);
        $this->assertSame(1, $doc->chapters[1]->number, '«Capítulo uno» → 1 (palabra)');
        $this->assertSame(1, $doc->chapters[2]->number, '«CAPITULO I» → 1 (romano)');
        $this->assertSame(3, $doc->chapters[3]->number, '«capitulo 3» → 3');
    }

    public function test_candidatos_ambiguos_se_marcan_para_confirmacion(): void
    {
        // "Capítulo 7" no aislado: puntuación de confianza media → advertencia
        $txt = "Título\n\nCAPITULO 1\n\nTexto.\n\ncapitulo 2\nMás texto pegado al marcador.";

        $doc = $this->importer->parse($txt, 'ambiguo.txt');

        $flagged = array_filter($doc->chapters, fn ($c) => $c->needsConfirmation);
        $this->assertNotEmpty($flagged, 'El marcador no aislado debe marcarse ambiguo');

        $messages = array_map(fn ($w) => $w->message, $doc->warnings);
        $ambiguousWarning = array_values(array_filter(
            $messages,
            fn ($m) => str_contains($m, 'confianza'),
        ));
        $this->assertNotEmpty($ambiguousWarning, 'Debe haber advertencia con la confianza');
    }

    public function test_sin_marcadores_todo_va_a_un_capitulo(): void
    {
        $txt = "Párrafo uno.\n\nPárrafo dos.\n\nPárrafo tres.";

        $doc = $this->importer->parse($txt, 'sinestructura.txt');

        $this->assertCount(1, $doc->chapters);
        $this->assertCount(3, $doc->chapters[0]->blocks);
        $this->assertTrue($doc->chapters[0]->needsConfirmation);
        $this->assertNotEmpty(array_filter(
            $doc->warnings,
            fn ($w) => str_contains($w->message, 'No se detectaron marcadores'),
        ));
    }

    public function test_lineas_duras_dentro_de_parrafo_se_unen(): void
    {
        $txt = "Título\n\nCAPITULO 1\n\nEsta línea\ncontinúa acá\ny sigue acá.\n\nNuevo párrafo.";

        $doc = $this->importer->parse($txt, 'duras.txt');

        $this->assertSame('Esta línea continúa acá y sigue acá.', $doc->chapters[0]->blocks[0]->content);
        $this->assertSame('Nuevo párrafo.', $doc->chapters[0]->blocks[1]->content);
    }

    public function test_subtitulo_con_punto_final_no_se_confunde_con_titulo(): void
    {
        // La línea tras el marcador termina en "..." → no es título, es contenido
        $txt = "Título\n\nCAPÍTULO 1\n\nSi alguien me hubiera dicho...\n\nel recuerdo vendría";

        $doc = $this->importer->parse($txt, 'puntito.txt');

        $this->assertNull($doc->chapters[0]->title);
        $this->assertSame('Si alguien me hubiera dicho...', $doc->chapters[0]->blocks[0]->content);
    }
}
