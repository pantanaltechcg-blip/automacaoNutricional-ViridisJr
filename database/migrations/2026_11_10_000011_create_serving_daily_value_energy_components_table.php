<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('serving_daily_value_energy_components', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('serving_daily_value_calculation_id')->constrained('serving_daily_value_calculations')->restrictOnDelete();
            $table->text('ene_code');
            $table->text('component_name');
            $table->text('map_code')->nullable();
            $table->text('applicability_status');
            $table->text('participation');
            foreach (['factor', 'input', 'contribution'] as $prefix) {
                $table->decimal("{$prefix}_numerator")->nullable();
                $table->decimal("{$prefix}_denominator")->nullable();
            }
            $table->text('factor_input_unit')->nullable();
            $table->text('factor_output_unit')->nullable();
            $table->text('source_reference')->nullable();
            $table->text('source_version')->nullable();
            $table->text('technical_status');
            foreach (['reference', 'source', 'version', 'scope', 'kind'] as $name) {
                $table->text("ene_evidence_{$name}")->nullable();
            }
            $table->text('ene_validated_by')->nullable();
            $table->date('ene_validated_on')->nullable();
            $table->text('overlap_group')->nullable();
            $table->text('overlap_rule_code')->nullable();
            $table->text('overlap_rule_status');
            foreach (['reference', 'source', 'version', 'scope', 'kind'] as $name) {
                $table->text("applicability_evidence_{$name}")->nullable();
            }
            $table->text('applicability_validated_by')->nullable();
            $table->date('applicability_validated_on')->nullable();
            $table->text('non_applicability_justification')->nullable();
            $table->text('non_applicability_source')->nullable();
            $table->unique(['serving_daily_value_calculation_id', 'ene_code'], 'serving_energy_component_unique');
        });
        DB::statement('ALTER TABLE serving_daily_value_energy_components ALTER COLUMN factor_numerator TYPE NUMERIC, ALTER COLUMN factor_denominator TYPE NUMERIC, ALTER COLUMN input_numerator TYPE NUMERIC, ALTER COLUMN input_denominator TYPE NUMERIC, ALTER COLUMN contribution_numerator TYPE NUMERIC, ALTER COLUMN contribution_denominator TYPE NUMERIC');
        DB::statement("ALTER TABLE serving_daily_value_energy_components ADD CONSTRAINT serving_energy_component_status_check CHECK (applicability_status IN ('APPLICABLE','NOT_APPLICABLE','UNKNOWN') AND participation IN ('INCLUDED','NON_APPLICABLE','BLOCKED') AND technical_status IN ('AUTHORIZED','BLOCKED','UNAVAILABLE') AND overlap_rule_status IN ('AUTHORIZED','BLOCKED','NOT_REQUIRED'))");
        foreach (['factor', 'input', 'contribution'] as $prefix) {
            DB::statement("ALTER TABLE serving_daily_value_energy_components ADD CONSTRAINT serving_energy_{$prefix}_ratio_check CHECK (({$prefix}_numerator IS NULL AND {$prefix}_denominator IS NULL) OR ({$prefix}_numerator IS NOT NULL AND {$prefix}_denominator IS NOT NULL AND {$prefix}_numerator NOT IN ('Infinity'::numeric,'-Infinity'::numeric,'NaN'::numeric) AND {$prefix}_denominator NOT IN ('Infinity'::numeric,'NaN'::numeric) AND {$prefix}_denominator > 0 AND scale({$prefix}_numerator)=0 AND scale({$prefix}_denominator)=0 AND ({$prefix}_numerator <> 0 OR {$prefix}_denominator=1)))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('serving_daily_value_energy_components');
    }
};
