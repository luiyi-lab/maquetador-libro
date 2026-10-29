<?php

namespace App\DTOs;

/**
 * Representación común de un libro, independiente del formato de origen (§6).
 * Todos los importadores producen esta estructura.
 */
readonly class BookDocument
{
    /**
     * @param array<int, ChapterData> $frontMatter
     * @param array<int, ChapterData> $chapters
     * @param array<int, ChapterData> $backMatter
     * @param array<int, ImportWarning> $warnings
     */
    public function __construct(
        public string $title,
        public ?string $subtitle = null,
        public ?string $author = null,
        public string $language = 'es',
        public array $frontMatter = [],
        public array $chapters = [],
        public array $backMatter = [],
        public array $warnings = [],
    ) {
    }

    public function allChapters(): array
    {
        return [...$this->frontMatter, ...$this->chapters, ...$this->backMatter];
    }

    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'author' => $this->author,
            'language' => $this->language,
            'front_matter' => array_map(fn (ChapterData $c) => $c->toArray(), $this->frontMatter),
            'chapters' => array_map(fn (ChapterData $c) => $c->toArray(), $this->chapters),
            'back_matter' => array_map(fn (ChapterData $c) => $c->toArray(), $this->backMatter),
            'warnings' => array_map(fn (ImportWarning $w) => $w->toArray(), $this->warnings),
        ];
    }
}
