<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('Genres', function (Blueprint $table) {
            $table->unsignedInteger('igdb_genre_id')->nullable()->unique()->after('slug');
        });
    }

    public function down(): void
    {
        Schema::table('Genres', function (Blueprint $table) {
            $table->dropColumn('igdb_genre_id');
        });
    }
};
