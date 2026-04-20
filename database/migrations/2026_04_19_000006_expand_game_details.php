<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('GameDetails', function (Blueprint $table) {
            $table->text('storyline')->nullable();
            $table->float('igdb_rating')->nullable();
            $table->unsignedInteger('igdb_rating_count')->nullable();
            $table->float('aggregated_rating')->nullable();
            $table->unsignedInteger('aggregated_rating_count')->nullable();
            $table->unsignedInteger('hypes')->nullable();
            $table->unsignedInteger('follows')->nullable();
            $table->unsignedTinyInteger('status')->nullable();
            $table->unsignedTinyInteger('category')->nullable();
            $table->string('franchise')->nullable();
            $table->string('trailer_youtube_id')->nullable();
            $table->string('pegi_rating')->nullable();
            $table->string('esrb_rating')->nullable();
            $table->string('gog_url')->nullable();
            $table->string('epic_url')->nullable();
            $table->string('official_url')->nullable();
            $table->json('game_modes')->nullable();
            $table->json('themes')->nullable();
            $table->json('player_perspectives')->nullable();
            $table->json('screenshots')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('GameDetails', function (Blueprint $table) {
            $table->dropColumn([
                'storyline', 'igdb_rating', 'igdb_rating_count',
                'aggregated_rating', 'aggregated_rating_count',
                'hypes', 'follows', 'status', 'category',
                'franchise', 'trailer_youtube_id', 'pegi_rating', 'esrb_rating',
                'gog_url', 'epic_url', 'official_url',
                'game_modes', 'themes', 'player_perspectives', 'screenshots',
            ]);
        });
    }
};
