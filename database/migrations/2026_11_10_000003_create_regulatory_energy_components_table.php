<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regulatory_energy_components', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('profile_id')->constrained('regulatory_profiles')->restrictOnDelete();
            $table->text('ene_code');
            $table->text('component_name');
            $table->foreignId('mapping_id')->nullable()->constrained('regulatory_nutrient_mappings')->restrictOnDelete();
            $table->decimal('factor_numerator')->nullable();
            $table->decimal('factor_denominator')->nullable();
            $table->text('factor_input_unit')->nullable();
            $table->text('factor_output_unit')->nullable();
            $table->text('technical_status');
            $table->text('source_reference')->nullable();
            foreach (['reference', 'source', 'version', 'scope', 'kind'] as $name) {
                $table->text("evidence_{$name}")->nullable();
            }
            $table->text('validated_by')->nullable();
            $table->date('validated_on')->nullable();
            $table->text('overlap_group')->nullable();
            $table->text('overlap_rule_code')->nullable();
            $table->text('overlap_rule_status')->default('NOT_REQUIRED');
            $table->unique(['profile_id', 'ene_code']);
        });
        DB::statement('ALTER TABLE regulatory_energy_components ALTER COLUMN factor_numerator TYPE NUMERIC, ALTER COLUMN factor_denominator TYPE NUMERIC');
        DB::statement("ALTER TABLE regulatory_energy_components ADD CONSTRAINT regulatory_energy_status_check CHECK (technical_status IN ('AUTHORIZED','BLOCKED','UNAVAILABLE') AND overlap_rule_status IN ('AUTHORIZED','BLOCKED','NOT_REQUIRED'))");
        DB::statement("ALTER TABLE regulatory_energy_components ADD CONSTRAINT regulatory_energy_factor_check CHECK ((factor_numerator IS NULL AND factor_denominator IS NULL) OR (factor_numerator IS NOT NULL AND factor_denominator IS NOT NULL AND factor_numerator NOT IN ('Infinity'::numeric,'-Infinity'::numeric,'NaN'::numeric) AND factor_denominator NOT IN ('Infinity'::numeric,'NaN'::numeric) AND factor_denominator > 0 AND scale(factor_numerator)=0 AND scale(factor_denominator)=0 AND (factor_numerator <> 0 OR factor_denominator=1)))");
        DB::statement("ALTER TABLE regulatory_energy_components ADD CONSTRAINT regulatory_energy_authorized_check CHECK (technical_status <> 'AUTHORIZED' OR (mapping_id IS NOT NULL AND factor_numerator IS NOT NULL AND factor_input_unit IS NOT NULL AND factor_output_unit IS NOT NULL AND evidence_reference IS NOT NULL AND evidence_source IS NOT NULL AND evidence_version IS NOT NULL AND evidence_scope IS NOT NULL AND evidence_kind IN ('DOCUMENTED','TEST_FIXTURE') AND validated_by IS NOT NULL AND validated_on IS NOT NULL))");
        DB::statement("ALTER TABLE regulatory_energy_components ADD CONSTRAINT regulatory_energy_evidence_shape_check CHECK ((evidence_reference IS NULL AND evidence_source IS NULL AND evidence_version IS NULL AND evidence_scope IS NULL AND evidence_kind IS NULL AND validated_by IS NULL AND validated_on IS NULL) OR (evidence_reference IS NOT NULL AND evidence_source IS NOT NULL AND evidence_version IS NOT NULL AND evidence_scope IS NOT NULL AND evidence_kind IN ('DOCUMENTED','TEST_FIXTURE') AND validated_by IS NOT NULL AND validated_on IS NOT NULL))");
    }

    public function down(): void
    {
        Schema::dropIfExists('regulatory_energy_components');
    }
};
