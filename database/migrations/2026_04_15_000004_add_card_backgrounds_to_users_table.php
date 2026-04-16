<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('card_big_bg')->nullable()->after('social_links');
            $table->string('card_mid_bg')->nullable()->after('card_big_bg');
            $table->string('card_mini_bg')->nullable()->after('card_mid_bg');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['card_big_bg', 'card_mid_bg', 'card_mini_bg']);
        });
    }
};
