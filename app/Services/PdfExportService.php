<?php

namespace App\Services;

use App\Models\Book;
use Illuminate\Support\Facades\Log;
use Mpdf\HTMLParserMode;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use RuntimeException;

/**
 * Genera el PDF con mPDF a partir del HTML de BookRenderService (§4, §11, §12).
 * Portada y preliminares sin numeración; el cuerpo numera desde 1 (§11).
 */
class PdfExportService
{
    public function __construct(
        private readonly BookRenderService $renderer,
        private readonly TemplateService $templates,
    ) {
    }

    /**
     * @return array{bytes: string, pages: int}
     */
    public function render(Book $book): array
    {
        $rendered = $this->renderer->render($book, 'mpdf');
        $config = $rendered['config'];
        $template = $rendered['template'];

        $pageSize = $this->pageSizeMm($template, $config['page_size'] ?? '6x9');
        $font = $this->resolveFont($template, $config['font_family'] ?? 'dejavuserif');

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => $pageSize,
            'margin_top' => (float) ($config['margin_top'] ?? 20),
            'margin_bottom' => (float) ($config['margin_bottom'] ?? 20),
            'margin_left' => (float) ($config['margin_outer'] ?? 18),
            'margin_right' => (float) ($config['margin_inner'] ?? 18),
            'margin_header' => 8,
            'margin_footer' => 8,
            'default_font' => $font,
            'default_font_size' => (int) ($config['font_size'] ?? 11),
        ]);

        // CSS de la plantilla (dialecto mPDF)
        $mpdf->WriteHtml($rendered['css'], HTMLParserMode::HEADER_CSS);

        // Portada: sin cabecera ni pie
        $mpdf->SetHTMLHeader('');
        $mpdf->SetHTMLFooter('');
        $mpdf->WriteHtml($rendered['cover'], HTMLParserMode::HTML_BODY);

        // Preliminares: copyright + TOC (mPDF rellena los números tras paginar)
        if ($rendered['front'] !== '') {
            $mpdf->WriteHtml($rendered['front'], HTMLParserMode::HTML_BODY);
        }

        // Cuerpo: numeración arábiga desde 1, cabecera/pie configurables
        $header = '';
        if (!empty($config['header_enabled'])) {
            $header = '<div style="text-align:center; font-size:9pt;">' . e($book->title) . '</div>';
        }
        $footer = '';
        if (!empty($config['show_page_numbers']) && !empty($config['footer_enabled'])) {
            $footer = '<div style="text-align:center; font-size:9pt;">{PAGENO}</div>';
        }
        $mpdf->SetHTMLHeader($header);
        $mpdf->SetHTMLFooter($footer);

        $body = '<pagebreak resetpagenum="1" pagenumstyle="1" suppress="off" />' . $rendered['body'];
        $mpdf->WriteHtml($body, HTMLParserMode::HTML_BODY);

        // Sección final (agradecimientos, nota del autor…)
        if ($rendered['back'] !== '') {
            $mpdf->WriteHtml('<pagebreak />' . $rendered['back'], HTMLParserMode::HTML_BODY);
        }

        $pages = $mpdf->page;

        return ['bytes' => $mpdf->Output('', Destination::STRING_RETURN), 'pages' => $pages];
    }

    public function renderToFile(Book $book, string $absolutePath): array
    {
        $result = $this->render($book);
        if (file_put_contents($absolutePath, $result['bytes']) === false) {
            throw new RuntimeException("No se pudo escribir el PDF en {$absolutePath}");
        }

        Log::channel('books')->info('pdf_rendered', [
            'book_id' => $book->id,
            'pages' => $result['pages'],
            'bytes' => strlen($result['bytes']),
        ]);

        return $result;
    }

    /**
     * @return array<float, float> [ancho, alto] en mm
     */
    private function pageSizeMm(array $template, string $size): array
    {
        $sizes = $template['page_sizes'] ?? [];
        if (isset($sizes[$size]) && is_array($sizes[$size])) {
            return [(float) $sizes[$size][0], (float) $sizes[$size][1]];
        }

        return $sizes['6x9'] ?? [152.4, 228.6];
    }

    private function resolveFont(array $template, string $font): string
    {
        foreach ($template['fonts'] ?? [] as $f) {
            if (($f['id'] ?? null) === $font) {
                return $font;
            }
        }

        return 'dejavuserif';
    }
}
