<?php

namespace App\DTOs;

/**
 * Advertencia del proceso de importación (§15: INFO / WARNING / ERROR).
 * Las "actionable" contienen un candidato ambiguo que el usuario puede
 * aceptar o rechazar en la pantalla de revisión (regla de oro §8).
 */
readonly class ImportWarning
{
    public const LEVEL_INFO = 'info';
    public const LEVEL_WARNING = 'warning';
    public const LEVEL_ERROR = 'error';

    public function __construct(
        public string $level,
        public string $message,
        public array $context = [],
    ) {
    }

    public static function info(string $message, array $context = []): self
    {
        return new self(self::LEVEL_INFO, $message, $context);
    }

    public static function warning(string $message, array $context = []): self
    {
        return new self(self::LEVEL_WARNING, $message, $context);
    }

    public static function error(string $message, array $context = []): self
    {
        return new self(self::LEVEL_ERROR, $message, $context);
    }

    public function toArray(): array
    {
        return [
            'level' => $this->level,
            'message' => $this->message,
            'context' => $this->context,
        ];
    }
}
