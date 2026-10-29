<?php

namespace App\Importers;

use App\DTOs\BookDocument;

interface BookImporter
{
    public function parse(string $content, string $originalName): BookDocument;
}
