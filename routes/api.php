<?php

use App\Http\Controllers\CardController;
use App\Http\Controllers\DeckController;
use Illuminate\Support\Facades\Route;

Route::get('/ping', function () {
    return ['status' => 'ok'];
});

// Deck routes
Route::get('/decks', [DeckController::class, 'index'])->name('decks.index');
Route::get('/decks/{deck}', [DeckController::class, 'show'])->name('decks.show')->whereNumber('deck');

// Card routes
Route::prefix('decks/{deck}/cards')
    ->whereNumber(['deck', 'card'])
    ->scopeBindings()
    ->group(function () {
        Route::get('/', [CardController::class, 'index'])->name('cards.index');
        Route::get('/{card}', [CardController::class, 'show'])->name('cards.show');
    });
