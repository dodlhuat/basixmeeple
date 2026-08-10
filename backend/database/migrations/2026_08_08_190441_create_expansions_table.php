<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expansions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('base_game_id')->constrained('games')->cascadeOnDelete();
            $table->string('title');
            $table->unsignedInteger('bgg_id')->nullable()->unique();
            $table->string('cover_url')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expansions');
    }
};
