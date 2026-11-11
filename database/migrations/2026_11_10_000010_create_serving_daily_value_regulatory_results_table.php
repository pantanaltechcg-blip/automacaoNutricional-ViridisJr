<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('serving_daily_value_regulatory_results', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('serving_daily_value_calculation_id')->constrained('serving_daily_value_calculations')->restrictOnDelete();
            $table->text('map_code');
            $table->text('component_name');
            $table->text('technical_status');
            $table->foreignId('serving_daily_value_nutrient_id')->nullable()->constrained('serving_daily_value_nutrients')->restrictOnDelete();
            $table->unsignedBigInteger('nutrient_id')->nullable();
            $table->text('nutrient_name')->nullable();
            $table->text('source_unit')->nullable();
            $table->text('regulatory_unit');
            $table->text('map_source_reference')->nullable();
            foreach (['reference', 'source', 'version', 'scope', 'kind'] as $name) {
                $table->text("map_evidence_{$name}")->nullable();
            }
            $table->text('map_validated_by')->nullable();
            $table->date('map_validated_on')->nullable();
            $table->text('conversion_status');
            $table->text('conversion_source_unit')->nullable();
            $table->text('conversion_target_unit')->nullable();
            $table->decimal('conversion_numerator')->nullable();
            $table->decimal('conversion_denominator')->nullable();
            foreach (['reference', 'source', 'version', 'scope', 'kind'] as $name) {
                $table->text("conversion_evidence_{$name}")->nullable();
            }
            $table->text('conversion_validated_by')->nullable();
            $table->date('conversion_validated_on')->nullable();
            $table->decimal('value_numerator')->nullable();
            $table->decimal('value_denominator')->nullable();
            $table->boolean('is_complete')->default(false);
            $table->text('availability');
            $table->unique(['serving_daily_value_calculation_id', 'map_code'], 'serving_regulatory_result_unique');
        });
        DB::statement('ALTER TABLE serving_daily_value_regulatory_results ALTER COLUMN conversion_numerator TYPE NUMERIC, ALTER COLUMN conversion_denominator TYPE NUMERIC, ALTER COLUMN value_numerator TYPE NUMERIC, ALTER COLUMN value_denominator TYPE NUMERIC');
        DB::statement("ALTER TABLE serving_daily_value_regulatory_results ADD CONSTRAINT serving_regulatory_result_status_check CHECK (technical_status IN ('AUTHORIZED','BLOCKED','UNAVAILABLE') AND conversion_status IN ('NOT_REQUIRED','AUTHORIZED','BLOCKED') AND availability IN ('AVAILABLE','PARTIAL','UNAVAILABLE'))");
        DB::statement("ALTER TABLE serving_daily_value_regulatory_results ADD CONSTRAINT serving_regulatory_result_ratio_check CHECK ((value_numerator IS NULL AND value_denominator IS NULL) OR (value_numerator IS NOT NULL AND value_denominator IS NOT NULL AND value_numerator NOT IN ('Infinity'::numeric,'-Infinity'::numeric,'NaN'::numeric) AND value_denominator NOT IN ('Infinity'::numeric,'NaN'::numeric) AND value_denominator > 0 AND scale(value_numerator)=0 AND scale(value_denominator)=0 AND (value_numerator <> 0 OR value_denominator=1)))");
        DB::statement("ALTER TABLE serving_daily_value_regulatory_results ADD CONSTRAINT serving_regulatory_conversion_ratio_check CHECK ((conversion_numerator IS NULL AND conversion_denominator IS NULL) OR (conversion_numerator IS NOT NULL AND conversion_denominator IS NOT NULL AND conversion_numerator NOT IN ('Infinity'::numeric,'-Infinity'::numeric,'NaN'::numeric) AND conversion_denominator NOT IN ('Infinity'::numeric,'NaN'::numeric) AND conversion_denominator > 0 AND scale(conversion_numerator)=0 AND scale(conversion_denominator)=0 AND (conversion_numerator <> 0 OR conversion_denominator=1)))");
    }

    public function down(): void
    {
        Schema::dropIfExists('serving_daily_value_regulatory_results');
    }
};
