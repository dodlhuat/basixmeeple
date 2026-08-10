<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('games', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->unsignedInteger('bgg_id')->nullable()->unique();
            $table->string('publisher')->nullable();
            $table->unsignedSmallInteger('min_players')->nullable();
            $table->unsignedSmallInteger('max_players')->nullable();
            $table->unsignedSmallInteger('play_time_min')->nullable();
            $table->unsignedSmallInteger('play_time_max')->nullable();
            $table->unsignedTinyInteger('min_age')->nullable();
            $table->decimal('weight_complexity', 3, 2)->nullable();
            $table->text('description')->nullable();
            $table->string('cover_url')->nullable();
            $table->string('rulebook_path')->nullable();
            $table->string('edition')->nullable();
            $table->string('language')->nullable();
            $table->text('condition_notes')->nullable();
            $table->decimal('purchase_price', 8, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('games');
    }
};
