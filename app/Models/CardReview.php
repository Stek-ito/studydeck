<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['box', 'due_at', 'last_reviewed_at', 'own_explanation', 'doubt'])]
class CardReview extends Model
{
    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }

    protected function casts(): array
    {
        return [
            'box' => 'integer',
            'due_at' => 'date',
            'last_reviewed_at' => 'datetime',
        ];
    }
}
