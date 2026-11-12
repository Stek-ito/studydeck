<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['original_name', 'path', 'sha256', 'status', 'pages', 'tokens_used'])]
class Document extends Model
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function deck(): HasOne
    {
        return $this->hasOne(Deck::class);
    }

    protected function casts(): array
    {
        return [
            'pages' => 'integer',
            'tokens_used' => 'integer',
        ];
    }
}
