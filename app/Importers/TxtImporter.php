<?php

namespace App\Importers;

use App\DTOs\BlockData;
use App\DTOs\BookDocument;
use App\DTOs\ChapterData;
use App\DTOs\ChapterCandidate;
use App\DTOs\ImportWarning;
use App\Services\BookStructureAnalyzer;

/**
 * Importador TXT — analizador heurístico (§7, §18).
 * Señales: CAPÍTULO 1 / CAPITULO I / Chapter 1 / Capítulo uno, aislamiento,
 * mayúsculas, longitud. Nunca adivina en silencio: los candidatos ambiguos
 * (60–84) se proponen marcados para confirmación (§8).
 */
class TxtImporter implements BookImporter
{
    public function __construct(
        private readonly BookStructureAnalyzer $analyzer = new BookStructureAnalyzer(),
    ) {
    }

    public function parse(string $content, string $originalName): BookDocument
    {
        $lines = preg_split('/\n/', $content);
        $warnings = [];

        $candidates = $this->analyzer->findChapterCandidates($lines);

        if ($candidates === []) {
            return $this->parseWithoutChapters($lines, $originalName);
        }

        $title = null;
        $titleProposal = $this->analyzer->proposeTitle($lines, $candidates[0]->lineIndex);
        if ($titleProposal !== null) {
            [$title, $titleScore] = $titleProposal;
            $warnings[] = ImportWarning::info(
                "Título propuesto: «{$title}» (confianza {$titleScore}%). Confírmalo en la revisión.",
                ['score' => $titleScore],
            );
        } else {
            $title = pathinfo($originalName, PATHINFO_FILENAME);
            $warnings[] = ImportWarning::info(
                "No se pudo proponer un título con confianza. Se usó el nombre del archivo: «{$title}».",
            );
        }

        $chapters = [];
        $number = 0;

        foreach ($candidates as $index => $candidate) {
            $nextCandidateIndex = $index + 1 < count($candidates)
                ? $candidates[$index + 1]->lineIndex
                : count($lines);

            // Rango de líneas de contenido de este capítulo
            $contentStart = $this->firstNonBlankAfter($lines, $candidate->lineIndex);
            $contentEnd = $nextCandidateIndex;

            $chapterNumber = $candidate->number;
            $chapterTitle = $candidate->inlineTitle;

            // Marcador puro ("CAPITULO 1") → el título suele venir en la línea siguiente,
            // pero solo si está aislada (línea en blanco después): una línea corta
            // seguida de texto es un párrafo partido, no un título.
            $consumedSubtitleLine = null;
            if ($candidate->pureMarker && $contentStart !== -1 && $contentStart < $contentEnd) {
                $nextLine = trim($lines[$contentStart]);
                $followedByBlank = ($contentStart + 1 >= count($lines)) || trim($lines[$contentStart + 1]) === '';
                $isSubtitle = mb_strlen($nextLine) < 80
                    && !preg_match('/[.!?…]\s*$/u', $nextLine)
                    && $followedByBlank;
                if ($isSubtitle) {
                    $chapterTitle = $nextLine;
                    $consumedSubtitleLine = $contentStart;
                    $contentStart = $this->firstNonBlankAfter($lines, $contentStart);
                }
            }

            // Texto huérfano antes del primer capítulo (más allá del título propuesto)
            if ($index === 0) {
                $preContent = $this->collectRange($lines, 0, $candidate->lineIndex, [$titleProposal[0] ?? null]);
                if ($preContent !== []) {
                    $chapters[] = new ChapterData(
                        null, null, 'body',
                        [new BlockData('paragraph', implode(' ', $preContent))],
                        null, true,
                    );
                    $warnings[] = ImportWarning::warning(
                        'Se conservó el texto previo al primer capítulo como una sección sin título. Revísala.',
                    );
                }
            }

            $blocks = [];

            $paragraphs = $this->collectParagraphs($lines, $contentStart, $contentEnd);
            foreach ($paragraphs as $p) {
                $blocks[] = new BlockData('paragraph', $p);
            }

            $needsConfirmation = $candidate->isAmbiguous();
            if ($needsConfirmation) {
                $warnings[] = ImportWarning::warning(
                    "Capítulo detectado con confianza {$candidate->score}%: «{$candidate->line}». Confírmalo o conviértelo de nuevo en texto.",
                    [
                        'line' => $candidate->lineIndex + 1,
                        'score' => $candidate->score,
                        'raw' => $candidate->line,
                    ],
                );
            }

            $chapters[] = new ChapterData(
                $chapterNumber,
                $chapterTitle,
                'body',
                $blocks,
                $candidate->score,
                $needsConfirmation,
            );
        }

        $numbered = 0;
        foreach ($chapters as $chapter) {
            if ($chapter->number !== null) {
                $numbered++;
            }
        }

        $warnings[] = ImportWarning::info(
            count($chapters) . ' capítulos detectados (' . $numbered . ' numerados).',
        );

        return new BookDocument($title, null, null, 'es', [], $chapters, [], $warnings);
    }

    private function parseWithoutChapters(array $lines, string $originalName): BookDocument
    {
        $warnings[] = ImportWarning::warning(
            'No se detectaron marcadores de capítulo. Todo el texto se colocó en un único capítulo sin título.',
        );

        $paragraphs = $this->collectParagraphs($lines, 0, count($lines));
        $blocks = array_map(fn (string $p) => new BlockData('paragraph', $p), $paragraphs);
        $chapter = new ChapterData(null, null, 'body', $blocks, null, true);

        $title = pathinfo($originalName, PATHINFO_FILENAME);

        return new BookDocument($title, null, null, 'es', [], [$chapter], [], $warnings);
    }

    /**
     * Une líneas dentro de un párrafo (manuscrito con saltos duros) y separa párrafos por líneas en blanco.
     *
     * @return array<int, string>
     */
    private function collectParagraphs(array $lines, int $start, int $end): array
    {
        $paragraphs = [];
        $current = [];

        for ($i = max(0, $start); $i < $end && $i < count($lines); $i++) {
            $line = trim($lines[$i]);
            if ($line === '') {
                if ($current !== []) {
                    $paragraphs[] = implode(' ', $current);
                    $current = [];
                }
                continue;
            }
            $current[] = $line;
        }

        if ($current !== []) {
            $paragraphs[] = implode(' ', $current);
        }

        return $paragraphs;
    }

    /**
     * @return array<int, string> líneas del rango (para texto huérfano)
     */
    private function collectRange(array $lines, int $start, int $end, array $exclude): array
    {
        $paragraphs = [];
        $current = [];

        for ($i = $start; $i < $end && $i < count($lines); $i++) {
            $line = trim($lines[$i]);
            if ($line === '' || in_array($line, $exclude, true)) {
                if ($current !== []) {
                    $paragraphs[] = implode(' ', $current);
                    $current = [];
                }
                continue;
            }
            $current[] = $line;
        }
        if ($current !== []) {
            $paragraphs[] = implode(' ', $current);
        }

        return $paragraphs;
    }

    private function firstNonBlankAfter(array $lines, int $index): int
    {
        for ($i = $index + 1; $i < count($lines); $i++) {
            if (trim($lines[$i]) !== '') {
                return $i;
            }
        }

        return -1;
    }
}
