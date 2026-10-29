<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookAsset extends Model
{
    protected $fillable = ['book_id', 'path', 'original_name', 'mime', 'size'];

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }
}
