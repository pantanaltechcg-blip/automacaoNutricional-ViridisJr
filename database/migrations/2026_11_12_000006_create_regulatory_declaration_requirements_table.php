<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regulatory_declaration_requirements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('declaration_profile_id')
                ->constrained('regulatory_declaration_profiles')
                ->restrictOnDelete();
            $table->text('requirement_code');
            $table->text('target_kind');
            $table->text('map_code')->nullable();
            $table->text('vdr_code')->nullable();
            $table->text('requirement_level');
            $table->text('applicability_status');
            $table->text('justification')->nullable();
            $table->text('technical_status');
            $table->text('source_reference')->nullable();
            foreach (['reference', 'source', 'version', 'scope', 'kind'] as $name) {
                $table->text("evidence_{$name}")->nullable();
            }
            $table->text('validated_by')->nullable();
            $table->date('validated_on')->nullable();
            $table->unique(['declaration_profile_id', 'requirement_code'], 'regulatory_decl_req_code_unique');
        });

        DB::statement("ALTER TABLE regulatory_declaration_requirements ADD CONSTRAINT regulatory_decl_req_status_check CHECK (target_kind IN ('COMPONENT','ENERGY','DAILY_VALUE') AND requirement_level IN ('MANDATORY','CONDITIONAL','VOLUNTARY') AND applicability_status IN ('APPLICABLE','NOT_APPLICABLE','UNKNOWN') AND technical_status IN ('AUTHORIZED','BLOCKED','UNAVAILABLE') AND ((target_kind = 'COMPONENT' AND map_code IS NOT NULL AND trim(map_code) <> '' AND vdr_code IS NULL) OR (target_kind = 'ENERGY' AND map_code IS NULL AND vdr_code IS NULL) OR (target_kind = 'DAILY_VALUE' AND vdr_code IS NOT NULL AND trim(vdr_code) <> '' AND map_code IS NULL)))");
        DB::statement("ALTER TABLE regulatory_declaration_requirements ADD CONSTRAINT regulatory_decl_req_evidence_shape_check CHECK ((evidence_reference IS NULL AND evidence_source IS NULL AND evidence_version IS NULL AND evidence_scope IS NULL AND evidence_kind IS NULL AND validated_by IS NULL AND validated_on IS NULL) OR (evidence_reference IS NOT NULL AND trim(evidence_reference) <> '' AND evidence_source IS NOT NULL AND trim(evidence_source) <> '' AND evidence_version IS NOT NULL AND trim(evidence_version) <> '' AND evidence_scope IS NOT NULL AND trim(evidence_scope) <> '' AND evidence_kind IN ('DOCUMENTED','TEST_FIXTURE') AND validated_by IS NOT NULL AND trim(validated_by) <> '' AND validated_on IS NOT NULL))");
        DB::statement("ALTER TABLE regulatory_declaration_requirements ADD CONSTRAINT regulatory_decl_req_authorized_check CHECK ((technical_status <> 'AUTHORIZED' OR (source_reference IS NOT NULL AND trim(source_reference) <> '' AND evidence_reference IS NOT NULL)) AND (applicability_status <> 'NOT_APPLICABLE' OR (justification IS NOT NULL AND trim(justification) <> '' AND evidence_reference IS NOT NULL)))");
    }

    public function down(): void
    {
        Schema::dropIfExists('regulatory_declaration_requirements');
    }
};
