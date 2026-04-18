<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('surveys')) return;

        DB::statement('ALTER TABLE surveys MODIFY COLUMN title JSON NOT NULL');
        DB::statement('ALTER TABLE surveys MODIFY COLUMN question JSON NOT NULL');
        DB::statement('ALTER TABLE survey_options MODIFY COLUMN text JSON NOT NULL');
    }

    public function down(): void
    {
        if (!Schema::hasTable('surveys')) return;

        DB::statement('ALTER TABLE surveys MODIFY COLUMN title VARCHAR(255) NOT NULL');
        DB::statement('ALTER TABLE surveys MODIFY COLUMN question TEXT NOT NULL');
        DB::statement('ALTER TABLE survey_options MODIFY COLUMN text VARCHAR(255) NOT NULL');
    }
};
