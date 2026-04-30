<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('Reviews', function (Blueprint $table) {
            $table->decimal('weighted_score', 4, 1)->unsigned()->change();
        });
    }

    public function down(): void
    {
        Schema::table('Reviews', function (Blueprint $table) {
            $table->tinyInteger('weighted_score')->unsigned()->change();
        });
    }
};
