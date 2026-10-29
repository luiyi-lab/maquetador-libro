<?php

namespace App\Services;

/**
 * Normalización TÉCNICA (§10 AGENTS.md): no creativa, reversible y registrada.
 * Nunca altera palabras, solo caracteres y espaciado problemáticos.
 * Cada operación se cuenta para quedar registrada en la versión del libro.
 */
class BookNormalizationService
{
    public function normalizeEncoding(string $raw): string
    {
        // Detecta codificación y fuerza UTF-8 (manuscritos TXT suelen venir en Windows-1252/Latin-1)
        $encoding = mb_detect_encoding($raw, ['UTF-8', 'Windows-1252', 'ISO-8859-1'], true);

        if ($encoding !== 'UTF-8' && $encoding !== false) {
            $raw = mb_convert_encoding($raw, 'UTF-8', $encoding);
        }

        return $raw;
    }

    /**
     * @return array{text: string, ops: array<string, int>} texto normalizado + contador de operaciones
     */
    public function normalize(string $raw): array
    {
        $ops = [
            'bom_removido' => 0,
            'caracteres_invisibles' => 0,
            'fines_de_linea' => 0,
            'tabulaciones' => 0,
            'espacios_duplicados' => 0,
            'saltos_excesivos' => 0,
            'espacios_finales' => 0,
        ];

        // BOM
        if (str_starts_with($raw, "\xEF\xBB\xBF")) {
            $raw = substr($raw, 3);
            $ops['bom_removido'] = 1;
        }

        // Caracteres invisibles: zero-width space, zero-width joiner/non-joiner, soft hyphen, BOM interno
        $before = $raw;
        $raw = str_replace(["\xE2\x80\x8B", "\xE2\x80\x8C", "\xE2\x80\x8D", "\xC2\xAD", "\xEF\xBB\xBF"], '', $raw);
        $ops['caracteres_invisibles'] = (strlen($before) - strlen($raw)) > 0 ? 1 : 0;

        // Fines de línea: CRLF y CR → LF
        $crlfCount = substr_count($raw, "\r\n") + substr_count(str_replace("\r\n", '', $raw), "\r");
        $raw = str_replace(["\r\n", "\r"], "\n", $raw);
        $ops['fines_de_linea'] = $crlfCount > 0 ? 1 : 0;

        // Tabulaciones → espacio
        if (str_contains($raw, "\t")) {
            $raw = str_replace("\t", ' ', $raw);
            $ops['tabulaciones'] = 1;
        }

        // Espacios no separables usados como espaciado de texto → espacio normal
        $raw = str_replace("\xC2\xA0", ' ', $raw);

        // Espacios finales de línea
        $before = $raw;
        $raw = preg_replace('/[ \t]+$/m', '', $raw);
        $ops['espacios_finales'] = $before !== $raw ? 1 : 0;

        // Espacios duplicados (fuera de sangrías de inicio de línea)
        $before = $raw;
        $raw = preg_replace('/(?<=\S) {2,}(?=\S)/', ' ', $raw);
        $ops['espacios_duplicados'] = $before !== $raw ? 1 : 0;

        // Saltos excesivos: 3+ líneas en blanco → 1 línea en blanco (separador de párrafo)
        $before = $raw;
        $raw = preg_replace("/\n{3,}/", "\n\n", $raw);
        $ops['saltos_excesivos'] = $before !== $raw ? 1 : 0;

        return ['text' => $raw, 'ops' => $ops];
    }
}
