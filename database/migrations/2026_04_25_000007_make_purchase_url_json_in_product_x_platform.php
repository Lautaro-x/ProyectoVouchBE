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
            $table->json('purchase_links')->nullable()->after('purchase_url');
        });

        DB::statement(
            "UPDATE Product_x_Platform
             SET purchase_links = JSON_OBJECT('steam', purchase_url)
             WHERE purchase_url IS NOT NULL AND purchase_url != ''"
        );

        Schema::table('Product_x_Platform', function (Blueprint $table) {
            $table->dropColumn('purchase_url');
        });

        Schema::table('Product_x_Platform', function (Blueprint $table) {
            $table->renameColumn('purchase_links', 'purchase_url');
        });
    }

    public function down(): void
    {
        Schema::table('Product_x_Platform', function (Blueprint $table) {
            $table->string('purchase_url_old', 500)->nullable()->after('purchase_url');
        });

        DB::statement(
            "UPDATE Product_x_Platform
             SET purchase_url_old = JSON_UNQUOTE(JSON_EXTRACT(purchase_url, '$.steam'))
             WHERE purchase_url IS NOT NULL"
        );

        Schema::table('Product_x_Platform', function (Blueprint $table) {
            $table->dropColumn('purchase_url');
        });

        Schema::table('Product_x_Platform', function (Blueprint $table) {
            $table->renameColumn('purchase_url_old', 'purchase_url');
        });
    }
};
