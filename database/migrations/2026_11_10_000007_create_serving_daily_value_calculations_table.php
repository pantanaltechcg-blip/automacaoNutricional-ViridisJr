<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('serving_daily_value_calculations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('nutrition_calculation_id')->constrained('nutrition_calculations')->restrictOnDelete();
            $table->unsignedBigInteger('source_recipe_id');
            $table->text('source_rules_version');
            $table->decimal('source_yield_value');
            $table->text('source_yield_unit');
            $table->decimal('portion_value');
            $table->text('portion_unit');
            $table->text('household_measure_mode');
            $table->text('household_measure')->nullable();
            $table->text('portion_justification')->nullable();
            $table->text('portion_reference_code');
            $table->text('portion_reference_version');
            $table->text('portion_reference_source');
            foreach (['reference', 'source', 'version', 'scope', 'kind'] as $name) {
                $table->text("portion_evidence_{$name}");
            }
            $table->text('portion_validated_by');
            $table->date('portion_validated_on');
            $table->text('regulatory_profile_code');
            $table->text('regulatory_profile_version');
            $table->text('regulatory_profile_title');
            $table->text('regulatory_profile_status');
            $table->text('regulatory_profile_normative_source');
            $table->text('regulatory_profile_normative_version');
            $table->date('regulatory_profile_consulted_on');
            foreach (['reference', 'source', 'version', 'scope', 'kind'] as $name) {
                $table->text("regulatory_profile_evidence_{$name}")->nullable();
            }
            $table->text('regulatory_profile_validated_by')->nullable();
            $table->date('regulatory_profile_validated_on')->nullable();
            $table->decimal('energy_numerator')->nullable();
            $table->decimal('energy_denominator')->nullable();
            $table->text('energy_unit')->nullable();
            $table->boolean('energy_is_complete')->default(false);
            $table->text('energy_availability')->default('UNAVAILABLE');
            $table->text('rules_version')->default('serving-daily-values-v1');
            $table->timestamp('created_at')->useCurrent();
        });
        DB::statement('ALTER TABLE serving_daily_value_calculations ALTER COLUMN source_yield_value TYPE NUMERIC, ALTER COLUMN portion_value TYPE NUMERIC, ALTER COLUMN energy_numerator TYPE NUMERIC, ALTER COLUMN energy_denominator TYPE NUMERIC');
        DB::statement("ALTER TABLE serving_daily_value_calculations ADD CONSTRAINT serving_calculation_values_check CHECK (source_yield_value > 0 AND source_yield_value NOT IN ('Infinity'::numeric,'NaN'::numeric) AND portion_value > 0 AND portion_value NOT IN ('Infinity'::numeric,'NaN'::numeric) AND source_yield_unit IN ('g','ml') AND portion_unit = source_yield_unit AND household_measure_mode IN ('PROVIDED','WAIVED','NOT_APPLICABLE') AND regulatory_profile_status IN ('AUTHORIZED','BLOCKED') AND energy_availability IN ('AVAILABLE','PARTIAL','UNAVAILABLE'))");
        DB::statement("ALTER TABLE serving_daily_value_calculations ADD CONSTRAINT serving_calculation_energy_ratio_check CHECK ((energy_numerator IS NULL AND energy_denominator IS NULL AND energy_unit IS NULL) OR (energy_numerator IS NOT NULL AND energy_denominator IS NOT NULL AND energy_numerator NOT IN ('Infinity'::numeric,'-Infinity'::numeric,'NaN'::numeric) AND energy_denominator NOT IN ('Infinity'::numeric,'NaN'::numeric) AND energy_denominator > 0 AND scale(energy_numerator)=0 AND scale(energy_denominator)=0 AND (energy_numerator <> 0 OR energy_denominator=1) AND energy_unit='kcal'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('serving_daily_value_calculations');
    }
};
