<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regulatory_nutrient_mappings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('profile_id')->constrained('regulatory_profiles')->restrictOnDelete();
            $table->text('map_code');
            $table->text('component_name');
            $table->foreignId('nutrient_id')->nullable()->constrained('nutrients')->restrictOnDelete();
            $table->text('source_unit')->nullable();
            $table->text('regulatory_unit');
            $table->text('technical_status');
            $table->text('source_reference')->nullable();
            foreach (['reference', 'source', 'version', 'scope', 'kind'] as $name) {
                $table->text("evidence_{$name}")->nullable();
            }
            $table->text('validated_by')->nullable();
            $table->date('validated_on')->nullable();
            $table->text('conversion_status');
            $table->decimal('conversion_numerator')->nullable();
            $table->decimal('conversion_denominator')->nullable();
            $table->text('conversion_source_unit')->nullable();
            $table->text('conversion_target_unit')->nullable();
            foreach (['reference', 'source', 'version', 'scope', 'kind'] as $name) {
                $table->text("conversion_evidence_{$name}")->nullable();
            }
            $table->text('conversion_validated_by')->nullable();
            $table->date('conversion_validated_on')->nullable();
            $table->unique(['profile_id', 'map_code']);
        });
        DB::statement('ALTER TABLE regulatory_nutrient_mappings ALTER COLUMN conversion_numerator TYPE NUMERIC, ALTER COLUMN conversion_denominator TYPE NUMERIC');
        DB::statement("ALTER TABLE regulatory_nutrient_mappings ADD CONSTRAINT regulatory_mappings_status_check CHECK (technical_status IN ('AUTHORIZED','BLOCKED','UNAVAILABLE') AND conversion_status IN ('NOT_REQUIRED','AUTHORIZED','BLOCKED'))");
        DB::statement("ALTER TABLE regulatory_nutrient_mappings ADD CONSTRAINT regulatory_mappings_ratio_check CHECK ((conversion_numerator IS NULL AND conversion_denominator IS NULL) OR (conversion_numerator IS NOT NULL AND conversion_denominator IS NOT NULL AND conversion_numerator NOT IN ('Infinity'::numeric,'-Infinity'::numeric,'NaN'::numeric) AND conversion_denominator NOT IN ('Infinity'::numeric,'NaN'::numeric) AND conversion_denominator > 0 AND scale(conversion_numerator)=0 AND scale(conversion_denominator)=0 AND (conversion_numerator <> 0 OR conversion_denominator = 1)))");
        DB::statement("ALTER TABLE regulatory_nutrient_mappings ADD CONSTRAINT regulatory_mappings_conversion_check CHECK ((conversion_status = 'AUTHORIZED' AND conversion_numerator IS NOT NULL AND conversion_source_unit IS NOT NULL AND conversion_target_unit IS NOT NULL AND conversion_evidence_reference IS NOT NULL AND conversion_evidence_source IS NOT NULL AND conversion_evidence_version IS NOT NULL AND conversion_evidence_scope IS NOT NULL AND conversion_evidence_kind IN ('DOCUMENTED','TEST_FIXTURE') AND conversion_validated_by IS NOT NULL AND conversion_validated_on IS NOT NULL) OR (conversion_status = 'NOT_REQUIRED' AND conversion_numerator IS NULL AND conversion_evidence_reference IS NOT NULL AND conversion_evidence_source IS NOT NULL AND conversion_evidence_version IS NOT NULL AND conversion_evidence_scope IS NOT NULL AND conversion_evidence_kind IN ('DOCUMENTED','TEST_FIXTURE') AND conversion_validated_by IS NOT NULL AND conversion_validated_on IS NOT NULL) OR (conversion_status = 'BLOCKED' AND conversion_numerator IS NULL AND conversion_denominator IS NULL))");
        DB::statement("ALTER TABLE regulatory_nutrient_mappings ADD CONSTRAINT regulatory_mappings_evidence_check CHECK (technical_status <> 'AUTHORIZED' OR (nutrient_id IS NOT NULL AND evidence_reference IS NOT NULL AND evidence_source IS NOT NULL AND evidence_version IS NOT NULL AND evidence_scope IS NOT NULL AND evidence_kind IN ('DOCUMENTED','TEST_FIXTURE') AND validated_by IS NOT NULL AND validated_on IS NOT NULL))");
        DB::statement("ALTER TABLE regulatory_nutrient_mappings ADD CONSTRAINT regulatory_mappings_evidence_shape_check CHECK ((evidence_reference IS NULL AND evidence_source IS NULL AND evidence_version IS NULL AND evidence_scope IS NULL AND evidence_kind IS NULL AND validated_by IS NULL AND validated_on IS NULL) OR (evidence_reference IS NOT NULL AND evidence_source IS NOT NULL AND evidence_version IS NOT NULL AND evidence_scope IS NOT NULL AND evidence_kind IN ('DOCUMENTED','TEST_FIXTURE') AND validated_by IS NOT NULL AND validated_on IS NOT NULL))");
        DB::statement("ALTER TABLE regulatory_nutrient_mappings ADD CONSTRAINT regulatory_mappings_conversion_evidence_shape_check CHECK ((conversion_evidence_reference IS NULL AND conversion_evidence_source IS NULL AND conversion_evidence_version IS NULL AND conversion_evidence_scope IS NULL AND conversion_evidence_kind IS NULL AND conversion_validated_by IS NULL AND conversion_validated_on IS NULL) OR (conversion_evidence_reference IS NOT NULL AND conversion_evidence_source IS NOT NULL AND conversion_evidence_version IS NOT NULL AND conversion_evidence_scope IS NOT NULL AND conversion_evidence_kind IN ('DOCUMENTED','TEST_FIXTURE') AND conversion_validated_by IS NOT NULL AND conversion_validated_on IS NOT NULL))");
    }

    public function down(): void
    {
        Schema::dropIfExists('regulatory_nutrient_mappings');
    }
};
