<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['term', 'definition', 'is_key', 'source_page'])]
class Card extends Model
{
    public function deck(): BelongsTo
    {
        return $this->belongsTo(Deck::class);
    }

    protected function casts(): array
    {
        return ['is_key' => 'boolean',
            'source_page' => 'integer',
        ];
    }
}
