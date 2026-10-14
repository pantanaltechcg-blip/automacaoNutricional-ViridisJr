<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nutrition_calculation_contributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nutrition_calculation_ingredient_id')
                ->constrained('nutrition_calculation_ingredients')
                ->cascadeOnDelete();
            $table->foreignId('nutrition_calculation_nutrient_id')
                ->constrained('nutrition_calculation_nutrients')
                ->cascadeOnDelete();
            $table->unsignedBigInteger('food_nutrient_id')->nullable();
            $table->text('input_status')->nullable();
            $table->decimal('input_amount_value', 7, 2)->nullable();
            $table->boolean('is_missing_relation');

            $table->unique(
                ['nutrition_calculation_ingredient_id', 'nutrition_calculation_nutrient_id'],
                'nutrition_calc_contributions_ingredient_nutrient_unique',
            );
        });

        DB::statement(<<<'SQL'
            ALTER TABLE nutrition_calculation_contributions
                ADD COLUMN value_numerator NUMERIC,
                ADD COLUMN value_denominator NUMERIC
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE nutrition_calculation_contributions
            ADD CONSTRAINT nutrition_calculation_contributions_status_check
            CHECK (input_status IN ('EXACT', 'TRACE', 'NA', 'ABSENT', 'REEVALUATION'))
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE nutrition_calculation_contributions
            ADD CONSTRAINT nutrition_calculation_contributions_value_ratio_check
            CHECK (
                (value_numerator IS NULL AND value_denominator IS NULL)
                OR (
                    value_numerator IS NOT NULL
                    AND value_denominator IS NOT NULL
                    AND value_numerator NOT IN ('Infinity'::numeric, '-Infinity'::numeric, 'NaN'::numeric)
                    AND value_denominator NOT IN ('Infinity'::numeric, 'NaN'::numeric)
                    AND scale(value_numerator) = 0
                    AND scale(value_denominator) = 0
                    AND value_denominator > 0
                    AND (value_numerator <> 0 OR value_denominator = 1)
                )
            )
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE nutrition_calculation_contributions
            ADD CONSTRAINT nutrition_calculation_contributions_shape_check
            CHECK (
                (
                    is_missing_relation = true
                    AND food_nutrient_id IS NULL
                    AND input_status IS NULL
                    AND input_amount_value IS NULL
                    AND value_numerator IS NULL
                    AND value_denominator IS NULL
                )
                OR (
                    is_missing_relation = false
                    AND food_nutrient_id IS NOT NULL
                    AND input_status = 'EXACT'
                    AND input_amount_value IS NOT NULL
                    AND value_numerator IS NOT NULL
                    AND value_denominator IS NOT NULL
                )
                OR (
                    is_missing_relation = false
                    AND food_nutrient_id IS NOT NULL
                    AND input_status IN ('TRACE', 'NA', 'ABSENT', 'REEVALUATION')
                    AND input_amount_value IS NULL
                    AND value_numerator IS NULL
                    AND value_denominator IS NULL
                )
            )
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('nutrition_calculation_contributions');
    }
};
