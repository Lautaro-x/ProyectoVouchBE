<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->index('banned_at');
            $table->index('product_id');
        });

        Schema::table('Product_x_Platform', function (Blueprint $table) {
            $table->index('release_date');
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropIndex(['banned_at']);
            $table->dropIndex(['product_id']);
        });

        Schema::table('Product_x_Platform', function (Blueprint $table) {
            $table->dropIndex(['release_date']);
        });
    }
};
