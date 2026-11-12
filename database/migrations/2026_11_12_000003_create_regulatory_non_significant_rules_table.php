<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regulatory_non_significant_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('declaration_profile_id')
                ->constrained('regulatory_declaration_profiles')
                ->restrictOnDelete();
            $table->text('rule_code');
            $table->text('target_kind');
            $table->text('map_code')->nullable();
            $table->text('basis');
            $table->text('technical_status');
            $table->text('comparison_operator')->nullable();
            $table->decimal('threshold_numerator')->nullable();
            $table->decimal('threshold_denominator')->nullable();
            $table->text('threshold_unit')->nullable();
            $table->text('declared_string')->nullable();
            $table->decimal('declared_numerator')->nullable();
            $table->decimal('declared_denominator')->nullable();
            $table->text('justification')->nullable();
            $table->text('source_reference')->nullable();
            foreach (['reference', 'source', 'version', 'scope', 'kind'] as $name) {
                $table->text("evidence_{$name}")->nullable();
            }
            $table->text('validated_by')->nullable();
            $table->date('validated_on')->nullable();
            $table->unique(['declaration_profile_id', 'rule_code'], 'regulatory_nsq_rule_code_unique');
        });

        DB::statement('ALTER TABLE regulatory_non_significant_rules ALTER COLUMN threshold_numerator TYPE NUMERIC, ALTER COLUMN threshold_denominator TYPE NUMERIC, ALTER COLUMN declared_numerator TYPE NUMERIC, ALTER COLUMN declared_denominator TYPE NUMERIC');
        DB::statement("CREATE UNIQUE INDEX regulatory_nsq_component_unique ON regulatory_non_significant_rules (declaration_profile_id, map_code, basis) WHERE target_kind = 'COMPONENT'");
        DB::statement("CREATE UNIQUE INDEX regulatory_nsq_energy_unique ON regulatory_non_significant_rules (declaration_profile_id, basis) WHERE target_kind = 'ENERGY'");
        DB::statement("ALTER TABLE regulatory_non_significant_rules ADD CONSTRAINT regulatory_nsq_status_check CHECK (target_kind IN ('COMPONENT','ENERGY') AND basis IN ('REFERENCE_100','PORTION') AND technical_status IN ('AUTHORIZED','BLOCKED','NOT_APPLICABLE','UNAVAILABLE') AND (comparison_operator IS NULL OR comparison_operator IN ('LTE','LT')) AND ((target_kind = 'COMPONENT' AND map_code IS NOT NULL AND trim(map_code) <> '') OR (target_kind = 'ENERGY' AND map_code IS NULL)))");

        foreach (['threshold', 'declared'] as $prefix) {
            DB::statement("ALTER TABLE regulatory_non_significant_rules ADD CONSTRAINT regulatory_nsq_{$prefix}_ratio_check CHECK (({$prefix}_numerator IS NULL AND {$prefix}_denominator IS NULL) OR ({$prefix}_numerator IS NOT NULL AND {$prefix}_denominator IS NOT NULL AND {$prefix}_numerator NOT IN ('Infinity'::numeric,'-Infinity'::numeric,'NaN'::numeric) AND {$prefix}_denominator NOT IN ('Infinity'::numeric,'NaN'::numeric) AND {$prefix}_numerator >= 0 AND {$prefix}_denominator > 0 AND scale({$prefix}_numerator)=0 AND scale({$prefix}_denominator)=0 AND ({$prefix}_numerator <> 0 OR {$prefix}_denominator=1)))");
        }

        DB::statement("ALTER TABLE regulatory_non_significant_rules ADD CONSTRAINT regulatory_nsq_evidence_shape_check CHECK ((evidence_reference IS NULL AND evidence_source IS NULL AND evidence_version IS NULL AND evidence_scope IS NULL AND evidence_kind IS NULL AND validated_by IS NULL AND validated_on IS NULL) OR (evidence_reference IS NOT NULL AND trim(evidence_reference) <> '' AND evidence_source IS NOT NULL AND trim(evidence_source) <> '' AND evidence_version IS NOT NULL AND trim(evidence_version) <> '' AND evidence_scope IS NOT NULL AND trim(evidence_scope) <> '' AND evidence_kind IN ('DOCUMENTED','TEST_FIXTURE') AND validated_by IS NOT NULL AND trim(validated_by) <> '' AND validated_on IS NOT NULL))");
        DB::statement("ALTER TABLE regulatory_non_significant_rules ADD CONSTRAINT regulatory_nsq_authorized_check CHECK ((technical_status <> 'AUTHORIZED' OR (comparison_operator IS NOT NULL AND threshold_numerator IS NOT NULL AND threshold_unit IS NOT NULL AND trim(threshold_unit) <> '' AND declared_string IS NOT NULL AND trim(declared_string) <> '' AND declared_numerator IS NOT NULL AND source_reference IS NOT NULL AND trim(source_reference) <> '' AND evidence_reference IS NOT NULL)) AND (technical_status <> 'NOT_APPLICABLE' OR (justification IS NOT NULL AND trim(justification) <> '' AND evidence_reference IS NOT NULL)))");
    }

    public function down(): void
    {
        Schema::dropIfExists('regulatory_non_significant_rules');
    }
};
