<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('GameDetails', function (Blueprint $table) {
            $table->dropColumn(['gog_url', 'epic_url']);
        });
    }

    public function down(): void
    {
        Schema::table('GameDetails', function (Blueprint $table) {
            $table->string('gog_url', 500)->nullable()->after('esrb_rating');
            $table->string('epic_url', 500)->nullable()->after('gog_url');
        });
    }
};
