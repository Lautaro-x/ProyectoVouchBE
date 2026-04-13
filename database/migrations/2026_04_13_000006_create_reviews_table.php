<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('Reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignIdFor(\App\Models\Product::class)->constrained('Products')->cascadeOnDelete();
            $table->string('body', 255)->nullable();
            $table->tinyInteger('weighted_score')->unsigned();
            $table->string('letter_grade', 2);
            $table->timestamps();

            $table->unique(['user_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('Reviews');
    }
};
