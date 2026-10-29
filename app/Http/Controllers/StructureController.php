<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\BookBlock;
use App\Models\Chapter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Revisión y corrección manual de la estructura (§9).
 * Todas las operaciones reordenan explícitamente (nunca confiar en el orden accidental de la BD).
 */
class StructureController extends Controller
{
    public function updateBook(Request $request, Book $book): RedirectResponse
    {
        $this->authorize('update', $book);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'subtitle' => ['nullable', 'string', 'max:200'],
            'author' => ['nullable', 'string', 'max:120'],
        ]);

        $book->update($validated);

        return back()->with('status', 'Metadatos actualizados.');
    }

    public function updateChapter(Request $request, Book $book, Chapter $chapter): RedirectResponse
    {
        $this->authorize('update', $book);
        $this->assertChapterInBook($book, $chapter);

        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:200'],
            'number' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'section' => ['nullable', Rule::in(['front', 'body', 'back'])],
            'confirm' => ['nullable', 'boolean'],
        ]);

        $data = [];
        if (array_key_exists('title', $validated)) {
            $data['title'] = $validated['title'] ?: null;
        }
        if (array_key_exists('number', $validated)) {
            $data['number'] = $validated['number'];
        }
        if (isset($validated['section'])) {
            $data['section'] = $validated['section'];
        }
        $chapter->update($data);

        if ($request->boolean('confirm')) {
            $chapter->update(['needs_confirmation' => false, 'confidence' => 100]);
        }

        return back()->with('status', 'Capítulo actualizado.');
    }

    /** Capítulo → texto: sus bloques se fusionan en el capítulo anterior (o el siguiente si es el primero). */
    public function convertChapterToText(Book $book, Chapter $chapter): RedirectResponse
    {
        $this->authorize('update', $book);
        $this->assertChapterInBook($book, $chapter);

        $chapters = $book->chapters()->orderBy('sort_order')->get()->values();
        $index = $chapters->search(fn (Chapter $c) => $c->id === $chapter->id);
        $target = $chapters[$index - 1] ?? $chapters[$index + 1] ?? null;

        if ($target === null) {
            return back()->with('error', 'No hay otro capítulo donde fusionar este texto.');
        }

        DB::transaction(function () use ($chapter, $target): void {
            $base = $target->blocks()->max('sort_order') ?? -1;
            foreach ($chapter->blocks()->orderBy('sort_order')->get() as $i => $block) {
                $block->update(['chapter_id' => $target->id, 'sort_order' => $base + 1 + $i]);
            }
            $chapter->delete();
        });

        $this->reindexChapters($book);

        return back()->with('status', 'Capítulo convertido en texto (fusionado con el capítulo anterior/siguiente).');
    }

    /** Bloque de texto → capítulo: si el texto es corto se convierte en título del capítulo. */
    public function convertBlockToChapter(Book $book, BookBlock $block): RedirectResponse
    {
        $this->authorize('update', $book);
        $this->assertBlockInBook($book, $block);

        $chapter = $block->chapter;
        $text = trim((string) $block->content);

        $newChapter = Chapter::create([
            'book_id' => $book->id,
            'section' => $chapter->section,
            'number' => null,
            'title' => $text !== '' && mb_strlen($text) <= 80 ? $text : null,
            'sort_order' => $chapter->sort_order + 0.5,
            'confidence' => null,
            'needs_confirmation' => true,
        ]);

        if ($newChapter->title !== null) {
            $block->delete();
        } else {
            $block->update(['chapter_id' => $newChapter->id, 'sort_order' => 0]);
        }

        $this->reindexChapters($book);

        return back()->with('status', 'Bloque convertido en capítulo. Confirma su título y número.');
    }

    /** Divide el capítulo en el bloque indicado: ese bloque y los siguientes forman un capítulo nuevo. */
    public function splitChapter(Book $book, Chapter $chapter, BookBlock $block): RedirectResponse
    {
        $this->authorize('update', $book);
        $this->assertChapterInBook($book, $chapter);
        $this->assertBlockInBook($book, $block);

        DB::transaction(function () use ($book, $chapter, $block): void {
            $newChapter = Chapter::create([
                'book_id' => $book->id,
                'section' => $chapter->section,
                'number' => null,
                'title' => null,
                'sort_order' => $chapter->sort_order + 0.5,
                'confidence' => null,
                'needs_confirmation' => true,
            ]);

            $block->update(['chapter_id' => $newChapter->id, 'sort_order' => 0]);
            BookBlock::where('chapter_id', $chapter->id)
                ->where('sort_order', '>', $block->sort_order)
                ->each(function (BookBlock $b, $i) use ($newChapter): void {
                    $b->update(['chapter_id' => $newChapter->id, 'sort_order' => $i + 1]);
                });

            $this->reindexChapters($book);
        });

        return back()->with('status', 'Capítulo dividido. Asigna título y número al nuevo.');
    }

    /** Une el capítulo con el siguiente. */
    public function mergeChapter(Book $book, Chapter $chapter): RedirectResponse
    {
        $this->authorize('update', $book);
        $this->assertChapterInBook($book, $chapter);

        $next = $book->chapters()->where('sort_order', '>', $chapter->sort_order)->orderBy('sort_order')->first();
        if ($next === null) {
            return back()->with('error', 'No hay capítulo siguiente para unir.');
        }

        DB::transaction(function () use ($chapter, $next): void {
            $base = $chapter->blocks()->max('sort_order') ?? -1;
            foreach ($next->blocks()->orderBy('sort_order')->get() as $i => $block) {
                $block->update(['chapter_id' => $chapter->id, 'sort_order' => $base + 1 + $i]);
            }
            $next->delete();
        });

        $this->reindexChapters($book);

        return back()->with('status', 'Capítulo unido con el siguiente.');
    }

    /** Reordenar por drag & drop: el orden llega explícito y se guarda tal cual (§9). */
    public function reorderChapters(Request $request, Book $book): RedirectResponse
    {
        $this->authorize('update', $book);

        $validated = $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['integer'],
        ]);

        $bookIds = $book->chapters()->pluck('id')->all();
        $order = array_map('intval', $validated['order']);

        if (count(array_diff($bookIds, $order)) > 0 || count($order) !== count($bookIds)) {
            return back()->with('error', 'Orden inválido: no coincide con los capítulos del libro.');
        }

        DB::transaction(function () use ($order): void {
            foreach ($order as $i => $chapterId) {
                Chapter::where('id', $chapterId)->update(['sort_order' => $i]);
            }
        });

        return back()->with('status', 'Orden guardado.');
    }

    public function moveChapter(Book $book, Chapter $chapter, Request $request): RedirectResponse
    {
        $this->authorize('update', $book);
        $this->assertChapterInBook($book, $chapter);

        $direction = $request->input('direction') === 'up' ? -1 : 1;
        $chapters = $book->chapters()->orderBy('sort_order')->get()->values();
        $index = $chapters->search(fn (Chapter $c) => $c->id === $chapter->id);
        $swapIndex = $index + $direction;

        if ($swapIndex < 0 || $swapIndex >= $chapters->count()) {
            return back();
        }

        DB::transaction(function () use ($chapters, $index, $swapIndex): void {
            $chapters[$index]->update(['sort_order' => $swapIndex]);
            $chapters[$swapIndex]->update(['sort_order' => $index]);
        });

        $this->reindexChapters($book);

        return back()->with('status', 'Capítulo movido.');
    }

    public function storeChapter(Request $request, Book $book): RedirectResponse
    {
        $this->authorize('update', $book);

        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:200'],
            'number' => ['nullable', 'integer', 'min:1'],
        ]);

        $max = (int) ($book->chapters()->max('sort_order') ?? -1);

        Chapter::create([
            'book_id' => $book->id,
            'section' => 'body',
            'number' => $validated['number'] ?? null,
            'title' => $validated['title'] ?? null,
            'sort_order' => $max + 1,
            'confidence' => null,
            'needs_confirmation' => false,
        ]);

        return back()->with('status', 'Capítulo agregado.');
    }

    public function destroyChapter(Book $book, Chapter $chapter): RedirectResponse
    {
        $this->authorize('update', $book);
        $this->assertChapterInBook($book, $chapter);

        $chapter->delete();
        $this->reindexChapters($book);

        return back()->with('status', 'Capítulo eliminado.');
    }

    public function updateBlock(Request $request, Book $book, BookBlock $block): RedirectResponse
    {
        $this->authorize('update', $book);
        $this->assertBlockInBook($book, $block);

        $validated = $request->validate([
            'content' => ['required', 'string', 'max:200000'],
            'type' => ['nullable', Rule::in(BookBlock::TYPES)],
        ]);

        $block->update([
            'content' => $validated['content'],
            'type' => $validated['type'] ?? $block->type,
        ]);

        return back()->with('status', 'Bloque actualizado.');
    }

    public function storeBlock(Request $request, Book $book, Chapter $chapter): RedirectResponse
    {
        $this->authorize('update', $book);
        $this->assertChapterInBook($book, $chapter);

        $validated = $request->validate([
            'type' => ['required', Rule::in(['paragraph', 'page_break', 'separator', 'epigraph'])],
            'content' => ['nullable', 'string', 'max:100000'],
        ]);

        $max = (int) ($chapter->blocks()->max('sort_order') ?? -1);
        BookBlock::create([
            'chapter_id' => $chapter->id,
            'type' => $validated['type'],
            'content' => $validated['content'] ?? '',
            'sort_order' => $max + 1,
        ]);

        return back()->with('status', 'Bloque agregado.');
    }

    public function destroyBlock(Book $book, BookBlock $block): RedirectResponse
    {
        $this->authorize('update', $book);
        $this->assertBlockInBook($book, $block);

        $block->delete();

        return back()->with('status', 'Bloque eliminado.');
    }

    public function moveBlock(Book $book, BookBlock $block, Request $request): RedirectResponse
    {
        $this->authorize('update', $book);
        $this->assertBlockInBook($book, $block);

        $direction = $request->input('direction') === 'up' ? -1 : 1;
        $blocks = $block->chapter->blocks()->orderBy('sort_order')->get()->values();
        $index = $blocks->search(fn (BookBlock $b) => $b->id === $block->id);
        $swapIndex = $index + $direction;

        if ($swapIndex < 0 || $swapIndex >= $blocks->count()) {
            return back();
        }

        DB::transaction(function () use ($blocks, $index, $swapIndex): void {
            $blocks[$index]->update(['sort_order' => $swapIndex]);
            $blocks[$swapIndex]->update(['sort_order' => $index]);
        });

        return back();
    }

    private function reindexChapters(Book $book): void
    {
        $book->chapters()->orderBy('sort_order')->each(function (Chapter $c, $i): void {
            $c->update(['sort_order' => $i]);
        });
    }

    private function assertChapterInBook(Book $book, Chapter $chapter): void
    {
        abort_unless($chapter->book_id === $book->id, 404);
    }

    private function assertBlockInBook(Book $book, BookBlock $block): void
    {
        abort_unless($block->chapter->book_id === $book->id, 404);
    }
}
