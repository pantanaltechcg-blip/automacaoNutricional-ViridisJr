<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var list<string> */
    private const TABLES = [
        'regulatory_profiles', 'regulatory_nutrient_mappings', 'regulatory_energy_components',
        'regulatory_daily_values', 'portion_authorizations', 'regulatory_energy_applicabilities',
        'serving_daily_value_calculations', 'serving_daily_value_nutrients',
        'serving_daily_value_nutrient_states', 'serving_daily_value_regulatory_results',
        'serving_daily_value_energy_components', 'serving_daily_value_percentages',
        'serving_daily_value_result_causes',
    ];

    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION prevent_serving_daily_value_mutation()
            RETURNS TRIGGER AS $$
            BEGIN
                RAISE EXCEPTION 'Serving daily value data is immutable.';
            END;
            $$ LANGUAGE plpgsql
            SQL);

        foreach (self::TABLES as $table) {
            DB::statement("CREATE TRIGGER prevent_{$table}_mutation BEFORE UPDATE OR DELETE ON {$table} FOR EACH ROW EXECUTE FUNCTION prevent_serving_daily_value_mutation() ");
        }
    }

    public function down(): void
    {
        foreach (array_reverse(self::TABLES) as $table) {
            DB::statement("DROP TRIGGER IF EXISTS prevent_{$table}_mutation ON {$table}");
        }

        DB::statement('DROP FUNCTION IF EXISTS prevent_serving_daily_value_mutation()');
    }
};
