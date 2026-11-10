<?php

namespace Database\Factories;

use App\Models\Card;
use App\Models\Deck;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Card>
 */
class CardFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'deck_id' => Deck::factory(),
            'term' => fake()->sentence(4),
            'definition' => fake()->paragraph(),
            'is_key' => fake()->boolean(20),
            'source_page' => fake()->numberBetween(1, 300),
        ];
    }
}
