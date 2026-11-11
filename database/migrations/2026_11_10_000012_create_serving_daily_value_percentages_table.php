<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('serving_daily_value_percentages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('serving_daily_value_calculation_id')->constrained('serving_daily_value_calculations')->restrictOnDelete();
            $table->text('vdr_code');
            $table->text('component_name');
            $table->text('map_code')->nullable();
            $table->foreignId('serving_daily_value_regulatory_result_id')->nullable()->constrained('serving_daily_value_regulatory_results')->restrictOnDelete();
            $table->decimal('vdr_numerator')->nullable();
            $table->decimal('vdr_denominator')->nullable();
            $table->text('vdr_unit')->nullable();
            $table->text('vdr_source_reference')->nullable();
            $table->text('vdr_source_version')->nullable();
            $table->text('technical_status');
            foreach (['reference', 'source', 'version', 'scope', 'kind'] as $name) {
                $table->text("vdr_evidence_{$name}")->nullable();
            }
            $table->text('vdr_validated_by')->nullable();
            $table->date('vdr_validated_on')->nullable();
            $table->text('numerator_identity')->nullable();
            $table->decimal('numerator_numerator')->nullable();
            $table->decimal('numerator_denominator')->nullable();
            $table->decimal('percentage_numerator')->nullable();
            $table->decimal('percentage_denominator')->nullable();
            $table->boolean('is_complete')->default(false);
            $table->text('availability');
            $table->unique(['serving_daily_value_calculation_id', 'vdr_code'], 'serving_percentage_unique');
        });
        DB::statement('ALTER TABLE serving_daily_value_percentages ALTER COLUMN vdr_numerator TYPE NUMERIC, ALTER COLUMN vdr_denominator TYPE NUMERIC, ALTER COLUMN numerator_numerator TYPE NUMERIC, ALTER COLUMN numerator_denominator TYPE NUMERIC, ALTER COLUMN percentage_numerator TYPE NUMERIC, ALTER COLUMN percentage_denominator TYPE NUMERIC');
        DB::statement("ALTER TABLE serving_daily_value_percentages ADD CONSTRAINT serving_percentage_status_check CHECK (technical_status IN ('AUTHORIZED','BLOCKED','NO_NORMATIVE_VDR','UNAVAILABLE') AND availability IN ('AVAILABLE','PARTIAL','UNAVAILABLE','NO_NORMATIVE_VDR'))");
        foreach (['vdr', 'numerator', 'percentage'] as $prefix) {
            DB::statement("ALTER TABLE serving_daily_value_percentages ADD CONSTRAINT serving_percentage_{$prefix}_ratio_check CHECK (({$prefix}_numerator IS NULL AND {$prefix}_denominator IS NULL) OR ({$prefix}_numerator IS NOT NULL AND {$prefix}_denominator IS NOT NULL AND {$prefix}_numerator NOT IN ('Infinity'::numeric,'-Infinity'::numeric,'NaN'::numeric) AND {$prefix}_denominator NOT IN ('Infinity'::numeric,'NaN'::numeric) AND {$prefix}_denominator > 0 AND scale({$prefix}_numerator)=0 AND scale({$prefix}_denominator)=0 AND ({$prefix}_numerator <> 0 OR {$prefix}_denominator=1)))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('serving_daily_value_percentages');
    }
};
