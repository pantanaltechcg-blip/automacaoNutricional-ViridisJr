<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('declared_nutrition_calculations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('serving_daily_value_calculation_id')
                ->constrained('serving_daily_value_calculations')
                ->restrictOnDelete();
            $table->foreignId('source_nutrition_calculation_id')
                ->constrained('nutrition_calculations')
                ->restrictOnDelete();
            $table->unsignedBigInteger('source_recipe_id');
            $table->text('source_003_rules_version');
            $table->text('source_004_rules_version');
            $table->decimal('source_yield_value');
            $table->text('source_yield_unit');
            $table->decimal('portion_value');
            $table->text('portion_unit');
            $table->text('household_measure_mode');
            $table->text('household_measure')->nullable();
            $table->text('portion_justification')->nullable();
            $table->text('portion_reference_technical_status');
            foreach (['reference', 'source', 'version', 'scope', 'kind'] as $name) {
                $table->text("portion_evidence_{$name}");
            }
            $table->text('portion_validated_by');
            $table->date('portion_validated_on');

            $table->text('regulatory_profile_code');
            $table->text('regulatory_profile_version');
            $table->text('regulatory_profile_status');
            $table->text('regulatory_profile_normative_source');
            $table->text('regulatory_profile_normative_version');

            $table->text('declaration_profile_code');
            $table->text('declaration_profile_version');
            $table->text('declaration_profile_title');
            $table->text('declaration_profile_status');
            $table->text('declaration_profile_normative_source');
            $table->text('declaration_profile_normative_version');
            $table->date('declaration_profile_consulted_on');
            $table->unsignedBigInteger('declaration_profile_scope_recipe_id')->nullable();
            $table->unsignedBigInteger('declaration_profile_scope_nutrition_calculation_id')->nullable();
            foreach (['reference', 'source', 'version', 'scope', 'kind'] as $name) {
                $table->text("declaration_profile_evidence_{$name}")->nullable();
            }
            $table->text('declaration_profile_validated_by')->nullable();
            $table->date('declaration_profile_validated_on')->nullable();

            $table->text('recalculation_policy_code')->nullable();
            $table->text('recalculation_policy_status')->nullable();
            $table->text('recalculation_normative_order_mode')->nullable();
            $table->boolean('recalculation_requires_complete_energy_inputs')->nullable();
            $table->text('recalculation_policy_source_reference')->nullable();
            foreach (['reference', 'source', 'version', 'scope', 'kind'] as $name) {
                $table->text("recalculation_policy_evidence_{$name}")->nullable();
            }
            $table->text('recalculation_policy_validated_by')->nullable();
            $table->date('recalculation_policy_validated_on')->nullable();

            $table->decimal('source_portion_energy_numerator')->nullable();
            $table->decimal('source_portion_energy_denominator')->nullable();
            $table->text('source_portion_energy_unit')->nullable();
            $table->boolean('source_portion_energy_is_complete')->nullable();
            $table->text('source_portion_energy_availability')->nullable();

            foreach (['reference_100', 'portion'] as $basisPrefix) {
                $table->decimal("{$basisPrefix}_exact_energy_numerator")->nullable();
                $table->decimal("{$basisPrefix}_exact_energy_denominator")->nullable();
                $table->boolean("{$basisPrefix}_energy_nsq_applied")->default(false);
                $table->text("{$basisPrefix}_energy_nsq_rule_code")->nullable();
                $table->text("{$basisPrefix}_energy_nsq_rule_status")->nullable();
                foreach (['reference', 'source', 'version', 'scope', 'kind'] as $name) {
                    $table->text("{$basisPrefix}_energy_nsq_evidence_{$name}")->nullable();
                }
                $table->text("{$basisPrefix}_energy_nsq_validated_by")->nullable();
                $table->date("{$basisPrefix}_energy_nsq_validated_on")->nullable();

                $table->text("{$basisPrefix}_energy_rounding_rule_code")->nullable();
                $table->text("{$basisPrefix}_energy_rounding_rule_status")->nullable();
                $table->text("{$basisPrefix}_energy_rounding_tie_breaker")->nullable();
                $table->decimal("{$basisPrefix}_energy_rounding_step_numerator")->nullable();
                $table->decimal("{$basisPrefix}_energy_rounding_step_denominator")->nullable();
                $table->unsignedInteger("{$basisPrefix}_energy_rounding_decimal_places")->nullable();
                foreach (['reference', 'source', 'version', 'scope', 'kind'] as $name) {
                    $table->text("{$basisPrefix}_energy_rounding_evidence_{$name}")->nullable();
                }
                $table->text("{$basisPrefix}_energy_rounding_validated_by")->nullable();
                $table->date("{$basisPrefix}_energy_rounding_validated_on")->nullable();

                $table->text("{$basisPrefix}_declared_energy_string")->nullable();
                $table->decimal("{$basisPrefix}_declared_energy_numerator")->nullable();
                $table->decimal("{$basisPrefix}_declared_energy_denominator")->nullable();
                $table->text("{$basisPrefix}_energy_unit")->nullable();
                $table->boolean("{$basisPrefix}_energy_is_complete")->default(false);
                $table->text("{$basisPrefix}_energy_availability")->default('UNAVAILABLE');
            }

            $table->boolean('is_label_ready')->default(false);
            $table->text('rules_version')->default('declared-values-rounding-v1');
            $table->timestamp('created_at')->useCurrent();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE declared_nutrition_calculations
                ALTER COLUMN source_yield_value TYPE NUMERIC,
                ALTER COLUMN portion_value TYPE NUMERIC,
                ALTER COLUMN source_portion_energy_numerator TYPE NUMERIC,
                ALTER COLUMN source_portion_energy_denominator TYPE NUMERIC,
                ALTER COLUMN reference_100_exact_energy_numerator TYPE NUMERIC,
                ALTER COLUMN reference_100_exact_energy_denominator TYPE NUMERIC,
                ALTER COLUMN reference_100_energy_rounding_step_numerator TYPE NUMERIC,
                ALTER COLUMN reference_100_energy_rounding_step_denominator TYPE NUMERIC,
                ALTER COLUMN reference_100_declared_energy_numerator TYPE NUMERIC,
                ALTER COLUMN reference_100_declared_energy_denominator TYPE NUMERIC,
                ALTER COLUMN portion_exact_energy_numerator TYPE NUMERIC,
                ALTER COLUMN portion_exact_energy_denominator TYPE NUMERIC,
                ALTER COLUMN portion_energy_rounding_step_numerator TYPE NUMERIC,
                ALTER COLUMN portion_energy_rounding_step_denominator TYPE NUMERIC,
                ALTER COLUMN portion_declared_energy_numerator TYPE NUMERIC,
                ALTER COLUMN portion_declared_energy_denominator TYPE NUMERIC
            SQL);

        DB::statement("ALTER TABLE declared_nutrition_calculations ADD CONSTRAINT declared_calculations_status_check CHECK (source_yield_value > 0 AND source_yield_value NOT IN ('Infinity'::numeric,'NaN'::numeric) AND portion_value > 0 AND portion_value NOT IN ('Infinity'::numeric,'NaN'::numeric) AND source_yield_unit IN ('g','ml') AND portion_unit = source_yield_unit AND household_measure_mode IN ('PROVIDED','WAIVED','NOT_APPLICABLE') AND portion_reference_technical_status IN ('AUTHORIZED','BLOCKED') AND regulatory_profile_status IN ('AUTHORIZED','BLOCKED') AND declaration_profile_status IN ('AUTHORIZED','BLOCKED') AND reference_100_energy_availability IN ('AVAILABLE','UNAVAILABLE') AND portion_energy_availability IN ('AVAILABLE','UNAVAILABLE'))");

        foreach ([
            'source_portion_energy',
            'reference_100_exact_energy',
            'reference_100_declared_energy',
            'reference_100_energy_rounding_step',
            'portion_exact_energy',
            'portion_declared_energy',
            'portion_energy_rounding_step',
        ] as $prefix) {
            DB::statement("ALTER TABLE declared_nutrition_calculations ADD CONSTRAINT declared_calculations_{$prefix}_ratio_check CHECK (({$prefix}_numerator IS NULL AND {$prefix}_denominator IS NULL) OR ({$prefix}_numerator IS NOT NULL AND {$prefix}_denominator IS NOT NULL AND {$prefix}_numerator NOT IN ('Infinity'::numeric,'-Infinity'::numeric,'NaN'::numeric) AND {$prefix}_denominator NOT IN ('Infinity'::numeric,'NaN'::numeric) AND {$prefix}_denominator > 0 AND scale({$prefix}_numerator)=0 AND scale({$prefix}_denominator)=0 AND ({$prefix}_numerator <> 0 OR {$prefix}_denominator=1)))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('declared_nutrition_calculations');
    }
};
