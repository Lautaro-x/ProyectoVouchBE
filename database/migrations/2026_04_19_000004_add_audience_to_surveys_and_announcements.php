<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('surveys', function (Blueprint $table) {
            $table->enum('audience', ['all', 'verified', 'press'])->default('all')->after('ends_at');
        });

        Schema::table('announcements', function (Blueprint $table) {
            $table->enum('audience', ['all', 'verified', 'press'])->default('all')->after('ends_at');
        });
    }

    public function down(): void
    {
        Schema::table('surveys',       fn(Blueprint $t) => $t->dropColumn('audience'));
        Schema::table('announcements', fn(Blueprint $t) => $t->dropColumn('audience'));
    }
};
