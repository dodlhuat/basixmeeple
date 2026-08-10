<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collection_games', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('collection_id')->constrained('collections')->cascadeOnDelete();
            $table->foreignUuid('game_id')->constrained('games')->cascadeOnDelete();
            $table->string('location')->nullable();
            $table->string('condition')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['collection_id', 'game_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collection_games');
    }
};
