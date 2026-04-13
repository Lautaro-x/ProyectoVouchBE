<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('Product_x_Platform', function (Blueprint $table) {
            $table->foreignIdFor(\App\Models\Product::class)->constrained('Products')->cascadeOnDelete();
            $table->foreignIdFor(\App\Models\Platform::class)->constrained('Platforms')->cascadeOnDelete();
            $table->smallInteger('release_year')->nullable();
            $table->string('purchase_url')->nullable();

            $table->primary(['product_id', 'platform_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('Product_x_Platform');
    }
};
