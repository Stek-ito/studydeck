<?php

namespace App\Http\Controllers;

use App\Models\Deck;
use Illuminate\Database\Eloquent\Collection;

class DeckController extends Controller
{
    public function index(): Collection
    {
        return Deck::all();
    }

    public function show(Deck $deck): Deck
    {
        return $deck;
    }
}
