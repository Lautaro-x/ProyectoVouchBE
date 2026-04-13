<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('Review_x_Category', function (Blueprint $table) {
            $table->foreignIdFor(\App\Models\Review::class)->constrained('Reviews')->cascadeOnDelete();
            $table->foreignIdFor(\App\Models\Category::class)->constrained('Categories')->restrictOnDelete();
            $table->tinyInteger('score')->unsigned();

            $table->primary(['review_id', 'category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('Review_x_Category');
    }
};
