<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('show_email')->default(false)->after('ban_reason');
            $table->boolean('reviews_public')->default(true)->after('show_email');
            $table->json('social_links')->nullable()->after('reviews_public');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['show_email', 'reviews_public', 'social_links']);
        });
    }
};
