<?php

namespace Database\Seeders;

use App\Models\Card;
use App\Models\Deck;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $user = User::factory()->create([
            'name' => 'Roberto',
            'email' => 'roberto@studydeck.test',
        ]);

        Deck::factory()
            ->count(3)
            ->for($user)
            ->has(Card::factory()->count(10))
            ->create();
    }
}
