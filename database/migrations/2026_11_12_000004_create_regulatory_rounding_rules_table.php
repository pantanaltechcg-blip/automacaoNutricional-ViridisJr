<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regulatory_rounding_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('declaration_profile_id')
                ->constrained('regulatory_declaration_profiles')
                ->restrictOnDelete();
            $table->text('rule_code');
            $table->text('target_kind');
            $table->text('map_code')->nullable();
            $table->text('vdr_code')->nullable();
            $table->text('vdr_numerator_source')->nullable();
            $table->text('basis');
            $table->text('unit');
            $table->text('tie_breaker');
            $table->text('technical_status');
            $table->text('source_reference')->nullable();
            foreach (['reference', 'source', 'version', 'scope', 'kind'] as $name) {
                $table->text("evidence_{$name}")->nullable();
            }
            $table->text('validated_by')->nullable();
            $table->date('validated_on')->nullable();
            $table->unique(['declaration_profile_id', 'rule_code'], 'regulatory_rounding_rule_code_unique');
        });

        DB::statement("CREATE UNIQUE INDEX regulatory_rounding_component_unique ON regulatory_rounding_rules (declaration_profile_id, basis, map_code) WHERE target_kind = 'COMPONENT'");
        DB::statement("CREATE UNIQUE INDEX regulatory_rounding_energy_unique ON regulatory_rounding_rules (declaration_profile_id, basis) WHERE target_kind = 'ENERGY'");
        DB::statement("CREATE UNIQUE INDEX regulatory_rounding_daily_value_unique ON regulatory_rounding_rules (declaration_profile_id, basis, vdr_code) WHERE target_kind = 'DAILY_VALUE'");
        DB::statement("ALTER TABLE regulatory_rounding_rules ADD CONSTRAINT regulatory_rounding_rules_status_check CHECK (target_kind IN ('COMPONENT','ENERGY','DAILY_VALUE') AND tie_breaker IN ('HALF_UP') AND technical_status IN ('AUTHORIZED','BLOCKED','UNAVAILABLE') AND trim(unit) <> '' AND ((target_kind = 'COMPONENT' AND map_code IS NOT NULL AND trim(map_code) <> '' AND vdr_code IS NULL AND vdr_numerator_source IS NULL AND basis IN ('REFERENCE_100','PORTION')) OR (target_kind = 'ENERGY' AND map_code IS NULL AND vdr_code IS NULL AND vdr_numerator_source IS NULL AND basis IN ('REFERENCE_100','PORTION')) OR (target_kind = 'DAILY_VALUE' AND vdr_code IS NOT NULL AND trim(vdr_code) <> '' AND map_code IS NULL AND basis = 'PERCENT_VD' AND (vdr_numerator_source IS NULL OR vdr_numerator_source IN ('DECLARED_COMPONENT','DECLARED_PORTION_ENERGY')))))");
        DB::statement("ALTER TABLE regulatory_rounding_rules ADD CONSTRAINT regulatory_rounding_rules_evidence_shape_check CHECK ((evidence_reference IS NULL AND evidence_source IS NULL AND evidence_version IS NULL AND evidence_scope IS NULL AND evidence_kind IS NULL AND validated_by IS NULL AND validated_on IS NULL) OR (evidence_reference IS NOT NULL AND trim(evidence_reference) <> '' AND evidence_source IS NOT NULL AND trim(evidence_source) <> '' AND evidence_version IS NOT NULL AND trim(evidence_version) <> '' AND evidence_scope IS NOT NULL AND trim(evidence_scope) <> '' AND evidence_kind IN ('DOCUMENTED','TEST_FIXTURE') AND validated_by IS NOT NULL AND trim(validated_by) <> '' AND validated_on IS NOT NULL))");
        DB::statement("ALTER TABLE regulatory_rounding_rules ADD CONSTRAINT regulatory_rounding_rules_authorized_check CHECK (technical_status <> 'AUTHORIZED' OR (source_reference IS NOT NULL AND trim(source_reference) <> '' AND evidence_reference IS NOT NULL AND (target_kind <> 'DAILY_VALUE' OR vdr_numerator_source IS NOT NULL)))");
    }

    public function down(): void
    {
        Schema::dropIfExists('regulatory_rounding_rules');
    }
};
