<?php
// Verificación end-to-end del §19 con el pipeline real (fuera de tests):
// importa el manuscrito de demo, genera el PDF y valida su estructura.
// Uso: php scripts/verificar_demo.php  (desde la raíz del proyecto, con BD migrada)
use App\Models\User;
use App\Services\BookImportService;
use App\Services\PdfExportService;
use Illuminate\Http\UploadedFile;

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// 1. Importar manuscrito de demo con usuario real (idempotente)
$user = User::updateOrCreate(
    ['email' => 'demo@local.test'],
    ['name' => 'Demo', 'password' => bcrypt('password1234')],
);

$file = new UploadedFile(
    __DIR__ . '/demo_manuscrito.md',
    'demo_manuscrito.md',
    'text/plain',
    null,
    true, // modo test: usa el archivo existente en disco
);

$book = app(BookImportService::class)->importUploadedFile($user->id, $file);

echo "=== IMPORTACIÓN ===\n";
echo "Título: {$book->title}\n";
echo "Capítulos: {$book->chapters()->count()}\n";
foreach ($book->chapters()->orderBy('sort_order')->get() as $c) {
    echo "  - " . ($c->title ?? '(sin título)') . " (num={$c->number}, bloques={$c->blocks()->count()})\n";
}

// 2. Exportar PDF
$pdf = app(PdfExportService::class);
$result = $pdf->render($book);
$pdfPath = storage_path('app/demo_export.pdf');
file_put_contents($pdfPath, $result['bytes']);
$bytes = $result['bytes'];

// 3. Validación post-generación (§19)
echo "\n=== VALIDACIÓN PDF ===\n";
echo "Existe: " . (file_exists($pdfPath) ? 'SÍ' : 'NO') . "\n";
echo "Cabecera válida: " . (str_starts_with($bytes, '%PDF-1.') ? 'SÍ' : 'NO') . "\n";
echo "EOF presente: " . (str_contains($bytes, '%%EOF') ? 'SÍ' : 'NO') . "\n";
echo "Tamaño: " . number_format(strlen($bytes) / 1024, 1) . " KB\n";
echo "Páginas: {$result['pages']}\n";

preg_match('/\/MediaBox\s*\[\s*0 0 ([\d.]+) ([\d.]+)\s*\]/', $bytes, $m);
if ($m) {
    $w = (float) $m[1] / 72; // puntos → pulgadas
    $h = (float) $m[2] / 72;
    echo sprintf("Tamaño de página: %.2f x %.2f in (esperado 6x9)\n", $w, $h);
    echo "Tamaño correcto: " . ((abs($w - 6) < 0.05 && abs($h - 9) < 0.05) ? 'SÍ' : 'NO') . "\n";
} else {
    echo "Tamaño de página: NO DETECTADO\n";
}

echo "Font embebida (DejaVuSerif): " . (str_contains($bytes, 'DejaVuSerif') ? 'SÍ' : 'NO') . "\n";
