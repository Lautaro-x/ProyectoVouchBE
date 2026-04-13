<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('Genre_x_Category', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(\App\Models\Genre::class)->constrained('Genres')->cascadeOnDelete();
            $table->foreignIdFor(\App\Models\Category::class)->constrained('Categories')->cascadeOnDelete();
            $table->decimal('weight', 3, 2);
            $table->timestamps();

            $table->unique(['genre_id', 'category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('Genre_x_Category');
    }
};
