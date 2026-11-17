<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var list<string> */
    private const TABLES = [
        'regulatory_declaration_profiles',
        'regulatory_recalculation_policies',
        'regulatory_non_significant_rules',
        'regulatory_rounding_rules',
        'regulatory_rounding_rule_ranges',
        'regulatory_declaration_requirements',
        'declared_nutrition_calculations',
        'declared_nutrition_components',
        'declared_nutrition_component_states',
        'declared_nutrition_energy_components',
        'declared_nutrition_percentages',
        'declared_nutrition_causes',
    ];

    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION prevent_declared_nutrition_mutation()
            RETURNS TRIGGER AS $$
            BEGIN
                RAISE EXCEPTION 'Declared nutrition data is immutable.';
            END;
            $$ LANGUAGE plpgsql
            SQL);

        foreach (self::TABLES as $table) {
            DB::statement("CREATE TRIGGER prevent_{$table}_mutation BEFORE UPDATE OR DELETE ON {$table} FOR EACH ROW EXECUTE FUNCTION prevent_declared_nutrition_mutation()");
        }
    }

    public function down(): void
    {
        foreach (array_reverse(self::TABLES) as $table) {
            DB::statement("DROP TRIGGER IF EXISTS prevent_{$table}_mutation ON {$table}");
        }

        DB::statement('DROP FUNCTION IF EXISTS prevent_declared_nutrition_mutation()');
    }
};
