<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->convertTable('Genres');
        $this->convertTable('Categories');
    }

    public function down(): void
    {
        $this->revertTable('Genres');
        $this->revertTable('Categories');
    }

    private function convertTable(string $table): void
    {
        Schema::table($table, fn (Blueprint $t) => $t->renameColumn('name', 'name_old'));

        Schema::table($table, fn (Blueprint $t) => $t->json('name')->after('id'));

        DB::table($table)->get()->each(function ($row) use ($table) {
            DB::table($table)->where('id', $row->id)->update([
                'name' => json_encode([
                    'en' => $row->name_old,
                    'es' => $row->name_old,
                    'fr' => $row->name_old,
                    'pt' => $row->name_old,
                    'it' => $row->name_old,
                ]),
            ]);
        });

        Schema::table($table, fn (Blueprint $t) => $t->dropColumn('name_old'));
    }

    private function revertTable(string $table): void
    {
        Schema::table($table, fn (Blueprint $t) => $t->renameColumn('name', 'name_json'));

        Schema::table($table, fn (Blueprint $t) => $t->string('name')->after('id'));

        DB::table($table)->get()->each(function ($row) use ($table) {
            $translations = json_decode($row->name_json, true);
            DB::table($table)->where('id', $row->id)->update([
                'name' => $translations['en'] ?? $translations[array_key_first($translations)] ?? '',
            ]);
        });

        Schema::table($table, fn (Blueprint $t) => $t->dropColumn('name_json'));
    }
};
