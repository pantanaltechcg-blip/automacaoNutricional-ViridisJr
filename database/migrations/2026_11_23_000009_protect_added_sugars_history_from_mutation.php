<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var list<string> */
    private const IMMUTABLE_TABLES = [
        'added_sugars_formulation_versions',
        'added_sugars_formulation_ingredients',
        'added_sugars_formulation_evidences',
        'added_sugars_formulation_validations',
        'added_sugars_evaluations',
        'added_sugars_evaluation_items',
        'added_sugars_evaluation_causes',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION prevent_added_sugars_history_mutation()
            RETURNS TRIGGER AS $$
            BEGIN
                RAISE EXCEPTION 'Added sugars formulation and evaluation history is immutable.';
            END;
            $$ LANGUAGE plpgsql
            SQL);

        foreach (self::IMMUTABLE_TABLES as $table) {
            DB::statement(<<<SQL
                CREATE TRIGGER {$table}_prevent_mutation
                BEFORE UPDATE OR DELETE ON {$table}
                FOR EACH ROW
                EXECUTE FUNCTION prevent_added_sugars_history_mutation()
                SQL);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (array_reverse(self::IMMUTABLE_TABLES) as $table) {
            DB::statement("DROP TRIGGER IF EXISTS {$table}_prevent_mutation ON {$table}");
        }

        DB::statement('DROP FUNCTION IF EXISTS prevent_added_sugars_history_mutation()');
    }
};
