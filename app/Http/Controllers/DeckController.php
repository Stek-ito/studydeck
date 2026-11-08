<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;


class DeckController extends Controller
{
    private const DECKS = [
        1 => [
            'id' => 1,
            'name' => 'PHP',
            'description' => 'This is the first deck.',
        ],
        2 => [
            'id' => 2,
            'name' => 'Symfony',
            'description' => 'This is the second deck.',
        ],
        3 => [
            'id' => 3,
            'name' => 'Laravel',
            'description' => 'This is the third deck.',
        ],
    ];

    public function index()
    {
        return array_values(self::DECKS);
    }

    public function show(int $id)
    {
        if (!array_key_exists($id, self::DECKS)) {
            abort(404, 'Deck not found');
        }
        return self::DECKS[$id];
    }
}
