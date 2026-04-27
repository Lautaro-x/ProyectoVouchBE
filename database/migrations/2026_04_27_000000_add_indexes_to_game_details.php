<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('GameDetails', function (Blueprint $table) {
            $table->index('developer');
            $table->index('publisher');
            $table->index('franchise');
        });
    }

    public function down(): void
    {
        Schema::table('GameDetails', function (Blueprint $table) {
            $table->dropIndex(['developer']);
            $table->dropIndex(['publisher']);
            $table->dropIndex(['franchise']);
        });
    }
};
