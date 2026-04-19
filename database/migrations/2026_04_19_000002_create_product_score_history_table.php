<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ProductScoreHistory', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(\App\Models\Product::class)->constrained('Products')->cascadeOnDelete();
            $table->tinyInteger('global_score')->unsigned()->nullable();
            $table->tinyInteger('pro_score')->unsigned()->nullable();
            $table->date('snapshot_date');
            $table->unique(['product_id', 'snapshot_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ProductScoreHistory');
    }
};
