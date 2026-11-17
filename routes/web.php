<?php

use App\Models\Deck;
use Illuminate\Support\Facades\Route;

Route::view('/', 'home')->name('home');

Route::get('/decks', function () {
    return view('decks.index', ['decks' => Deck::withCount('cards')->get()]);
})->name('decks.index');