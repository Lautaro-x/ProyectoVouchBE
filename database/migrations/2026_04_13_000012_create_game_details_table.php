<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('GameDetails', function (Blueprint $table) {
            $table->foreignIdFor(\App\Models\Product::class)->primary()->constrained('Products')->cascadeOnDelete();
            $table->unsignedBigInteger('igdb_id')->nullable()->unique();
            $table->string('developer')->nullable();
            $table->string('publisher')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('GameDetails');
    }
};
