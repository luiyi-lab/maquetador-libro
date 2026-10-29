<?php

namespace App\DTOs;

readonly class ChapterData
{
    public function __construct(
        public ?int $number,
        public ?string $title,
        public string $section = 'body', // front | body | back
        public array $blocks = [],
        public ?int $confidence = null,
        public bool $needsConfirmation = false,
    ) {
    }

    public function withBlocks(array $blocks): self
    {
        return new self(
            $this->number, $this->title, $this->section,
            $blocks, $this->confidence, $this->needsConfirmation,
        );
    }

    public function toArray(): array
    {
        return [
            'number' => $this->number,
            'title' => $this->title,
            'section' => $this->section,
            'blocks' => array_map(fn (BlockData $b) => $b->toArray(), $this->blocks),
            'confidence' => $this->confidence,
            'needs_confirmation' => $this->needsConfirmation,
        ];
    }
}
