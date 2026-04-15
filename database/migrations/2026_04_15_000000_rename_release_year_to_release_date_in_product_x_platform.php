<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('Product_x_Platform', function (Blueprint $table) {
            $table->date('release_date')->nullable()->after('platform_id');
        });

        DB::statement("
            UPDATE Product_x_Platform
            SET release_date = CONCAT(release_year, '-01-01')
            WHERE release_year IS NOT NULL
        ");

        Schema::table('Product_x_Platform', function (Blueprint $table) {
            $table->dropColumn('release_year');
        });
    }

    public function down(): void
    {
        Schema::table('Product_x_Platform', function (Blueprint $table) {
            $table->smallInteger('release_year')->nullable()->after('platform_id');
        });

        DB::statement("
            UPDATE Product_x_Platform
            SET release_year = YEAR(release_date)
            WHERE release_date IS NOT NULL
        ");

        Schema::table('Product_x_Platform', function (Blueprint $table) {
            $table->dropColumn('release_date');
        });
    }
};
