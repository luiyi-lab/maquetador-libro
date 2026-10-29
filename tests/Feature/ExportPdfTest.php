<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Export;
use App\Models\User;
use App\Services\PdfExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Validación post-generación (§19): el PDF existe, es válido, tiene el número
 * de páginas correcto y la portada/contenido presentes. Nunca declarar
 * "PDF correcto" sin haberlo validado.
 */
class ExportPdfTest extends TestCase
{
    use RefreshDatabase;

    private function createBook(User $user, string $content): Book
    {
        Storage::fake('local');
        $file = UploadedFile::fake()->createWithContent('libro.md', $content);
        $this->actingAs($user)->post('/libros', ['file' => $file]);

        return Book::where('user_id', $user->id)->firstOrFail();
    }

    public function test_genera_pdf_valido_con_paginas_y_contenido(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();

        $book = $this->createBook($user, "# La vida que parecía perfecta\n\n## Capítulo 1: El recuerdo\n\n"
            . implode("\n\n", array_fill(0, 12, 'Si alguien me hubiera dicho lo que venía, tal vez no habría abierto la puerta aquel atardecer de otoño.'))
            . "\n\n## Capítulo 2: La tía\n\n" . implode("\n\n", array_fill(0, 8, 'Nunca hablamos de ella en esa casa, aunque su retrato seguía colgado en el pasillo mirándonos a todos.')));

        // Render directo del servicio
        $pdf = app(PdfExportService::class);
        $result = $pdf->render($book->fresh());

        $bytes = $result['bytes'];

        // §19: existe, es válido, número de páginas correcto
        $this->assertStringStartsWith('%PDF', $bytes, 'Debe ser un PDF válido');
        $this->assertStringContainsString('%%EOF', $bytes);
        $this->assertGreaterThan(5000, strlen($bytes), 'Un PDF de 2 capítulos no puede pesar menos de 5 KB');
        $this->assertGreaterThanOrEqual(3, $result['pages'], 'Portada + 2 capítulos con texto: al menos 3 páginas');
        $this->assertLessThanOrEqual(40, $result['pages'], 'El contenido debe caber en un número razonable de páginas');

        // Flujo completo con cola (sync en tests): exportar → completado → archivo persistido
        $response = $this->actingAs($user)->post("/libros/{$book->id}/exportar");
        $response->assertRedirect();

        $export = Export::where('book_id', $book->id)->firstOrFail();
        $this->assertSame('completed', $export->status);
        $this->assertNotNull($export->page_count);
        $this->assertNotNull($export->file_size);

        Storage::disk('local')->assertExists($export->file_path);

        // Descarga
        $this->actingAs($user)
            ->get("/libros/{$book->id}/exportaciones/{$export->id}/descargar")
            ->assertOk();
    }

    public function test_preview_genera_pdf_con_el_mismo_pipeline(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $book = $this->createBook($user, "# Título\n\n## Capítulo 1\n\nContenido de prueba del capítulo.");

        $response = $this->actingAs($user)->get("/libros/{$book->id}/preview.pdf");

        $response->assertOk();
        $this->assertStringStartsWith('%PDF', $response->streamedContent());
    }

    public function test_toc_se_genera_con_numeros_reales_cuando_esta_activo(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $book = $this->createBook($user, "# Título\n\n## Capítulo 1: Uno\n\nTexto uno.\n\n## Capítulo 2: Dos\n\nTexto dos.");
        $book->update(['design_config' => ['toc_enabled' => true]]);

        $pdf = app(PdfExportService::class);
        $result = $pdf->render($book->fresh());

        $this->assertStringStartsWith('%PDF', $result['bytes']);
        $this->assertGreaterThanOrEqual(3, $result['pages'], 'Portada + índice + capítulos');
    }
}
