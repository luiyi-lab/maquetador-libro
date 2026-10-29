<?php

namespace App\Services;

use App\DTOs\BookStructureThresholds;
use App\DTOs\ChapterCandidate;

/**
 * Detección estructural con puntuación de confianza (§8 AGENTS.md).
 * Regla de oro: nunca adivinar en silencio — los candidatos ambiguos
 * (60–84) se proponen marcados para confirmación del usuario.
 *
 * Señales y pesos (ajustables mediante pruebas):
 *   ¿Contiene "CAPÍTULO"/"Chapter"?  +40
 *   ¿Tiene número (arábigo/romano/palabra)? +20
 *   ¿Aislado (líneas en blanco antes y después)? +15
 *   ¿Marcador puro (solo palabra+número)? +15
 *   ¿Línea en mayúsculas? +10
 *   ¿Línea corta (<60 caracteres)? +5
 */
class BookStructureAnalyzer
{
    public const W_KEYWORD = 40;
    public const W_NUMBER = 20;
    public const W_ISOLATED = 15;
    public const W_PURE_MARKER = 15;
    public const W_UPPERCASE = 10;
    public const W_SHORT = 5;

    /** Palabra clave de capítulo (español/inglés) al inicio de la línea */
    private const KEYWORD_REGEX = '/^[\s\-\*—–·>#]*?(cap[íi]tulo|chapter|cap\.)\s*/iu';

    /** Números en palabra (español) admitidos tras la palabra clave */
    private const WORD_NUMBERS = [
        'uno' => 1, 'un' => 1, 'dos' => 2, 'tres' => 3, 'cuatro' => 4, 'cinco' => 5,
        'seis' => 6, 'siete' => 7, 'ocho' => 8, 'nueve' => 9, 'diez' => 10,
        'once' => 11, 'doce' => 12, 'trece' => 13, 'catorce' => 14, 'quince' => 15,
        'dieciséis' => 16, 'dieciseis' => 16, 'diecisiete' => 17, 'dieciocho' => 18,
        'diecinueve' => 19, 'veinte' => 20, 'veintiuno' => 21, 'veintidós' => 22,
        'veintidos' => 22, 'veintitrés' => 23, 'veintitres' => 23, 'veinticuatro' => 24,
        'veinticinco' => 25, 'veintiséis' => 26, 'veintiseis' => 26, 'veintisiete' => 27,
        'veintiocho' => 28, 'veintinueve' => 29, 'treinta' => 30, 'cuarenta' => 40,
        'cincuenta' => 50, 'sesenta' => 60, 'setenta' => 70, 'ochenta' => 80,
        'noventa' => 90, 'cien' => 100,
    ];

    /**
     * Recorre las líneas y devuelve todos los candidatos con score >= umbral de advertencia.
     *
     * @param array<int, string> $lines
     * @return array<int, ChapterCandidate>
     */
    public function findChapterCandidates(array $lines): array
    {
        $candidates = [];
        $total = count($lines);

        for ($i = 0; $i < $total; $i++) {
            $line = trim($lines[$i]);
            if ($line === '') {
                continue;
            }

            $parsed = $this->parseChapterLine($line);
            if ($parsed === null) {
                continue;
            }

            [$score, $number, $inlineTitle, $pureMarker] = $parsed;

            $prevBlank = $i === 0 || trim($lines[$i - 1]) === '';
            $nextBlank = $i === $total - 1 || trim($lines[$i + 1]) === '';
            if ($prevBlank && $nextBlank) {
                $score += self::W_ISOLATED;
            }

            if ($score >= BookStructureThresholds::WARN_MIN) {
                $candidates[] = new ChapterCandidate($i, $line, min(100, $score), $number, $inlineTitle, $pureMarker);
            }
        }

        return $candidates;
    }

    /**
     * Intenta interpretar una línea como marcador de capítulo.
     * Devuelve null si no lo es; si lo es, [score, número|null, título en línea|null, marcador puro].
     */
    private function parseChapterLine(string $line): ?array
    {
        if (!preg_match(self::KEYWORD_REGEX, $line, $m)) {
            return null;
        }

        $score = self::W_KEYWORD;
        $rest = mb_substr($line, mb_strlen($m[0]));

        // Número: arábigo, romano o palabra
        $number = null;
        if (preg_match('/^[\s\.\:\-\–\—·#]*(\d+)(?![\d])\s*[\.\:\-\–\—]?\s*/u', $rest, $n)) {
            $number = (int) $n[1];
            $rest = mb_substr($rest, mb_strlen($n[0]));
        } elseif (preg_match('/^[\s\.\:\-\–\—·#]*([IVXLCDM]+)\b[\.\:\-\–\—]?\s*/iu', $rest, $r)) {
            $value = $this->romanToInt($r[1]);
            if ($value !== null) {
                $number = $value;
                $rest = mb_substr($rest, mb_strlen($r[0]));
            }
        } elseif (preg_match('/^[\s\.\:\-\–\—·#]*([a-záéíóúñü]+)\b[\.\:\-\–\—]?\s*/iu', $rest, $w)) {
            $value = self::WORD_NUMBERS[mb_strtolower($w[1], 'UTF-8')] ?? null;
            if ($value !== null) {
                $number = $value;
                $rest = mb_substr($rest, mb_strlen($w[0]));
            }
        }

        if ($number !== null) {
            $score += self::W_NUMBER;
        }

        $inlineTitle = trim($rest, " \t\-\–\—·:.=");
        $pureMarker = $inlineTitle === '' || mb_strlen($inlineTitle) <= 3;
        if ($pureMarker) {
            $score += self::W_PURE_MARKER;
            $inlineTitle = '';
        } elseif (mb_strlen($inlineTitle) < 60) {
            $score += self::W_SHORT;
        }

        // Mayúsculas: al menos el 60% de las letras de la línea original
        $letters = preg_replace('/[^\p{L}]/u', '', $line) ?? '';
        if ($letters !== '' && mb_strtoupper($letters, 'UTF-8') === $letters) {
            $score += self::W_UPPERCASE;
        }

        return [$score, $number, $inlineTitle !== '' ? $inlineTitle : null, $pureMarker];
    }

    /**
     * Propuesta de título del libro: primera línea no vacía antes del primer capítulo.
     * Devuelve [texto, confianza] o null.
     *
     * @param array<int, string> $lines
     */
    public function proposeTitle(array $lines, int $firstChapterLineIndex): ?array
    {
        for ($i = 0; $i < $firstChapterLineIndex; $i++) {
            $line = trim($lines[$i]);
            if ($line === '') {
                continue;
            }
            if (mb_strlen($line) > 80) {
                return null;
            }
            $letters = preg_replace('/[^\p{L}]/u', '', $line) ?? '';
            $score = 70;
            if ($letters !== '' && mb_strtoupper($letters, 'UTF-8') === $letters) {
                $score = 90;
            }
            if ($i === 0) {
                $score += 5;
            }
            return [$line, min(100, $score)];
        }

        return null;
    }

    public function romanToInt(string $roman): ?int
    {
        $roman = strtoupper($roman);
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
