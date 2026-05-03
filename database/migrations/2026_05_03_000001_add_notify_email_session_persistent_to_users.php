<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('Users', function (Blueprint $table) {
            $table->boolean('notify_email')->default(true)->after('consent_follower_score');
            $table->boolean('session_persistent')->default(false)->after('notify_email');
        });
    }

    public function down(): void
    {
        Schema::table('Users', function (Blueprint $table) {
            $table->dropColumn(['notify_email', 'session_persistent']);
        });
    }
};
