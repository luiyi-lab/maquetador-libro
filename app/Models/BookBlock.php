<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookBlock extends Model
{
    public const TYPES = [
        'paragraph', 'heading', 'epigraph', 'quote', 'separator', 'image',
        'caption', 'list', 'table', 'page_break', 'section_break', 'note',
    ];

    protected $fillable = ['chapter_id', 'type', 'content', 'meta', 'sort_order'];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }

    public function chapter(): BelongsTo
    {
        return $this->belongsTo(Chapter::class);
    }
}
