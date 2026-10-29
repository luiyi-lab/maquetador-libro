<?php

namespace App\Services;

use App\DTOs\BookDocument;
use App\DTOs\ChapterData;
use App\Importers\BookImporter;
use App\Importers\MarkdownImporter;
use App\Importers\TxtImporter;
use App\Models\Book;
use App\Models\BookVersion;
use App\Models\Chapter;
use App\Models\BookBlock;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use RuntimeException;

/**
 * Orquesta la importación: archivo → detección de formato → parser →
 * normalización → modelo interno → persistencia (§5, §7).
 */
class BookImportService
{
    public const SUPPORTED_FORMATS = ['md', 'txt'];
    public const MAX_FILE_SIZE_KB = 10240; // 10 MB

    public function __construct(
        private readonly MarkdownImporter $markdownImporter,
        private readonly TxtImporter $txtImporter,
        private readonly BookNormalizationService $normalizer,
    ) {
    }

    /**
     * Guarda el original (nunca se sobrescribe, §13), importa y persiste la estructura.
     */
    public function importUploadedFile(int $userId, UploadedFile $file): Book
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: 'txt');
        $format = $extension === 'md' ? 'md' : 'txt';
        $importer = $this->importerFor($format);

        Log::channel('books')->info('upload_started', [
            'user_id' => $userId,
            'original_name' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
        ]);

        // El original se guarda fuera del directorio público y nunca se sobrescribe
        $directory = "books/u{$userId}/original";
        $path = $file->storeAs($directory, uniqid('ms_', true) . '.' . $extension);

        Log::channel('books')->info('upload_completed', ['user_id' => $userId, 'path' => $path]);

        $raw = Storage::get($path);
        if ($raw === null || trim($raw) === '') {
            throw new RuntimeException('El archivo está vacío o no se pudo leer.');
        }

        Log::channel('books')->info('import_started', ['format' => $format]);

        $encodingResult = $this->normalizer->normalizeEncoding($raw);
        $normalized = $this->normalizer->normalize($encodingResult);
        $normalizations = array_filter($normalized['ops'], fn ($v) => $v > 0);
        Log::channel('books')->info('normalization_completed', ['ops' => array_keys($normalizations)]);

        $document = $importer->parse($normalized['text'], $file->getClientOriginalName());

        if (trim($document->title) === '' && count($document->allChapters()) === 0) {
            throw new RuntimeException('No se pudo extraer contenido del archivo.');
        }

        $book = $this->persistDocument($userId, $document, $format, $path, $file->getClientOriginalName(), $normalizations);

        Log::channel('books')->info('import_completed', [
            'book_id' => $book->id,
            'chapters' => count($document->allChapters()),
            'warnings' => count($document->warnings),
        ]);

        return $book;
    }

    public function importerFor(string $format): BookImporter
    {
        return $format === 'md' ? $this->markdownImporter : $this->txtImporter;
    }

    private function persistDocument(
        int $userId,
        BookDocument $document,
        string $format,
        string $originalPath,
        string $originalName,
        array $normalizations,
    ): Book {
        $book = Book::create([
            'user_id' => $userId,
            'title' => $document->title,
            'subtitle' => $document->subtitle,
            'author' => $document->author,
            'language' => $document->language,
            'status' => 'imported',
            'template_slug' => 'classic',
        ]);

        BookVersion::create([
            'book_id' => $book->id,
            'label' => 'Importación original',
            'format' => $format,
            'original_path' => $originalPath,
            'original_name' => $originalName,
            'snapshot' => $document->toArray(),
            'warnings' => array_map(fn ($w) => $w->toArray(), $document->warnings),
            'normalization' => $normalizations,
        ]);

        $sortOrder = 0;
        foreach ($document->allChapters() as $chapterData) {
            $this->persistChapter($book, $chapterData, $sortOrder++);
        }

        return $book;
    }

    private function persistChapter(Book $book, ChapterData $chapterData, int $sortOrder): Chapter
    {
        $chapter = Chapter::create([
            'book_id' => $book->id,
            'section' => $chapterData->section,
            'number' => $chapterData->number,
            'title' => $chapterData->title,
            'sort_order' => $sortOrder,
            'confidence' => $chapterData->confidence,
            'needs_confirmation' => $chapterData->needsConfirmation,
        ]);

        foreach ($chapterData->blocks as $blockIndex => $blockData) {
            BookBlock::create([
                'chapter_id' => $chapter->id,
                'type' => $blockData->type,
                'content' => $blockData->content,
                'meta' => $blockData->meta ?: null,
                'sort_order' => $blockIndex,
            ]);
        }

        return $chapter;
    }
}
