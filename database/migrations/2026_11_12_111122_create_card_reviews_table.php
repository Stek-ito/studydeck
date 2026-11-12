<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('card_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('card_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('box')->default(1);           // Leitner box (1-6)
            $table->date('due_at')->index();                          // next review day
            $table->timestamp('last_reviewed_at')->nullable();        // date and time of the last review
            $table->text('own_explanation')->nullable();              // explanation in my own words
            $table->text('doubt')->nullable();                        // open question about the card
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('card_reviews');
    }
};
