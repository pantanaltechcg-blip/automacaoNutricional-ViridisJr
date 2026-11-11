<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regulatory_daily_values', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('profile_id')->constrained('regulatory_profiles')->restrictOnDelete();
            $table->text('vdr_code');
            $table->text('component_name');
            $table->foreignId('mapping_id')->nullable()->constrained('regulatory_nutrient_mappings')->restrictOnDelete();
            $table->decimal('vdr_numerator')->nullable();
            $table->decimal('vdr_denominator')->nullable();
            $table->text('unit')->nullable();
            $table->text('technical_status');
            $table->text('source_reference')->nullable();
            foreach (['reference', 'source', 'version', 'scope', 'kind'] as $name) {
                $table->text("evidence_{$name}")->nullable();
            }
            $table->text('validated_by')->nullable();
            $table->date('validated_on')->nullable();
            $table->unique(['profile_id', 'vdr_code']);
        });
        DB::statement('ALTER TABLE regulatory_daily_values ALTER COLUMN vdr_numerator TYPE NUMERIC, ALTER COLUMN vdr_denominator TYPE NUMERIC');
        DB::statement("ALTER TABLE regulatory_daily_values ADD CONSTRAINT regulatory_daily_values_status_check CHECK (technical_status IN ('AUTHORIZED','BLOCKED','NO_NORMATIVE_VDR','UNAVAILABLE'))");
        DB::statement("ALTER TABLE regulatory_daily_values ADD CONSTRAINT regulatory_daily_values_ratio_check CHECK ((vdr_numerator IS NULL AND vdr_denominator IS NULL) OR (vdr_numerator IS NOT NULL AND vdr_denominator IS NOT NULL AND vdr_numerator NOT IN ('Infinity'::numeric,'-Infinity'::numeric,'NaN'::numeric) AND vdr_denominator NOT IN ('Infinity'::numeric,'NaN'::numeric) AND vdr_denominator > 0 AND scale(vdr_numerator)=0 AND scale(vdr_denominator)=0 AND (vdr_numerator <> 0 OR vdr_denominator=1)))");
        DB::statement("ALTER TABLE regulatory_daily_values ADD CONSTRAINT regulatory_daily_values_authorization_check CHECK ((technical_status = 'AUTHORIZED' AND mapping_id IS NOT NULL AND vdr_numerator IS NOT NULL AND unit IS NOT NULL AND evidence_reference IS NOT NULL AND evidence_source IS NOT NULL AND evidence_version IS NOT NULL AND evidence_scope IS NOT NULL AND evidence_kind IN ('DOCUMENTED','TEST_FIXTURE') AND validated_by IS NOT NULL AND validated_on IS NOT NULL) OR (technical_status = 'NO_NORMATIVE_VDR' AND vdr_numerator IS NULL AND evidence_reference IS NOT NULL AND evidence_source IS NOT NULL AND evidence_version IS NOT NULL AND evidence_scope IS NOT NULL AND evidence_kind IN ('DOCUMENTED','TEST_FIXTURE') AND validated_by IS NOT NULL AND validated_on IS NOT NULL) OR technical_status IN ('BLOCKED','UNAVAILABLE'))");
        DB::statement("ALTER TABLE regulatory_daily_values ADD CONSTRAINT regulatory_daily_values_evidence_shape_check CHECK ((evidence_reference IS NULL AND evidence_source IS NULL AND evidence_version IS NULL AND evidence_scope IS NULL AND evidence_kind IS NULL AND validated_by IS NULL AND validated_on IS NULL) OR (evidence_reference IS NOT NULL AND evidence_source IS NOT NULL AND evidence_version IS NOT NULL AND evidence_scope IS NOT NULL AND evidence_kind IN ('DOCUMENTED','TEST_FIXTURE') AND validated_by IS NOT NULL AND validated_on IS NOT NULL))");
    }

    public function down(): void
    {
        Schema::dropIfExists('regulatory_daily_values');
    }
};
