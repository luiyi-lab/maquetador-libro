<?php

namespace App\DTOs;

/**
 * Bloque semántico del modelo interno (§6 AGENTS.md).
 * El contenido se guarda como texto fuente (markdown plano); el HTML se genera al renderizar.
 */
readonly class BlockData
{
    public function __construct(
        public string $type,
        public string $content = '',
        public array $meta = [],
        public ?int $confidence = null,
        public bool $needsConfirmation = false,
    ) {
    }

    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'content' => $this->content,
            'meta' => $this->meta,
            'confidence' => $this->confidence,
            'needs_confirmation' => $this->needsConfirmation,
        ];
    }
}
