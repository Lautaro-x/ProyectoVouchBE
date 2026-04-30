<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('Follows', function (Blueprint $table) {
            $table->index('followed_id');
        });

        Schema::table('Reviews', function (Blueprint $table) {
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('Follows', function (Blueprint $table) {
            $table->dropIndex(['followed_id']);
        });

        Schema::table('Reviews', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
        });
    }
};
