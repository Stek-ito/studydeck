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
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('original_name');                          // e.g. "chapter-3.pdf"
            $table->string('path');                                   // location inside storage/
            $table->char('sha256', 64);                               // always 64 hex characters
            $table->string('status')->default('pending');             // pending/processing/done/failed
            $table->unsignedSmallInteger('pages')->nullable();        // known once processed
            $table->unsignedInteger('tokens_used')->default(0);       // Claude API cost
            $table->timestamps();

            $table->unique(['user_id', 'sha256']);                    // same PDF, same user: rejected
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
