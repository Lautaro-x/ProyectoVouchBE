<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_trailer_sections', function (Blueprint $table) {
            $table->id();
            $table->json('title');
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });

        Schema::create('custom_trailer_items', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('youtube_url');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_trailer_items');
        Schema::dropIfExists('custom_trailer_sections');
    }
};
