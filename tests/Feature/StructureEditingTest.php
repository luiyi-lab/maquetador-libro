<?php

namespace Tests\Feature;

use App\Models\BookBlock;
use App\Models\Chapter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Operaciones de corrección manual (§9) y orden explícito (§19).
 */
class StructureEditingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private $book;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->user = User::factory()->create();
        $file = UploadedFile::fake()->createWithContent('libro.md', "# Título\n\n## Capítulo 1: Uno\n\nTexto uno.\n\n## Capítulo 2: Dos\n\nTexto dos.\n\n## Capítulo 3: Tres\n\nTexto tres.");
        $this->actingAs($this->user)->post('/libros', ['file' => $file]);
        $this->book = \App\Models\Book::where('user_id', $this->user->id)->firstOrFail();
    }

    public function test_reordenar_guarda_el_orden_explícito(): void
    {
        $ids = $this->book->chapters()->orderBy('sort_order')->pluck('id')->all();
        $reversed = array_reverse($ids);

        $this->actingAs($this->user)
            ->post("/libros/{$this->book->id}/capitulos/reordenar", ['order' => $reversed])
            ->assertRedirect();

        $this->assertSame($reversed, $this->book->fresh()->chapters()->orderBy('sort_order')->pluck('id')->all());
    }

    public function test_reordenar_rechaza_un_orden_invalido(): void
    {
        $this->actingAs($this->user)
            ->post("/libros/{$this->book->id}/capitulos/reordenar", ['order' => [999, 998]])
            ->assertSessionHas('error');

        $this->assertSame(
            $this->book->chapters()->orderBy('sort_order')->pluck('id')->all(),
            $this->book->fresh()->chapters()->orderBy('sort_order')->pluck('id')->all(),
            'El orden no debe cambiar con un payload inválido',
        );
    }

    public function test_divide_un_capitulo_en_un_bloque(): void
    {
        $chapter = $this->book->chapters()->first();
        $block = $chapter->blocks()->first();

        $this->actingAs($this->user)
            ->post("/libros/{$this->book->id}/capitulos/{$chapter->id}/dividir/{$block->id}")
            ->assertRedirect();

        $this->assertSame(4, $this->book->fresh()->chapters()->count());
        $newChapter = $this->book->fresh()->chapters()->get()->get(1);
        $this->assertTrue($newChapter->needs_confirmation, 'El capítulo nuevo queda marcado para revisión');
    }

    public function test_un_el_capitulo_con_el_siguiente(): void
    {
        $chapter = $this->book->chapters()->orderBy('sort_order')->first();

        $this->actingAs($this->user)
            ->post("/libros/{$this->book->id}/capitulos/{$chapter->id}/fusionar")
            ->assertRedirect();

        $this->assertSame(2, $this->book->fresh()->chapters()->count());
        $this->assertSame(
            2,
            $chapter->fresh()->blocks()->count(),
            'Los bloques del siguiente se conservan (fidelidad §10)',
        );
    }

    public function test_convierte_bloque_en_capitulo_con_titulo_corto(): void
    {
        $chapter = $this->book->chapters()->orderBy('sort_order')->first();
        $block = $chapter->blocks()->first();
        $block->update(['content' => 'Prólogo original']);

        $this->actingAs($this->user)
            ->post("/libros/{$this->book->id}/bloques/{$block->id}/convertir-capitulo")
            ->assertRedirect();

        $this->assertSame(4, $this->book->fresh()->chapters()->count());
        $newChapter = $this->book->fresh()->chapters()->get()->get(1);
        $this->assertSame('Prólogo original', $newChapter->title);
    }

    public function test_edita_un_bloque(): void
    {
        $block = $this->book->chapters()->first()->blocks()->first();

        $this->actingAs($this->user)
            ->patch("/libros/{$this->book->id}/bloques/{$block->id}", ['content' => 'Texto corregido por el usuario.'])
            ->assertRedirect();

        $this->assertSame('Texto corregido por el usuario.', $block->fresh()->content);
    }

    public function test_otro_usuario_no_puede_editar_bloques(): void
    {
        $intruder = User::factory()->create();
        $block = $this->book->chapters()->first()->blocks()->first();

        $this->actingAs($intruder)
            ->patch("/libros/{$this->book->id}/bloques/{$block->id}", ['content' => 'ataque'])
            ->assertForbidden();
    }
}
