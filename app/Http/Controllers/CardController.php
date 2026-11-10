<?php

namespace App\Http\Controllers;

class CardController extends Controller
{
    //
    private const CARDS = [
        1 => [
            1 => [
                'id' => 1,
                'deck_id' => 1,
                'term' => 'What is PHP?',
                'definition' => 'PHP is a popular general-purpose scripting language that is especially suited to web development.',
            ],
            2 => [
                'id' => 2,
                'deck_id' => 1,
                'term' => 'What is a variable in PHP?',
                'definition' => 'A variable in PHP is a container for storing data values. It is represented by a dollar sign ($) followed by the variable name.',
            ],

        ],
        2 => [
            1 => [
                'id' => 1,
                'deck_id' => 2,
                'term' => 'What is Symfony?',
                'definition' => 'Symfony is a set of reusable PHP components and a PHP framework for web projects.',
            ],
            2 => [
                'id' => 1,
                'deck_id' => 2,
                'term' => 'What is Symfony2?',
                'definition' => 'Symfony is a set of reusable PHP components and a PHP framework for web projects.2',
            ],
        ],
        3 => [
            1 => [
                'id' => 1,
                'deck_id' => 3,
                'term' => 'What is Laravel?',
                'definition' => 'Laravel is a web application framework with expressive, elegant syntax.',
            ],
            2 => [
                'id' => 1,
                'deck_id' => 3,
                'term' => 'What is Laravel?2',
                'definition' => 'Laravel is a web application framework with expressive, elegant syntax.2',
            ],
        ],
    ];

    public function index(int $deck): array
    {
        abort_unless(array_key_exists($deck, self::CARDS), 404, 'Deck not found');

        return array_values(self::CARDS[$deck]);
    }

    public function show(int $deck, int $card): array
    {
        abort_unless(array_key_exists($deck, self::CARDS), 404, 'Deck not found');
        abort_unless(array_key_exists($card, self::CARDS[$deck]), 404, 'Card not found');

        return self::CARDS[$deck][$card];
    }
}
