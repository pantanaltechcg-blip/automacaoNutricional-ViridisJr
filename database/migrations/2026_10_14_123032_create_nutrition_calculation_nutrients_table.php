<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nutrition_calculation_nutrients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nutrition_calculation_id')
                ->constrained('nutrition_calculations')
                ->cascadeOnDelete();
            $table->unsignedBigInteger('nutrient_id');
            $table->text('nutrient_name');
            $table->text('nutrient_unit');
            $table->boolean('is_complete');
            $table->boolean('has_missing_relation');
            $table->string('reference_unit', 2);

            $table->unique(
                ['nutrition_calculation_id', 'nutrient_id'],
                'nutrition_calc_nutrients_nutrient_unique',
            );
        });

        DB::statement(<<<'SQL'
            ALTER TABLE nutrition_calculation_nutrients
                ADD COLUMN total_numerator NUMERIC,
                ADD COLUMN total_denominator NUMERIC,
                ADD COLUMN reference_basis NUMERIC NOT NULL,
                ADD COLUMN reference_numerator NUMERIC,
                ADD COLUMN reference_denominator NUMERIC
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE nutrition_calculation_nutrients
            ADD CONSTRAINT nutrition_calculation_nutrients_total_ratio_check
            CHECK (
                (total_numerator IS NULL AND total_denominator IS NULL)
                OR (
                    total_numerator IS NOT NULL
                    AND total_denominator IS NOT NULL
                    AND total_numerator NOT IN ('Infinity'::numeric, '-Infinity'::numeric, 'NaN'::numeric)
                    AND total_denominator NOT IN ('Infinity'::numeric, 'NaN'::numeric)
                    AND scale(total_numerator) = 0
                    AND scale(total_denominator) = 0
                    AND total_denominator > 0
                    AND (total_numerator <> 0 OR total_denominator = 1)
                )
            )
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE nutrition_calculation_nutrients
            ADD CONSTRAINT nutrition_calculation_nutrients_reference_ratio_check
            CHECK (
                (reference_numerator IS NULL AND reference_denominator IS NULL)
                OR (
                    reference_numerator IS NOT NULL
                    AND reference_denominator IS NOT NULL
                    AND reference_numerator NOT IN ('Infinity'::numeric, '-Infinity'::numeric, 'NaN'::numeric)
                    AND reference_denominator NOT IN ('Infinity'::numeric, 'NaN'::numeric)
                    AND scale(reference_numerator) = 0
                    AND scale(reference_denominator) = 0
                    AND reference_denominator > 0
                    AND (reference_numerator <> 0 OR reference_denominator = 1)
                )
            )
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE nutrition_calculation_nutrients
            ADD CONSTRAINT nutrition_calculation_nutrients_total_reference_presence_check
            CHECK (
                (total_numerator IS NULL AND reference_numerator IS NULL)
                OR (total_numerator IS NOT NULL AND reference_numerator IS NOT NULL)
            )
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE nutrition_calculation_nutrients
            ADD CONSTRAINT nutrition_calculation_nutrients_reference_basis_check
            CHECK (reference_basis = 100)
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE nutrition_calculation_nutrients
            ADD CONSTRAINT nutrition_calculation_nutrients_reference_unit_check
            CHECK (reference_unit IN ('g', 'ml'))
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('nutrition_calculation_nutrients');
    }
};
