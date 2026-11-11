<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('serving_daily_value_nutrients', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('serving_daily_value_calculation_id')->constrained('serving_daily_value_calculations')->restrictOnDelete();
            $table->foreignId('nutrition_calculation_nutrient_id')->constrained('nutrition_calculation_nutrients')->restrictOnDelete();
            $table->unsignedBigInteger('nutrient_id');
            $table->text('nutrient_name');
            $table->text('nutrient_unit');
            $table->decimal('source_numerator')->nullable();
            $table->decimal('source_denominator')->nullable();
            $table->boolean('source_is_complete');
            $table->boolean('source_has_missing_relation');
            $table->decimal('portion_numerator')->nullable();
            $table->decimal('portion_denominator')->nullable();
            $table->boolean('is_complete');
            $table->boolean('has_missing_relation');
            $table->unique(['serving_daily_value_calculation_id', 'nutrient_id'], 'serving_nutrient_unique');
        });
        DB::statement('ALTER TABLE serving_daily_value_nutrients ALTER COLUMN source_numerator TYPE NUMERIC, ALTER COLUMN source_denominator TYPE NUMERIC, ALTER COLUMN portion_numerator TYPE NUMERIC, ALTER COLUMN portion_denominator TYPE NUMERIC');
        DB::statement("ALTER TABLE serving_daily_value_nutrients ADD CONSTRAINT serving_nutrient_source_ratio_check CHECK ((source_numerator IS NULL AND source_denominator IS NULL) OR (source_numerator IS NOT NULL AND source_denominator IS NOT NULL AND source_numerator NOT IN ('Infinity'::numeric,'-Infinity'::numeric,'NaN'::numeric) AND source_denominator NOT IN ('Infinity'::numeric,'NaN'::numeric) AND source_denominator > 0 AND scale(source_numerator)=0 AND scale(source_denominator)=0 AND (source_numerator <> 0 OR source_denominator=1)))");
        DB::statement("ALTER TABLE serving_daily_value_nutrients ADD CONSTRAINT serving_nutrient_portion_ratio_check CHECK ((portion_numerator IS NULL AND portion_denominator IS NULL) OR (portion_numerator IS NOT NULL AND portion_denominator IS NOT NULL AND portion_numerator NOT IN ('Infinity'::numeric,'-Infinity'::numeric,'NaN'::numeric) AND portion_denominator NOT IN ('Infinity'::numeric,'NaN'::numeric) AND portion_denominator > 0 AND scale(portion_numerator)=0 AND scale(portion_denominator)=0 AND (portion_numerator <> 0 OR portion_denominator=1)))");
    }

    public function down(): void
    {
        Schema::dropIfExists('serving_daily_value_nutrients');
    }
};
