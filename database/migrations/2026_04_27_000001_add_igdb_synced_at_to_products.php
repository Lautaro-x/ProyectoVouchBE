<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('GameDetails', function (Blueprint $table) {
            $table->timestamp('igdb_synced_at')->nullable()->after('igdb_id');
        });
    }

    public function down(): void
    {
        Schema::table('GameDetails', function (Blueprint $table) {
            $table->dropColumn('igdb_synced_at');
        });
    }
};
