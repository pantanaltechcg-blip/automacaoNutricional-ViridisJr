<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regulatory_recalculation_policies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('declaration_profile_id')
                ->constrained('regulatory_declaration_profiles')
                ->restrictOnDelete();
            $table->text('policy_code');
            $table->text('normative_order_mode');
            $table->text('energy_reference_100_input_source');
            $table->text('energy_portion_input_source');
            $table->text('daily_value_input_source');
            $table->boolean('requires_complete_energy_inputs')->default(true);
            $table->text('technical_status');
            $table->text('source_reference')->nullable();
            foreach (['reference', 'source', 'version', 'scope', 'kind'] as $name) {
                $table->text("evidence_{$name}")->nullable();
            }
            $table->text('validated_by')->nullable();
            $table->date('validated_on')->nullable();
            $table->unique('declaration_profile_id', 'regulatory_recalc_policies_profile_unique');
            $table->unique(['declaration_profile_id', 'policy_code'], 'regulatory_recalc_policies_code_unique');
        });

        DB::statement("ALTER TABLE regulatory_recalculation_policies ADD CONSTRAINT regulatory_recalculation_policies_status_check CHECK (technical_status IN ('AUTHORIZED','BLOCKED','UNAVAILABLE') AND normative_order_mode IN ('DECLARE_COMPONENTS_THEN_ENERGY_AND_VD') AND energy_reference_100_input_source IN ('DECLARED_REFERENCE_100') AND energy_portion_input_source IN ('DECLARED_PORTION') AND daily_value_input_source IN ('DECLARED_PORTION_AND_DECLARED_ENERGY'))");
        DB::statement("ALTER TABLE regulatory_recalculation_policies ADD CONSTRAINT regulatory_recalculation_policies_evidence_shape_check CHECK ((evidence_reference IS NULL AND evidence_source IS NULL AND evidence_version IS NULL AND evidence_scope IS NULL AND evidence_kind IS NULL AND validated_by IS NULL AND validated_on IS NULL) OR (evidence_reference IS NOT NULL AND trim(evidence_reference) <> '' AND evidence_source IS NOT NULL AND trim(evidence_source) <> '' AND evidence_version IS NOT NULL AND trim(evidence_version) <> '' AND evidence_scope IS NOT NULL AND trim(evidence_scope) <> '' AND evidence_kind IN ('DOCUMENTED','TEST_FIXTURE') AND validated_by IS NOT NULL AND trim(validated_by) <> '' AND validated_on IS NOT NULL))");
        DB::statement("ALTER TABLE regulatory_recalculation_policies ADD CONSTRAINT regulatory_recalculation_policies_authorized_check CHECK (technical_status <> 'AUTHORIZED' OR (requires_complete_energy_inputs = true AND source_reference IS NOT NULL AND trim(source_reference) <> '' AND evidence_reference IS NOT NULL))");
    }

    public function down(): void
    {
        Schema::dropIfExists('regulatory_recalculation_policies');
    }
};
