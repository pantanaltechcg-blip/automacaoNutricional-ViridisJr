<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX foods_normalized_source_code_unique
            ON foods (UPPER(BTRIM(source)), UPPER(BTRIM(source_code)))
            WHERE source_code IS NOT NULL
            SQL);

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX foods_normalized_source_name_unique
            ON foods (UPPER(BTRIM(source)), LOWER(BTRIM(name)))
            SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS foods_normalized_source_name_unique');
        DB::statement('DROP INDEX IF EXISTS foods_normalized_source_code_unique');
    }
};
