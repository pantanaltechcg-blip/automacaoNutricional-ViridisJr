<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regulatory_energy_applicabilities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('nutrition_calculation_id')->constrained('nutrition_calculations')->restrictOnDelete();
            $table->foreignId('profile_id')->constrained('regulatory_profiles')->restrictOnDelete();
            $table->foreignId('energy_component_id')->constrained('regulatory_energy_components')->restrictOnDelete();
            $table->text('status');
            $table->text('justification')->nullable();
            $table->text('source_reference')->nullable();
            foreach (['reference', 'source', 'version', 'scope', 'kind'] as $name) {
                $table->text("evidence_{$name}")->nullable();
            }
            $table->text('validated_by')->nullable();
            $table->date('validated_on')->nullable();
            $table->unique(['nutrition_calculation_id', 'profile_id', 'energy_component_id'], 'energy_applicability_unique');
        });
        DB::statement("ALTER TABLE regulatory_energy_applicabilities ADD CONSTRAINT regulatory_energy_applicability_status_check CHECK (status IN ('APPLICABLE','NOT_APPLICABLE','UNKNOWN'))");
        DB::statement("ALTER TABLE regulatory_energy_applicabilities ADD CONSTRAINT regulatory_energy_applicability_evidence_check CHECK (status = 'UNKNOWN' OR (evidence_reference IS NOT NULL AND evidence_source IS NOT NULL AND evidence_version IS NOT NULL AND evidence_scope IS NOT NULL AND evidence_kind IN ('DOCUMENTED','TEST_FIXTURE') AND validated_by IS NOT NULL AND validated_on IS NOT NULL AND (status <> 'NOT_APPLICABLE' OR (justification IS NOT NULL AND btrim(justification) <> ''))))");
        DB::statement("ALTER TABLE regulatory_energy_applicabilities ADD CONSTRAINT regulatory_energy_applicability_evidence_shape_check CHECK ((evidence_reference IS NULL AND evidence_source IS NULL AND evidence_version IS NULL AND evidence_scope IS NULL AND evidence_kind IS NULL AND validated_by IS NULL AND validated_on IS NULL) OR (evidence_reference IS NOT NULL AND evidence_source IS NOT NULL AND evidence_version IS NOT NULL AND evidence_scope IS NOT NULL AND evidence_kind IN ('DOCUMENTED','TEST_FIXTURE') AND validated_by IS NOT NULL AND validated_on IS NOT NULL))");
    }

    public function down(): void
    {
        Schema::dropIfExists('regulatory_energy_applicabilities');
    }
};
