<?php

namespace App\Jobs;

use App\Models\Export;
use App\Models\ExportJob;
use App\Services\PdfExportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Exportación por cola (§15): pending → processing → completed | failed.
 * Si falla, se registra el error, se marca failed y el manuscrito queda intacto.
 */
class ProcessExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 300;

    public function __construct(
        public readonly int $exportId,
        public readonly int $exportJobRowId,
    ) {
    }

    public function handle(PdfExportService $pdf): void
    {
        $export = Export::with('book')->findOrFail($this->exportId);
        $jobRow = ExportJob::findOrFail($this->exportJobRowId);

        $jobRow->update([
            'status' => 'processing',
            'started_at' => now(),
            'attempts' => $jobRow->attempts + 1,
        ]);
        $export->update(['status' => 'processing']);

        Log::channel('books')->info('export_started', [
            'book_id' => $export->book_id,
            'export_id' => $export->id,
            'format' => $export->format,
        ]);

        try {
            $relativePath = "books/u{$export->book->user_id}/exports/export_{$export->id}.pdf";
            $absolutePath = Storage::disk('local')->path($relativePath);
            @mkdir(dirname($absolutePath), 0775, true);

            $result = $pdf->renderToFile($export->book, $absolutePath);

            $export->update([
                'status' => 'completed',
                'file_path' => $relativePath,
                'file_size' => strlen($result['bytes']),
                'page_count' => $result['pages'],
                'error' => null,
            ]);
            $export->book->update(['status' => 'exported']);

            $jobRow->update([
                'status' => 'completed',
                'finished_at' => now(),
                'error' => null,
            ]);

            Log::channel('books')->info('export_completed', [
                'book_id' => $export->book_id,
                'export_id' => $export->id,
                'pages' => $result['pages'],
            ]);
        } catch (Throwable $e) {
            $export->update([
                'status' => 'failed',
                'error' => mb_substr($e->getMessage(), 0, 2000),
            ]);
            $jobRow->update([
                'status' => 'failed',
                'finished_at' => now(),
                'error' => mb_substr($e->getMessage(), 0, 2000),
            ]);

            Log::channel('books')->info('export_failed', [
                'book_id' => $export->book_id,
                'export_id' => $export->id,
                'error' => $e->getMessage(),
            ]);

            // El manuscrito y la versión se conservan; el usuario puede reintentar.
        }
    }
}
