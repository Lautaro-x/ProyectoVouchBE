<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Reviews — solo si la columna sigue siendo entera (puede haber corrido parcialmente)
        if (Schema::getColumnType('Reviews', 'weighted_score') !== 'decimal') {
            Schema::table('Reviews', function (Blueprint $table) {
                $table->decimal('weighted_score', 3, 1)->default(0)->change();
            });

            DB::statement('UPDATE `Reviews` SET `weighted_score` = ROUND(`weighted_score` / 10.0, 1)');

            DB::statement("
                UPDATE `Reviews` SET `letter_grade` = CASE
                    WHEN `weighted_score` >= 10.0 THEN 'S'
                    WHEN `weighted_score` >= 9.1  THEN 'A+'
                    WHEN `weighted_score` >= 9.0  THEN 'A'
                    WHEN `weighted_score` >= 8.1  THEN 'B+'
                    WHEN `weighted_score` >= 8.0  THEN 'B'
                    WHEN `weighted_score` >= 7.1  THEN 'C+'
                    WHEN `weighted_score` >= 7.0  THEN 'C'
                    WHEN `weighted_score` >= 6.1  THEN 'D+'
                    WHEN `weighted_score` >= 6.0  THEN 'D'
                    WHEN `weighted_score` >= 5.1  THEN 'E+'
                    WHEN `weighted_score` >= 5.0  THEN 'E'
                    ELSE 'F'
                END
            ");
        }

        // ProductScores — usar columnas temporales para evitar out-of-range al hacer MODIFY
        Schema::table('ProductScores', function (Blueprint $table) {
            $table->decimal('global_score_tmp', 3, 1)->nullable();
            $table->decimal('pro_score_tmp', 3, 1)->nullable();
        });

        DB::statement('UPDATE `ProductScores` SET `global_score_tmp` = ROUND(`global_score` / 10.0, 1) WHERE `global_score` IS NOT NULL');
        DB::statement('UPDATE `ProductScores` SET `pro_score_tmp`    = ROUND(`pro_score`    / 10.0, 1) WHERE `pro_score`    IS NOT NULL');

        Schema::table('ProductScores', function (Blueprint $table) {
            $table->dropColumn(['global_score', 'pro_score']);
        });

        Schema::table('ProductScores', function (Blueprint $table) {
            $table->renameColumn('global_score_tmp', 'global_score');
            $table->renameColumn('pro_score_tmp', 'pro_score');
        });
    }

    public function down(): void
    {
        Schema::table('Reviews', function (Blueprint $table) {
            $table->integer('weighted_score')->default(0)->change();
        });

        DB::statement('UPDATE `Reviews` SET `weighted_score` = ROUND(`weighted_score` * 10)');

        Schema::table('ProductScores', function (Blueprint $table) {
            $table->decimal('global_score_tmp', 5, 1)->nullable();
            $table->decimal('pro_score_tmp', 5, 1)->nullable();
        });

        DB::statement('UPDATE `ProductScores` SET `global_score_tmp` = ROUND(`global_score` * 10) WHERE `global_score` IS NOT NULL');
        DB::statement('UPDATE `ProductScores` SET `pro_score_tmp`    = ROUND(`pro_score`    * 10) WHERE `pro_score`    IS NOT NULL');

        Schema::table('ProductScores', function (Blueprint $table) {
            $table->dropColumn(['global_score', 'pro_score']);
        });

        Schema::table('ProductScores', function (Blueprint $table) {
            $table->renameColumn('global_score_tmp', 'global_score');
            $table->renameColumn('pro_score_tmp', 'pro_score');
        });
    }
};
