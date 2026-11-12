<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['term', 'definition', 'is_key', 'source_page'])]
class Card extends Model
{
    use HasFactory;

    public function deck(): BelongsTo
    {
        return $this->belongsTo(Deck::class);
    }

    public function review(): HasOne
    {
        return $this->hasOne(CardReview::class);
    }

    #[Scope]
    protected function dueToday(Builder $query): void
    {
        $query->whereHas('review', function (Builder $q) {
            $q->whereDate('due_at', '<=', today());
        });
    }

    protected function casts(): array
    {
        return ['is_key' => 'boolean',
            'source_page' => 'integer',
        ];
    }
}
