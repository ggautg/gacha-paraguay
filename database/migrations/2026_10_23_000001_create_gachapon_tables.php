<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('characters', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('wiki_title');
            $table->string('wiki_url');
            $table->string('image_path')->nullable();
            $table->text('extract')->nullable();
            $table->string('role')->nullable();
            $table->string('era')->nullable();
            $table->string('category')->nullable();
            $table->unsignedTinyInteger('rarity')->default(1)->index();
            $table->timestamps();
        });

        Schema::create('players', function (Blueprint $table) {
            $table->id();
            $table->uuid('token')->unique();
            $table->string('name')->default('Coleccionista');
            $table->unsignedInteger('coins')->default(1500);
            $table->unsignedInteger('total_pulls')->default(0);
            $table->unsignedTinyInteger('pity')->default(0);
            $table->timestamp('bonus_claimed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('collection_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player_id')->constrained()->cascadeOnDelete();
            $table->foreignId('character_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->timestamps();

            $table->unique(['player_id', 'character_id']);
        });

        Schema::create('pulls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player_id')->constrained()->cascadeOnDelete();
            $table->foreignId('character_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('rarity');
            $table->boolean('is_new')->default(false);
            $table->timestamps();

            $table->index(['player_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pulls');
        Schema::dropIfExists('collection_entries');
        Schema::dropIfExists('players');
        Schema::dropIfExists('characters');
    }
};
