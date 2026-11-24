<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('serving_daily_value_calculations', function (Blueprint $table): void {
            $table->foreignId('added_sugars_evaluation_id')
                ->nullable()
                ->constrained('added_sugars_evaluations')
                ->restrictOnDelete();
            $table->integer('added_sugars_formulation_version_number')->nullable();
            $table->text('added_sugars_validation_status')->nullable();
            $table->boolean('added_sugars_is_regulatory_eligible')->nullable();
            $table->boolean('added_sugars_is_confirmed_zero')->nullable();
            $table->decimal('added_sugars_reference_numerator')->nullable();
            $table->decimal('added_sugars_reference_denominator')->nullable();
            $table->text('added_sugars_reference_unit')->nullable();
        });

        DB::statement('ALTER TABLE serving_daily_value_calculations ALTER COLUMN added_sugars_reference_numerator TYPE NUMERIC, ALTER COLUMN added_sugars_reference_denominator TYPE NUMERIC');
        DB::statement(<<<'SQL'
            ALTER TABLE serving_daily_value_calculations
            ADD CONSTRAINT serving_calculation_added_sugars_reference_ratio_check
            CHECK (
                (added_sugars_reference_numerator IS NULL AND added_sugars_reference_denominator IS NULL)
                OR (
                    added_sugars_reference_numerator IS NOT NULL
                    AND added_sugars_reference_denominator IS NOT NULL
                    AND added_sugars_reference_numerator NOT IN ('Infinity'::numeric, '-Infinity'::numeric, 'NaN'::numeric)
                    AND added_sugars_reference_denominator NOT IN ('Infinity'::numeric, 'NaN'::numeric)
                    AND added_sugars_reference_denominator > 0
                    AND scale(added_sugars_reference_numerator) = 0
                    AND scale(added_sugars_reference_denominator) = 0
                    AND (added_sugars_reference_numerator <> 0 OR added_sugars_reference_denominator = 1)
                )
            )
            SQL);

        Schema::table('serving_daily_value_regulatory_results', function (Blueprint $table): void {
            $table->text('value_source_type')->default('NUTRITION_CALCULATION');
            $table->decimal('source_reference_numerator')->nullable();
            $table->decimal('source_reference_denominator')->nullable();
        });

        DB::statement('ALTER TABLE serving_daily_value_regulatory_results ALTER COLUMN source_reference_numerator TYPE NUMERIC, ALTER COLUMN source_reference_denominator TYPE NUMERIC');
        DB::statement(<<<'SQL'
            ALTER TABLE serving_daily_value_regulatory_results
            ADD CONSTRAINT serving_regulatory_result_source_reference_ratio_check
            CHECK (
                (source_reference_numerator IS NULL AND source_reference_denominator IS NULL)
                OR (
                    source_reference_numerator IS NOT NULL
                    AND source_reference_denominator IS NOT NULL
                    AND source_reference_numerator NOT IN ('Infinity'::numeric, '-Infinity'::numeric, 'NaN'::numeric)
                    AND source_reference_denominator NOT IN ('Infinity'::numeric, 'NaN'::numeric)
                    AND source_reference_denominator > 0
                    AND scale(source_reference_numerator) = 0
                    AND scale(source_reference_denominator) = 0
                    AND (source_reference_numerator <> 0 OR source_reference_denominator = 1)
                )
            )
            SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE serving_daily_value_regulatory_results
            ADD CONSTRAINT serving_daily_value_regulatory_results_value_source_type_check
            CHECK (value_source_type IN ('NUTRITION_CALCULATION', 'ADDED_SUGARS_FORMULATION'))
            SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE serving_daily_value_regulatory_results
            DROP CONSTRAINT IF EXISTS serving_daily_value_regulatory_results_value_source_type_check
            SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE serving_daily_value_regulatory_results
            DROP CONSTRAINT IF EXISTS serving_regulatory_result_source_reference_ratio_check
            SQL);

        Schema::table('serving_daily_value_regulatory_results', function (Blueprint $table): void {
            $table->dropColumn([
                'value_source_type',
                'source_reference_numerator',
                'source_reference_denominator',
            ]);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE serving_daily_value_calculations
            DROP CONSTRAINT IF EXISTS serving_calculation_added_sugars_reference_ratio_check
            SQL);

        Schema::table('serving_daily_value_calculations', function (Blueprint $table): void {
            $table->dropForeign(['added_sugars_evaluation_id']);
            $table->dropColumn([
                'added_sugars_evaluation_id',
                'added_sugars_formulation_version_number',
                'added_sugars_validation_status',
                'added_sugars_is_regulatory_eligible',
                'added_sugars_is_confirmed_zero',
                'added_sugars_reference_numerator',
                'added_sugars_reference_denominator',
                'added_sugars_reference_unit',
            ]);
        });
    }
};
