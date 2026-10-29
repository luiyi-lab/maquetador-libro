<?php

namespace App\Services;

use App\Models\Book;
use App\Models\BookBlock;
use App\Models\Chapter;
use League\CommonMark\GithubFlavoredMarkdownConverter;

/**
 * Convierte el modelo interno (Book + Chapters + Blocks) en HTML semántico
 * con la plantilla elegida (§11). Modo 'mpdf' para el PDF real; modo 'html'
 * para depuración. El mismo pipeline alimenta preview y exportación.
 */
class BookRenderService
{
    public function __construct(
        private readonly TemplateService $templates,
    ) {
    }

    /**
     * @return array{css: string, cover: string, front: string, body: string, back: string, meta_title: string, language: string, config: array, template: array}
     */
    public function render(Book $book, string $mode = 'mpdf'): array
    {
        $config = $this->templates->resolveDesignConfig($book);
        $template = $this->templates->getTemplate($book->template_slug);
        $css = $this->templates->renderCss($book->template_slug, $config);

        $cover = $this->renderCover($book);
        $front = $this->renderFront($book, $config, $mode);
        $body = $this->renderSectionChapters($book, 'body', $mode);
        $back = $this->renderSectionChapters($book, 'back', $mode);

        return [
            'css' => $css,
            'cover' => $cover,
            'front' => $front,
            'body' => $body,
            'back' => $back,
            'meta_title' => $book->title,
            'language' => $book->language,
            'config' => $config,
            'template' => $template,
        ];
    }

    public function renderFullHtml(Book $book): string
    {
        $rendered = $this->render($book, 'html');
        $content = $rendered['cover'] . $rendered['front'] . $rendered['body'] . $rendered['back'];

        return strtr($rendered['template']['wrapper'], [
            '{{style}}' => $rendered['css'],
            '{{content}}' => $content,
            '{{meta_title}}' => e($rendered['meta_title']),
            '{{language}}' => e($rendered['language']),
        ]);
    }

    private function renderCover(Book $book): string
    {
        $html = '<div class="title-page">';
        $html .= '<p class="title-book">' . e($book->title) . '</p>';
        if ($book->subtitle) {
            $html .= '<p class="title-subtitle">' . e($book->subtitle) . '</p>';
        }
        if ($book->author) {
            $html .= '<p class="title-author">' . e($book->author) . '</p>';
        }
        $html .= '</div>';

        return $html;
    }

    private function renderFront(Book $book, array $config, string $mode): string
    {
        $html = '';

        $year = now()->format('Y');
        $html .= '<div class="copyright">';
        $html .= '<p class="no-indent">© ' . $year . ($book->author ? ' ' . e($book->author) : '') . '</p>';
        $html .= '<p class="no-indent">Todos los derechos reservados.</p>';
        $html .= '</div>';

        // Preliminares del manuscrito (epígrafes, dedicatoria) en páginas propias
        $frontChapters = $book->chapters()->where('section', 'front')->orderBy('sort_order')->get();
        if ($frontChapters->isNotEmpty()) {
            $html .= $mode === 'mpdf'
                ? '<pagebreak />'
                : '<div style="page-break-after: always;"></div>';
            foreach ($frontChapters as $chapter) {
                foreach ($chapter->blocks()->orderBy('sort_order')->get() as $block) {
                    $html .= $this->renderBlock($block, $mode, false);
                }
            }
        }

        if (!empty($config['toc_enabled'])) {
            $html .= $this->renderToc($book);
        }

        return $html;
    }

    private function renderToc(Book $book): string
    {
        // mPDF imprime el TOC en <tocpagebreak> y rellena los números de página
        // tras la composición final (§11: nunca escritos a mano).
        return '<tocpagebreak paging="on" links="on" toc-prehtml="&lt;h1 class=&quot;toc-title&quot;&gt;Índice&lt;/h1&gt;" />';
    }

    private function renderSectionChapters(Book $book, string $section, string $mode): string
    {
        $chapters = $book->chapters()->where('section', $section)->orderBy('sort_order')->get();
        $html = '';
        $first = true;

        foreach ($chapters as $chapter) {
            if (!$first) {
                if ($mode === 'mpdf') {
                    $html .= '<pagebreak />';
                } else {
                    $html .= '<div style="page-break-after: always;"></div>';
                }
            }
            $html .= $this->renderChapter($chapter, $book, $mode);
            $first = false;
        }

        return $html;
    }

    private function renderChapter(Chapter $chapter, Book $book, string $mode): string
    {
        $config = $this->templates->resolveDesignConfig($book);
        $html = '<article class="chapter">';

        if (!empty($config['toc_enabled'])) {
            $label = trim(($chapter->number !== null ? $chapter->number . '. ' : '') . ($chapter->title ?? 'Capítulo'));
            $html .= '<tocentry content="' . e($label) . '" level="0" />';
        }

        if ($chapter->number !== null) {
            $html .= '<p class="chapter-number">Capítulo ' . $chapter->number . '</p>';
        }
        if ($chapter->title) {
            $html .= '<h1 class="chapter-title">' . e($chapter->title) . '</h1>';
        }

        $blocks = $chapter->blocks()->orderBy('sort_order')->get();
        $firstParagraph = true;
        foreach ($blocks as $block) {
            $html .= $this->renderBlock($block, $mode, $firstParagraph);
            if ($block->type === 'paragraph') {
                $firstParagraph = false;
            }
        }

        $html .= '</article>';

        return $html;
    }

    private function renderBlock(BookBlock $block, string $mode, bool $isFirstParagraph): string
    {
        $content = (string) $block->content;

        switch ($block->type) {
            case 'paragraph':
                $class = $isFirstParagraph ? 'first' : '';
                return '<p class="' . $class . '">' . $this->inline($content) . '</p>';

            case 'heading':
                $level = min(3, max(2, (int) ($block->meta['level'] ?? 2)));
                return '<h' . $level . '>' . $this->inline($content) . '</h' . $level . '>';

            case 'epigraph':
                $html = '<div class="epigraph"><p>' . $this->inline($content) . '</p>';
                $source = trim(($block->meta['author'] ?? '') . (($block->meta['author'] ?? '') && ($block->meta['source'] ?? '') ? ', ' : '') . ($block->meta['source'] ?? ''));
                if ($source !== '') {
                    $html .= '<p class="epigraph-source">— ' . e($source) . '</p>';
                }
                return $html . '</div>';

            case 'quote':
                $lines = preg_split('/\n/', $content) ?: [];
                $inner = implode('', array_map(fn ($l) => '<p>' . $this->inline(trim($l)) . '</p>', array_filter(array_map('trim', $lines))));
                return '<blockquote>' . ($inner !== '' ? $inner : '<p></p>') . '</blockquote>';

            case 'separator':
                return '<p class="separator">·&nbsp;&nbsp;·&nbsp;&nbsp;·</p>';

            case 'page_break':
                return $mode === 'mpdf' ? '<pagebreak />' : '<div style="page-break-after: always;"></div>';

            case 'list':
                return $this->renderList($block);

            case 'image':
                $src = (string) ($block->meta['src'] ?? '');
                if (!preg_match('#^https?://#i', $src)) {
                    // Solo imágenes accesibles vía URL; referencias locales se omiten con nota
                    return '<p class="no-indent">[Imagen no disponible: ' . e($src) . ']</p>';
                }
                $alt = (string) ($block->meta['alt'] ?? '');
                return '<figure><img src="' . e($src) . '" alt="' . e($alt) . '" /></figure>';

            case 'caption':
                return '<figcaption>' . $this->inline($content) . '</figcaption>';

            case 'note':
                return '<p class="no-indent"><sup>' . $this->inline($content) . '</sup></p>';

            case 'section_break':
                return $mode === 'mpdf' ? '<pagebreak />' : '<div style="page-break-after: always;"></div>';

            default:
                return '<p>' . $this->inline($content) . '</p>';
        }
    }

    private function renderList(BookBlock $block): string
    {
        $lines = array_filter(array_map('trim', preg_split('/\n/', (string) $block->content) ?: []));
        $ordered = (bool) ($block->meta['ordered'] ?? false);
        $items = '';
        foreach ($lines as $line) {
            $line = preg_replace('/^([\-\*+]|\d+\.)\s+/', '', $line) ?? $line;
            $items .= '<li>' . $this->inline($line) . '</li>';
        }

        return $ordered ? '<ol>' . $items . '</ol>' : '<ul>' . $items . '</ul>';
    }

    /**
     * Inline markdown determinista (cursiva, negrita, enlaces) vía league/commonmark,
     * escapando HTML crudo del usuario.
     */
    public function inline(string $text): string
    {
        $converter = new GithubFlavoredMarkdownConverter([
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
        ]);

        $html = trim($converter->convert($text)->getContent());

        // El conversor envuelve en <p>…</p>; se extrae el interior para no anidar
        if (preg_match('/^<p>(.*)<\/p>$/s', $html, $m)) {
            return $m[1];
        }

        return $html;
    }
}
