<?php

use App\Http\Controllers\Auth\RegisterController;
use App\Models\Deck;
use Illuminate\Support\Facades\Route;

Route::view('/', 'home')->name('home');

Route::get('/decks', function () {
    return view('decks.index', ['decks' => Deck::withCount('cards')->get()]);
})->name('decks.index');

Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store']);
});