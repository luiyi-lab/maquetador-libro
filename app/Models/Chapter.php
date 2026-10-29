<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Chapter extends Model
{
    public const SECTIONS = ['front', 'body', 'back'];

    protected $fillable = [
        'book_id', 'section', 'number', 'title',
        'sort_order', 'confidence', 'needs_confirmation',
    ];

    protected function casts(): array
    {
        return [
            'needs_confirmation' => 'boolean',
        ];
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(BookBlock::class)->orderBy('sort_order');
    }
}
