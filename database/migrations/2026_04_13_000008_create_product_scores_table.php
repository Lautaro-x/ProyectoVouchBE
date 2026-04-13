<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ProductScores', function (Blueprint $table) {
            $table->foreignIdFor(\App\Models\Product::class)->primary()->constrained('Products')->cascadeOnDelete();
            $table->tinyInteger('global_score')->unsigned()->nullable();
            $table->tinyInteger('pro_score')->unsigned()->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ProductScores');
    }
};
