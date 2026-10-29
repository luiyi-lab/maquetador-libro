<?php

namespace Tests\Unit;

use App\Importers\MarkdownImporter;
use PHPUnit\Framework\TestCase;

class MarkdownImporterTest extends TestCase
{
    private MarkdownImporter $importer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importer = new MarkdownImporter();
    }

    public function test_mapea_encabezados_a_titulo_y_capitulos(): void
    {
        $md = "# La vida que parecía perfecta\n\n## Capítulo 1: El recuerdo\n\nSi alguien me hubiera dicho...\n\n## Capítulo II: La tía\n\nNunca hablamos de ella.";

        $doc = $this->importer->parse($md, 'libro.md');

        $this->assertSame('La vida que parecía perfecta', $doc->title);
        $this->assertCount(2, $doc->chapters);
        $this->assertSame(1, $doc->chapters[0]->number);
        $this->assertSame('El recuerdo', $doc->chapters[0]->title);
        $this->assertSame(2, $doc->chapters[1]->number);
        $this->assertSame('La tía', $doc->chapters[1]->title);
        $this->assertSame('Si alguien me hubiera dicho...', $doc->chapters[0]->blocks[0]->content);
    }

    public function test_bloque_capitulo_personalizado_mapea_directo(): void
    {
        $md = <<<MD
:::capitulo num=1 titulo="La vida que parecía perfecta"
Texto del capítulo.
:::
MD;

        $doc = $this->importer->parse($md, 'libro.md');

        $this->assertCount(1, $doc->chapters);
        $this->assertSame(1, $doc->chapters[0]->number);
        $this->assertSame('La vida que parecía perfecta', $doc->chapters[0]->title);
        $this->assertSame('Texto del capítulo.', $doc->chapters[0]->blocks[0]->content);
        $this->assertFalse($doc->chapters[0]->needsConfirmation);
    }

    public function test_bloque_epigrafe_con_autor_y_fuente(): void
    {
        $md = <<<MD
:::epigrafe autor="Julio Cortázar" fuente="Rayuela"
Yo no hago cosas para que duren.
:::

:::capitulo num=1 titulo="Primero"
Contenido.
:::
MD;

        $doc = $this->importer->parse($md, 'libro.md');

        $epigraphChapter = $doc->frontMatter[0];
        $this->assertSame('front', $epigraphChapter->section);
        $block = $epigraphChapter->blocks[0];
        $this->assertSame('epigraph', $block->type);
        $this->assertSame('Yo no hago cosas para que duren.', $block->content);
        $this->assertSame('Julio Cortázar', $block->meta['author']);
        $this->assertSame('Rayuela', $block->meta['source']);
    }

    public function test_reconoce_listas_citas_separadores_e_imagenes(): void
    {
        $md = "# Título\n\n## Capítulo 1\n\n- uno\n- dos\n\n> Una cita memorable\n\n---\n\n![Portada](https://example.com/portada.jpg)\n\nPárrafo final.";

        $doc = $this->importer->parse($md, 'libro.md');

        $types = array_map(fn ($b) => $b->type, $doc->chapters[0]->blocks);
        $this->assertSame(['list', 'quote', 'separator', 'image', 'paragraph'], $types);
        $this->assertFalse($doc->chapters[0]->blocks[0]->meta['ordered']);
        $this->assertSame('https://example.com/portada.jpg', $doc->chapters[0]->blocks[3]->meta['src']);
    }

    public function test_salto_de_pagina_personalizado(): void
    {
        $md = "# Título\n\n## Capítulo 1\n\nAntes.\n\n:::salto\n\n:::\n\nDespués del salto.";

        $doc = $this->importer->parse($md, 'libro.md');

        $types = array_map(fn ($b) => $b->type, $doc->chapters[0]->blocks);
        $this->assertSame(['paragraph', 'page_break', 'paragraph'], $types);
    }

    public function test_h1_repetido_se_marca_ambiguo(): void
    {
        $md = "# Libro\n\n## Capítulo 1\n\nTexto.\n\n# Otra cosa";

        $doc = $this->importer->parse($md, 'libro.md');

        $this->assertCount(2, $doc->chapters);
        $this->assertTrue($doc->chapters[1]->needsConfirmation);
        $this->assertStringContainsString('Otra cosa', $doc->warnings[0]->message);
    }

    public function test_sin_titulo_usa_nombre_de_archivo(): void
    {
        $doc = $this->importer->parse("## Capítulo 1\n\nTexto.", 'mi_manuscrito.md');

        $this->assertSame('mi_manuscrito', $doc->title);
    }
}
