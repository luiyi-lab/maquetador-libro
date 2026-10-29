<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Services\BookImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

class BookController extends Controller
{
    public function __construct(
        private readonly BookImportService $importService,
    ) {
    }

    public function create(): View
    {
        return view('books.create');
    }

    public function store(Request $request): RedirectResponse
    {
        // Validación estricta de subidas (§14): extensión, MIME, tamaño y contenido.
        $validated = $request->validate([
            'file' => [
                'required',
                'file',
                'max:' . BookImportService::MAX_FILE_SIZE_KB,
                'extensions:md,txt',
            ],
            'title' => ['nullable', 'string', 'max:200'],
            'author' => ['nullable', 'string', 'max:120'],
        ], [
            'file.required' => 'Selecciona un archivo Markdown (.md) o de texto (.txt).',
            'file.extensions' => 'Formato no soportado. Usa .md o .txt.',
            'file.max' => 'El archivo supera el máximo de 10 MB.',
        ]);

        $file = $request->file('file');

        // Verificación de contenido: debe ser texto legible, no un binario disfrazado
        $mime = $file->getMimeType() ?? '';
        $allowedMimes = ['text/plain', 'text/markdown', 'text/x-markdown', 'application/octet-stream'];
        if (!in_array($mime, $allowedMimes, true)) {
            return back()->withErrors(['file' => "Tipo de archivo no permitido ({$mime}). Sube un .md o .txt de texto."]);
        }

        try {
            $book = $this->importService->importUploadedFile($request->user()->id, $file);
        } catch (RuntimeException $e) {
            Log::channel('books')->info('import_failed', ['error' => $e->getMessage()]);

            return back()->withErrors(['file' => $e->getMessage()]);
        }

        // Metadatos opcionales definidos en la subida
        $book->update(array_filter([
            'title' => $validated['title'] ?? null,
            'author' => $validated['author'] ?? null,
        ]));

        return redirect()->route('books.show', $book)
            ->with('status', 'Libro importado. Revisa la estructura detectada.');
    }

    public function show(Request $request, Book $book, \App\Services\TemplateService $templates): View
    {
        $this->authorize('view', $book);

        $book->load(['chapters.blocks', 'versions', 'exports']);
        $template = $templates->getTemplate($book->template_slug);

        $pageSizes = [];
        foreach ($template['page_sizes'] ?? [] as $size => $dims) {
            $pageSizes[$size] = match ($size) {
                'a4' => 'A4', 'a5' => 'A5', 'letter' => 'Carta (Letter)',
                default => str_replace('x', '×', $size) . ' in',
            };
        }

        return view('books.show', [
            'book' => $book,
            'warnings' => $book->versions->first()?->warnings ?? [],
            'templates' => $templates->listTemplates(),
            'pageSizes' => $pageSizes,
            'fonts' => $template['fonts'] ?? [],
            'design' => $templates->resolveDesignConfig($book),
        ]);
    }

    public function destroy(Request $request, Book $book): RedirectResponse
    {
        $this->authorize('delete', $book);
        $book->delete();

        return redirect()->route('dashboard')->with('status', 'Libro eliminado.');
    }
}
