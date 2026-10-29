<?php

namespace App\DTOs;

/**
 * Línea candidata a ser capítulo, con su puntuación de confianza (§8).
 * La confianza es el score normalizado 0-100 (puntos = puntos porcentuales).
 */
readonly class ChapterCandidate
{
    public function __construct(
        public int $lineIndex,
        public string $line,
        public int $score,
        public ?int $number,
        public ?string $inlineTitle,
        public bool $pureMarker,
    ) {
    }

    public function isAutoAccepted(): bool
    {
        return $this->score >= BookStructureThresholds::AUTO_ACCEPT;
    }

    public function isAmbiguous(): bool
    {
        return $this->score >= BookStructureThresholds::WARN_MIN
            && $this->score < BookStructureThresholds::AUTO_ACCEPT;
    }
}
