<?php

namespace App\Importers;

use App\DTOs\BlockData;
use App\DTOs\BookDocument;
use App\DTOs\ChapterData;
use App\DTOs\ImportWarning;

/**
 * Importador Markdown — 100% determinista (§7).
 *
 * Mapeo de estructura:
 *   - El primer `#` es el título del libro.
 *   - `##` inicia un capítulo (extrae número si la línea trae "Capítulo N").
 *   - Un `#` adicional se interpreta como capítulo, marcado ambiguo (advertencia).
 *   - `###`+ → bloque heading dentro del capítulo.
 *
 * Bloques personalizados (fijados antes de escribir los tests del parser):
 *   :::capitulo num=1 titulo="La vida que parecía perfecta" ... :::
 *   :::epigrafe autor="X" fuente="Y" ... :::
 *   :::salto            → page_break
 *   :::separador        → separator
 */
class MarkdownImporter implements BookImporter
{
    private const CUSTOM_BLOCK_REGEX = '/^:::\s*([a-z]+)\s*(.*)$/u';

    public function parse(string $content, string $originalName): BookDocument
    {
        $lines = preg_split('/\n/', $content);
        $warnings = [];

        $title = null;
        $subtitle = null;
        $author = null;
        /** @var array<int, ChapterData> $chapters */
        $chapters = [];
        $currentBlocks = [];
        $currentChapter = null;
        $paragraph = [];

        $flushParagraph = function () use (&$paragraph, &$currentBlocks, &$currentChapter, &$chapters): void {
            if ($paragraph === []) {
                return;
            }
            $block = new BlockData('paragraph', implode(' ', $paragraph));
            $paragraph = [];
            if ($currentChapter !== null) {
                $currentBlocks[] = $block;
            } else {
                // Texto antes del primer capítulo: se conserva como capítulo sin título al inicio
                $chapters[] = new ChapterData(null, null, 'body', [$block], 100, false);
            }
        };

        $flushChapter = function () use (&$currentBlocks, &$currentChapter, &$chapters, $flushParagraph): void {
            $flushParagraph();
            if ($currentChapter !== null) {
                $chapters[] = $currentChapter->withBlocks($currentBlocks);
                $currentBlocks = [];
            }
            $currentChapter = null;
        };

        $startChapter = function (?int $number, ?string $title, int $confidence, bool $needsConfirmation) use ($flushChapter, &$currentChapter, &$chapters): void {
            $flushChapter();
            $currentChapter = new ChapterData($number, $title, 'body', [], $confidence, $needsConfirmation);
        };

        $parseAttrs = function (string $raw): array {
            $attrs = [];
            // Admite valores con y sin comillas: num=1 titulo="Mi título"
            if (preg_match_all('/([a-z_]+)\s*=\s*(?:"([^"]*)"|([^\s]+))/u', $raw, $m, PREG_SET_ORDER)) {
                foreach ($m as $attr) {
                    $attrs[$attr[1]] = $attr[2] !== '' ? $attr[2] : ($attr[3] ?? '');
                }
            }
            return $attrs;
        };

        $n = count($lines);
        $customBlock = null; // ['name' =>, 'attrs' =>, 'content' => []]
        for ($i = 0; $i < count($lines); $i++) {
            $line = rtrim($lines[$i]);

            // Dentro de un bloque personalizado: acumular hasta cerrar :::
            if ($customBlock !== null) {
                if (preg_match('/^:::\s*$/', $line)) {
                    $body = trim(implode("\n", $customBlock['content']));
                    $attrs = $customBlock['attrs'];
                    $flushParagraph();
                    switch ($customBlock['name']) {
                        case 'capitulo':
                            $startChapter(
                                isset($attrs['num']) && is_numeric($attrs['num']) ? (int) $attrs['num'] : null,
                                $attrs['titulo'] ?? null,
                                100,
                                false,
                            );
                            // El contenido del bloque se procesa con el parser normal,
                            // ya dentro del capítulo recién creado
                            if ($body !== '') {
                                array_splice($lines, $i + 1, 0, preg_split('/\n/', $body));
                            }
                            break;
                        case 'epigrafe':
                            $meta = array_filter([
                                'author' => $attrs['autor'] ?? null,
                                'source' => $attrs['fuente'] ?? null,
                            ]);
                            $block = new BlockData('epigraph', $body, $meta);
                            if ($currentChapter !== null) {
                                $currentBlocks[] = $block;
                            } else {
                                $chapters[] = new ChapterData(null, null, 'front', [$block], 100, false);
                            }
                            break;
                        case 'salto':
                            $currentBlocks[] = new BlockData('page_break');
                            break;
                        case 'separador':
                            $currentBlocks[] = new BlockData('separator');
                            break;
                        default:
                            $warnings[] = ImportWarning::warning(
                                "Bloque personalizado desconocido: :::{$customBlock['name']}. Se conservó como párrafo.",
                                ['line' => $i + 1],
                            );
                            $currentBlocks[] = new BlockData('paragraph', $body);
                    }
                    $customBlock = null;
                } else {
                    $customBlock['content'][] = $line;
                }
                continue;
            }

            // Apertura de bloque personalizado
            if (preg_match(self::CUSTOM_BLOCK_REGEX, $line, $m)) {
                $customBlock = [
                    'name' => mb_strtolower($m[1]),
                    'attrs' => $parseAttrs($m[2]),
                    'content' => [],
                ];
                continue;
            }

            // Línea vacía: fin de párrafo
            if (trim($line) === '') {
                $flushParagraph();
                continue;
            }

            // Encabezados
            if (preg_match('/^(#{1,6})\s+(.*)$/u', $line, $h)) {
                $level = strlen($h[1]);
                $text = trim($h[2]);
                $flushParagraph();

                if ($level === 1 && $title === null && $currentChapter === null && $chapters === []) {
                    $title = $text;
                    continue;
                }

                if ($level <= 2) {
                    // Capítulo: extraer "Capítulo N" si viene en el texto
                    $number = null;
                    $chapterTitle = $text;
                    if (preg_match('/^(?:cap[íi]tulo|chapter)\s+([\dIVXivx]+|uno|dos|tres|cuatro|cinco|seis|siete|ocho|nueve|diez)\s*[\.\:\-\–\—]?\s*(.*)$/iu', $text, $c)) {
                        $chapterTitle = trim($c[2]);
                        $wordNumbers = ['uno' => 1, 'dos' => 2, 'tres' => 3, 'cuatro' => 4, 'cinco' => 5,
                            'seis' => 6, 'siete' => 7, 'ocho' => 8, 'nueve' => 9, 'diez' => 10];
                        $numToken = $c[1];
                        if (ctype_digit($numToken)) {
                            $number = (int) $numToken;
                        } elseif (isset($wordNumbers[mb_strtolower($numToken)])) {
                            $number = $wordNumbers[mb_strtolower($numToken)];
                        } else {
                            $number = $this->romanToInt(strtoupper($numToken));
                        }
                        if ($chapterTitle === '') {
                            $chapterTitle = null;
                        }
                    }

                    $reusedH1 = $level === 1;
                    $startChapter($number, $chapterTitle, $reusedH1 ? 70 : 100, $reusedH1);
                    if ($reusedH1) {
                        $warnings[] = ImportWarning::warning(
                            "El encabezado # «{$text}» se interpretó como capítulo (el título del libro ya fue asignado). Confírmalo en la revisión.",
                            ['line' => $i + 1, 'score' => 70],
                        );
                    }
                    continue;
                }

                // H3+ → heading dentro del capítulo
                if ($currentChapter === null) {
                    $startChapter(null, null, 100, false);
                }
                $currentBlocks[] = new BlockData('heading', $text, ['level' => $level]);
                continue;
            }

            // Separador markdown
            if (preg_match('/^\s*([\-\*_])\1{2,}\s*$/', $line)) {
                $flushParagraph();
                $currentBlocks[] = new BlockData('separator');
                continue;
            }

            // Cita
            if (preg_match('/^>\s?(.*)$/', $line, $q)) {
                $flushParagraph();
                $quoteLines = [$q[1]];
                while ($i + 1 < $n && preg_match('/^>\s?(.*)$/', rtrim($lines[$i + 1]), $q2)) {
                    $quoteLines[] = $q2[1];
                    $i++;
                }
                $block = new BlockData('quote', implode("\n", $quoteLines));
                if ($currentChapter !== null) {
                    $currentBlocks[] = $block;
                } else {
                    $chapters[] = new ChapterData(null, null, 'body', [$block], 100, false);
                }
                continue;
            }

            // Imagen
            if (preg_match('/^!\[([^\]]*)\]\(([^)]+)\)\s*$/', $line, $img)) {
                $flushParagraph();
                $block = new BlockData('image', '', ['src' => $img[2], 'alt' => $img[1]]);
                if ($currentChapter !== null) {
                    $currentBlocks[] = $block;
                } else {
                    $chapters[] = new ChapterData(null, null, 'body', [$block], 100, false);
                }
                continue;
            }

            // Lista (acumular líneas contiguas)
            if (preg_match('/^\s*([\-\*+]|\d+\.)\s+/', $line)) {
                $flushParagraph();
                $listLines = [trim($line)];
                while ($i + 1 < $n && preg_match('/^\s*([\-\*+]|\d+\.)\s+/', trim($lines[$i + 1]))) {
                    $listLines[] = trim($lines[$i + 1]);
                    $i++;
                }
                $ordered = (bool) preg_match('/^\s*\d+\./', $listLines[0]);
                $block = new BlockData('list', implode("\n", $listLines), ['ordered' => $ordered]);
                if ($currentChapter !== null) {
                    $currentBlocks[] = $block;
                } else {
                    $chapters[] = new ChapterData(null, null, 'body', [$block], 100, false);
                }
                continue;
            }

            // Párrafo
            $paragraph[] = trim($line);
        }

        // Cerrar bloque personalizado sin cerrar (error)
        if ($customBlock !== null) {
            $warnings[] = ImportWarning::error(
                "El bloque :::{$customBlock['name']} no se cerró con :::. Su contenido se conservó como texto.",
            );
            $body = trim(implode("\n", $customBlock['content']));
            if ($body !== '') {
                $flushParagraph();
                $currentBlocks[] = new BlockData('paragraph', $body);
            }
        }

        $flushChapter();

        // Separar por secciones (§6: front matter, capítulos, back matter)
        $frontMatter = [];
        $bodyChapters = [];
        $backMatter = [];
        foreach ($chapters as $chapter) {
            match ($chapter->section) {
                'front' => $frontMatter[] = $chapter,
                'back' => $backMatter[] = $chapter,
                default => $bodyChapters[] = $chapter,
            };
        }

        // Título por defecto: nombre del archivo
        $fallbackTitle = null;
        if ($title === null) {
            $fallbackTitle = pathinfo($originalName, PATHINFO_FILENAME);
            $title = $fallbackTitle;
            $warnings[] = ImportWarning::info(
                "No se encontró un encabezado # para el título del libro. Se usó el nombre del archivo: «{$fallbackTitle}». Cámbialo si es necesario.",
            );
        }

        if ($author === null) {
            $warnings[] = ImportWarning::info(
                'No se detectó autor. Defínelo en la pantalla de revisión.',
            );
        }

        return new BookDocument($title, $subtitle, $author, 'es', $frontMatter, $bodyChapters, $backMatter, $warnings);
    }

    private function romanToInt(string $roman): ?int
    {
        if (!preg_match('/^[IVXLCDM]+$/', $roman)) {
            return null;
        }
        $values = ['I' => 1, 'V' => 5, 'X' => 10, 'L' => 50, 'C' => 100, 'D' => 500, 'M' => 1000];
        $total = 0;
        $len = strlen($roman);
        for ($i = 0; $i < $len; $i++) {
            $v = $values[$roman[$i]];
            if ($i + 1 < $len && $v < $values[$roman[$i + 1]]) {
                $total -= $v;
            } else {
                $total += $v;
            }
        }

        return ($total > 0 && $total <= 3000) ? $total : null;
    }
}
