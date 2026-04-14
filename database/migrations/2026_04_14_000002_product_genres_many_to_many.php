<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('Product_x_Genre', function (Blueprint $table) {
            $table->foreignId('product_id')->constrained('Products')->cascadeOnDelete();
            $table->foreignId('genre_id')->constrained('Genres')->cascadeOnDelete();
            $table->primary(['product_id', 'genre_id']);
            $table->timestamps();
        });

        DB::statement('
            INSERT INTO Product_x_Genre (product_id, genre_id, created_at, updated_at)
            SELECT id, genre_id, NOW(), NOW()
            FROM Products
            WHERE genre_id IS NOT NULL
        ');

        Schema::table('Products', function (Blueprint $table) {
            $table->dropForeign(['genre_id']);
            $table->dropColumn('genre_id');
        });
    }

    public function down(): void
    {
        Schema::table('Products', function (Blueprint $table) {
            $table->unsignedBigInteger('genre_id')->nullable()->after('type');
        });

        DB::statement('
            UPDATE Products p
            INNER JOIN (
                SELECT product_id, MIN(genre_id) as genre_id
                FROM Product_x_Genre
                GROUP BY product_id
            ) pg ON p.id = pg.product_id
            SET p.genre_id = pg.genre_id
        ');

        Schema::table('Products', function (Blueprint $table) {
            $table->foreign('genre_id')->references('id')->on('Genres')->nullOnDelete();
        });

        Schema::dropIfExists('Product_x_Genre');
    }
};
