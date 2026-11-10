<?php

namespace App\Http\Controllers;

use App\Models\Card;
use App\Models\Deck;
use Illuminate\Database\Eloquent\Collection;

class CardController extends Controller
{
    public function index(Deck $deck): Collection
    {
        return $deck->cards;
    }

    public function show(Deck $deck, Card $card): Card
    {
        return $card;
    }
}
