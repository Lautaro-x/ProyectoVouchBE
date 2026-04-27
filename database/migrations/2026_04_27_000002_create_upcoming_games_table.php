<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('upcoming_games', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('igdb_id')->unique();
            $table->string('title');
            $table->string('slug')->unique();
            $table->date('release_date')->nullable();
            $table->string('cover_image')->nullable();
            $table->string('trailer_youtube_id')->nullable();
            $table->string('developer')->nullable();
            $table->string('official_url')->nullable();
            $table->unsignedInteger('hypes')->nullable();
            $table->boolean('is_visible')->default(true);
            $table->timestamp('igdb_synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('upcoming_games');
    }
};
