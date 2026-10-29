<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookVersion extends Model
{
    protected $fillable = [
        'book_id', 'label', 'format', 'original_path',
        'original_name', 'snapshot', 'warnings', 'normalization',
    ];

    protected function casts(): array
    {
        return [
            'snapshot' => 'array',
            'warnings' => 'array',
            'normalization' => 'array',
        ];
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }
}
