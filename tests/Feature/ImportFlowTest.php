<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Flujo completo de importación (§22 MVP): subir → detectar → mostrar.
 * Incluye autorización por Policies (§14).
 */
class ImportFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_usuario_puede_subir_txt_y_ver_estructura_detectada(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();

        $content = "CUANDO REGRESÓ EL RECUERDO\n\nCAPITULO 1\n\nLa vida que parecía perfecta\n\n\nSi alguien me hubiera dicho...\n\nCAPÍTULO II\n\nEl recuerdo de mi tía.";
        $file = UploadedFile::fake()->createWithContent('manuscrito.txt', $content);

        $response = $this->actingAs($user)->post('/libros', ['file' => $file]);

        $response->assertRedirect();
        $book = Book::where('user_id', $user->id)->firstOrFail();

        $this->assertSame('CUANDO REGRESÓ EL RECUERDO', $book->title);
        $this->assertCount(2, $book->chapters);

        // El original nunca se sobrescribe y queda fuera del directorio público (§13, §14)
        $version = $book->versions->first();
        $this->assertNotNull($version);
        Storage::disk('local')->assertExists($version->original_path);
        $this->assertStringContainsString('original', $version->original_path);

        // La pantalla de revisión muestra la estructura y las advertencias
        $show = $this->actingAs($user)->get("/libros/{$book->id}");
        $show->assertOk();
        $show->assertSee('La vida que parecía perfecta');
        $show->assertSee('El recuerdo de mi tía');
        $show->assertSee('Título propuesto');
    }

    public function test_subida_rechaza_archivo_no_soportado(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();

        $file = UploadedFile::fake()->create('virus.exe', 10, 'application/octet-stream');

        $response = $this->actingAs($user)->post('/libros', ['file' => $file]);

        $response->assertSessionHasErrors('file');
        $this->assertSame(0, Book::count());
    }

    public function test_otro_usuario_no_puede_ver_el_libro_ajeno(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $intruder = User::factory()->create();

        $file = UploadedFile::fake()->createWithContent('libro.txt', "Título\n\nCAPITULO 1\n\nTexto.");
        $this->actingAs($owner)->post('/libros', ['file' => $file]);
        $book = Book::where('user_id', $owner->id)->firstOrFail();

        $this->actingAs($intruder)
            ->get("/libros/{$book->id}")
            ->assertForbidden();

        $this->actingAs($intruder)
            ->delete("/libros/{$book->id}")
            ->assertForbidden();

        $this->actingAs($intruder)
            ->get("/libros/{$book->id}/preview.pdf")
            ->assertForbidden();
    }

    public function test_invitado_es_redirigido_al_login(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->get('/libros/nuevo')->assertRedirect('/login');
    }
}
