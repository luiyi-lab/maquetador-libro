<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessExportJob;
use App\Models\Book;
use App\Models\Export;
use App\Models\ExportJob;
use App\Services\PdfExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    /** Genera el PDF en el momento (preview = mismo pipeline que la exportación, §11). */
    public function preview(Request $request, Book $book, PdfExportService $pdf): StreamedResponse
    {
        $this->authorize('view', $book);

        $result = $pdf->render($book);
        $name = 'preview_' . Str::slug($book->title) . '.pdf';

        return response()->streamDownload(
            function () use ($result): void {
                echo $result['bytes'];
            },
            $name,
            ['Content-Type' => 'application/pdf'],
            'inline',
        );
    }

    /** Encola la exportación PDF (§15). */
    public function store(Request $request, Book $book): RedirectResponse
    {
        $this->authorize('export', $book);

        $export = Export::create([
            'book_id' => $book->id,
            'format' => 'pdf',
            'status' => Export::STATUS_PENDING,
        ]);

        $jobRow = ExportJob::create([
            'export_id' => $export->id,
            'status' => 'pending',
        ]);

        ProcessExportJob::dispatch($export->id, $jobRow->id);

        Log::channel('books')->info('export_queued', [
            'book_id' => $book->id,
            'export_id' => $export->id,
        ]);

        session()->flash('export_id', $export->id);

        return redirect()
            ->route('books.show', $book)
            ->with('status', 'Exportación en cola. Se actualizará el estado automáticamente.');
    }

    /** Estado de la exportación (para polling del frontend). */
    public function status(Request $request, Book $book, Export $export): JsonResponse
    {
        $this->authorize('view', $book);
        abort_unless($export->book_id === $book->id, 404);

        return response()->json([
            'id' => $export->id,
            'status' => $export->status,
            'page_count' => $export->page_count,
            'file_size' => $export->file_size,
            'error' => $export->error,
            'download_url' => $export->status === Export::STATUS_COMPLETED ? route('exports.download', [$book, $export]) : null,
        ]);
    }

    public function download(Request $request, Book $book, Export $export): StreamedResponse
    {
        $this->authorize('view', $book);
        abort_unless($export->book_id === $book->id, 404);
        abort_unless($export->status === Export::STATUS_COMPLETED, 404, 'La exportación no está disponible.');

        $disk = Storage::disk('local');
        abort_unless($disk->exists($export->file_path), 404, 'Archivo no encontrado.');

        return $disk->download($export->file_path, 'libro_' . Str::slug($book->title) . '.pdf');
    }
}
