<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('declared_nutrition_energy_components', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('declared_nutrition_calculation_id')
                ->constrained('declared_nutrition_calculations')
                ->restrictOnDelete();
            $table->text('basis');
            $table->text('ene_code');
            $table->text('component_name');
            $table->text('map_code')->nullable();
            $table->text('applicability_status');
            $table->text('participation');
            $table->decimal('factor_numerator')->nullable();
            $table->decimal('factor_denominator')->nullable();
            $table->text('factor_input_unit')->nullable();
            $table->text('factor_output_unit')->nullable();
            $table->text('input_declared_string')->nullable();
            $table->decimal('input_declared_numerator')->nullable();
            $table->decimal('input_declared_denominator')->nullable();
            $table->decimal('contribution_numerator')->nullable();
            $table->decimal('contribution_denominator')->nullable();
            $table->text('ene_technical_status');
            $table->text('ene_source_reference')->nullable();
            $table->text('ene_source_version')->nullable();
            foreach (['reference', 'source', 'version', 'scope', 'kind'] as $name) {
                $table->text("ene_evidence_{$name}")->nullable();
            }
            $table->text('ene_validated_by')->nullable();
            $table->date('ene_validated_on')->nullable();
            foreach (['reference', 'source', 'version', 'scope', 'kind'] as $name) {
                $table->text("applicability_evidence_{$name}")->nullable();
            }
            $table->text('applicability_validated_by')->nullable();
            $table->date('applicability_validated_on')->nullable();
            $table->text('non_applicability_justification')->nullable();
            $table->text('overlap_group')->nullable();
            $table->text('overlap_rule_code')->nullable();
            $table->text('overlap_rule_status')->nullable();
            $table->unique(['declared_nutrition_calculation_id', 'basis', 'ene_code'], 'declared_energy_components_unique');
        });

        DB::statement('ALTER TABLE declared_nutrition_energy_components ALTER COLUMN factor_numerator TYPE NUMERIC, ALTER COLUMN factor_denominator TYPE NUMERIC, ALTER COLUMN input_declared_numerator TYPE NUMERIC, ALTER COLUMN input_declared_denominator TYPE NUMERIC, ALTER COLUMN contribution_numerator TYPE NUMERIC, ALTER COLUMN contribution_denominator TYPE NUMERIC');
        DB::statement("ALTER TABLE declared_nutrition_energy_components ADD CONSTRAINT declared_energy_components_status_check CHECK (basis IN ('REFERENCE_100','PORTION') AND applicability_status IN ('APPLICABLE','NOT_APPLICABLE','UNKNOWN') AND participation IN ('INCLUDED','NON_APPLICABLE','BLOCKED') AND ene_technical_status IN ('AUTHORIZED','BLOCKED','UNAVAILABLE'))");

        foreach (['factor', 'input_declared', 'contribution'] as $prefix) {
            DB::statement("ALTER TABLE declared_nutrition_energy_components ADD CONSTRAINT declared_energy_{$prefix}_ratio_check CHECK (({$prefix}_numerator IS NULL AND {$prefix}_denominator IS NULL) OR ({$prefix}_numerator IS NOT NULL AND {$prefix}_denominator IS NOT NULL AND {$prefix}_numerator NOT IN ('Infinity'::numeric,'-Infinity'::numeric,'NaN'::numeric) AND {$prefix}_denominator NOT IN ('Infinity'::numeric,'NaN'::numeric) AND {$prefix}_denominator > 0 AND scale({$prefix}_numerator)=0 AND scale({$prefix}_denominator)=0 AND ({$prefix}_numerator <> 0 OR {$prefix}_denominator=1)))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('declared_nutrition_energy_components');
    }
};
