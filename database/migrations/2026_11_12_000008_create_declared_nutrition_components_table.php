<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('declared_nutrition_components', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('declared_nutrition_calculation_id')
                ->constrained('declared_nutrition_calculations')
                ->restrictOnDelete();
            $table->foreignId('serving_daily_value_regulatory_result_id')
                ->nullable()
                ->constrained('serving_daily_value_regulatory_results')
                ->restrictOnDelete();
            $table->text('map_code');
            $table->text('component_name');
            $table->text('map_technical_status');
            $table->unsignedBigInteger('nutrient_id')->nullable();
            $table->text('nutrient_name')->nullable();
            $table->text('source_unit')->nullable();
            $table->text('regulatory_unit');
            $table->text('map_source_reference')->nullable();
            foreach (['reference', 'source', 'version', 'scope', 'kind'] as $name) {
                $table->text("map_evidence_{$name}")->nullable();
            }
            $table->text('map_validated_by')->nullable();
            $table->date('map_validated_on')->nullable();

            $table->text('conversion_status');
            $table->text('conversion_source_unit')->nullable();
            $table->text('conversion_target_unit')->nullable();
            $table->decimal('conversion_numerator')->nullable();
            $table->decimal('conversion_denominator')->nullable();
            foreach (['reference', 'source', 'version', 'scope', 'kind'] as $name) {
                $table->text("conversion_evidence_{$name}")->nullable();
            }
            $table->text('conversion_validated_by')->nullable();
            $table->date('conversion_validated_on')->nullable();

            $table->boolean('source_is_complete')->default(false);
            $table->boolean('source_has_missing_relation')->default(false);

            foreach (['reference_100', 'portion'] as $basisPrefix) {
                $table->decimal("{$basisPrefix}_source_numerator")->nullable();
                $table->decimal("{$basisPrefix}_source_denominator")->nullable();
                $table->decimal("{$basisPrefix}_regulatory_numerator")->nullable();
                $table->decimal("{$basisPrefix}_regulatory_denominator")->nullable();
                $table->boolean("{$basisPrefix}_is_exact_zero")->default(false);

                $table->text("{$basisPrefix}_nsq_rule_code")->nullable();
                $table->text("{$basisPrefix}_nsq_rule_status")->nullable();
                $table->boolean("{$basisPrefix}_nsq_applied")->default(false);
                foreach (['reference', 'source', 'version', 'scope', 'kind'] as $name) {
                    $table->text("{$basisPrefix}_nsq_evidence_{$name}")->nullable();
                }
                $table->text("{$basisPrefix}_nsq_validated_by")->nullable();
                $table->date("{$basisPrefix}_nsq_validated_on")->nullable();

                $table->text("{$basisPrefix}_rounding_rule_code")->nullable();
                $table->text("{$basisPrefix}_rounding_rule_status")->nullable();
                $table->text("{$basisPrefix}_rounding_tie_breaker")->nullable();
                $table->decimal("{$basisPrefix}_rounding_step_numerator")->nullable();
                $table->decimal("{$basisPrefix}_rounding_step_denominator")->nullable();
                $table->unsignedInteger("{$basisPrefix}_rounding_decimal_places")->nullable();
                foreach (['reference', 'source', 'version', 'scope', 'kind'] as $name) {
                    $table->text("{$basisPrefix}_rounding_evidence_{$name}")->nullable();
                }
                $table->text("{$basisPrefix}_rounding_validated_by")->nullable();
                $table->date("{$basisPrefix}_rounding_validated_on")->nullable();

                $table->text("{$basisPrefix}_declared_string")->nullable();
                $table->decimal("{$basisPrefix}_declared_numerator")->nullable();
                $table->decimal("{$basisPrefix}_declared_denominator")->nullable();
                $table->boolean("{$basisPrefix}_is_complete")->default(false);
                $table->text("{$basisPrefix}_availability");
            }

            $table->unique(['declared_nutrition_calculation_id', 'map_code'], 'declared_components_map_unique');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE declared_nutrition_components
                ALTER COLUMN conversion_numerator TYPE NUMERIC,
                ALTER COLUMN conversion_denominator TYPE NUMERIC,
                ALTER COLUMN reference_100_source_numerator TYPE NUMERIC,
                ALTER COLUMN reference_100_source_denominator TYPE NUMERIC,
                ALTER COLUMN reference_100_regulatory_numerator TYPE NUMERIC,
                ALTER COLUMN reference_100_regulatory_denominator TYPE NUMERIC,
                ALTER COLUMN reference_100_rounding_step_numerator TYPE NUMERIC,
                ALTER COLUMN reference_100_rounding_step_denominator TYPE NUMERIC,
                ALTER COLUMN reference_100_declared_numerator TYPE NUMERIC,
                ALTER COLUMN reference_100_declared_denominator TYPE NUMERIC,
                ALTER COLUMN portion_source_numerator TYPE NUMERIC,
                ALTER COLUMN portion_source_denominator TYPE NUMERIC,
                ALTER COLUMN portion_regulatory_numerator TYPE NUMERIC,
                ALTER COLUMN portion_regulatory_denominator TYPE NUMERIC,
                ALTER COLUMN portion_rounding_step_numerator TYPE NUMERIC,
                ALTER COLUMN portion_rounding_step_denominator TYPE NUMERIC,
                ALTER COLUMN portion_declared_numerator TYPE NUMERIC,
                ALTER COLUMN portion_declared_denominator TYPE NUMERIC
            SQL);

        DB::statement("ALTER TABLE declared_nutrition_components ADD CONSTRAINT declared_components_status_check CHECK (map_technical_status IN ('AUTHORIZED','BLOCKED','UNAVAILABLE') AND conversion_status IN ('NOT_REQUIRED','AUTHORIZED','BLOCKED') AND reference_100_availability IN ('AVAILABLE','UNAVAILABLE') AND portion_availability IN ('AVAILABLE','UNAVAILABLE'))");

        foreach ([
            'conversion',
            'reference_100_source',
            'reference_100_regulatory',
            'reference_100_rounding_step',
            'reference_100_declared',
            'portion_source',
            'portion_regulatory',
            'portion_rounding_step',
            'portion_declared',
        ] as $prefix) {
            DB::statement("ALTER TABLE declared_nutrition_components ADD CONSTRAINT declared_components_{$prefix}_ratio_check CHECK (({$prefix}_numerator IS NULL AND {$prefix}_denominator IS NULL) OR ({$prefix}_numerator IS NOT NULL AND {$prefix}_denominator IS NOT NULL AND {$prefix}_numerator NOT IN ('Infinity'::numeric,'-Infinity'::numeric,'NaN'::numeric) AND {$prefix}_denominator NOT IN ('Infinity'::numeric,'NaN'::numeric) AND {$prefix}_denominator > 0 AND scale({$prefix}_numerator)=0 AND scale({$prefix}_denominator)=0 AND ({$prefix}_numerator <> 0 OR {$prefix}_denominator=1)))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('declared_nutrition_components');
    }
};
