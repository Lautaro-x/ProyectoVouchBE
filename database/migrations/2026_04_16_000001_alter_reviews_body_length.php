<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('Reviews', function (Blueprint $table) {
            $table->string('body', 2200)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('Reviews', function (Blueprint $table) {
            $table->string('body', 255)->nullable()->change();
        });
    }
};
