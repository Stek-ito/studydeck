<?php

use App\Http\Controllers\CardController;
use App\Http\Controllers\DeckController;
use Illuminate\Support\Facades\Route;

Route::get('/ping', function () {
    return ['status' => 'ok'];
});

// Deck routes
Route::get('/decks', [DeckController::class, 'index'])->name('decks.index');
Route::get('/decks/{id}', [DeckController::class, 'show'])->name('decks.show')->where('id', '[0-9]+');

// Card routes
Route::prefix('decks/{deck}/cards')
    ->whereNumber(['deck', 'card'])
    ->group(function () {
        Route::get('/', [CardController::class, 'index'])->name('cards.index')->where('id', '[0-9]+');
        Route::get('/{card}', [CardController::class, 'show'])->name('cards.show')->where('id', '[0-9]+');
    });
