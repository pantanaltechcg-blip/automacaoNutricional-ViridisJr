<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var list<string> */
    private const TABLES = [
        'nutrition_calculations',
        'nutrition_calculation_ingredients',
        'nutrition_calculation_nutrients',
        'nutrition_calculation_contributions',
        'nutrition_calculation_nutrient_states',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE FUNCTION prevent_nutrition_calculation_history_mutation()
            RETURNS TRIGGER AS $$
            BEGIN
                RAISE EXCEPTION 'Nutrition calculation history is immutable.';
            END;
            $$ LANGUAGE plpgsql
            SQL);

        foreach (self::TABLES as $table) {
            DB::statement(<<<SQL
                CREATE TRIGGER {$table}_prevent_mutation
                BEFORE UPDATE OR DELETE ON {$table}
                FOR EACH ROW
                EXECUTE FUNCTION prevent_nutrition_calculation_history_mutation()
                SQL);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (array_reverse(self::TABLES) as $table) {
            DB::statement("DROP TRIGGER IF EXISTS {$table}_prevent_mutation ON {$table}");
        }

        DB::statement('DROP FUNCTION IF EXISTS prevent_nutrition_calculation_history_mutation()');
    }
};
