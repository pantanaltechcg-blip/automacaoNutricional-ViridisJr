<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('declared_nutrition_percentages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('declared_nutrition_calculation_id')
                ->constrained('declared_nutrition_calculations')
                ->restrictOnDelete();
            $table->foreignId('serving_daily_value_percentage_id')
                ->nullable()
                ->constrained('serving_daily_value_percentages')
                ->restrictOnDelete();
            $table->text('vdr_code');
            $table->text('component_name');
            $table->text('map_code')->nullable();
            $table->text('vdr_technical_status');
            $table->decimal('vdr_numerator')->nullable();
            $table->decimal('vdr_denominator')->nullable();
            $table->text('vdr_unit')->nullable();
            $table->text('vdr_source_reference')->nullable();
            $table->text('vdr_source_version')->nullable();
            foreach (['reference', 'source', 'version', 'scope', 'kind'] as $name) {
                $table->text("vdr_evidence_{$name}")->nullable();
            }
            $table->text('vdr_validated_by')->nullable();
            $table->date('vdr_validated_on')->nullable();

            $table->decimal('source_derived_percentage_numerator')->nullable();
            $table->decimal('source_derived_percentage_denominator')->nullable();
            $table->text('source_derived_availability')->nullable();

            $table->text('numerator_identity')->nullable();
            $table->text('input_declared_string')->nullable();
            $table->decimal('input_declared_numerator')->nullable();
            $table->decimal('input_declared_denominator')->nullable();
            $table->decimal('unrounded_percentage_numerator')->nullable();
            $table->decimal('unrounded_percentage_denominator')->nullable();

            $table->text('rounding_rule_code')->nullable();
            $table->text('rounding_rule_status')->nullable();
            $table->text('rounding_tie_breaker')->nullable();
            $table->decimal('rounding_step_numerator')->nullable();
            $table->decimal('rounding_step_denominator')->nullable();
            $table->unsignedInteger('rounding_decimal_places')->nullable();
            foreach (['reference', 'source', 'version', 'scope', 'kind'] as $name) {
                $table->text("rounding_evidence_{$name}")->nullable();
            }
            $table->text('rounding_validated_by')->nullable();
            $table->date('rounding_validated_on')->nullable();

            $table->text('declared_percentage_string')->nullable();
            $table->decimal('declared_percentage_numerator')->nullable();
            $table->decimal('declared_percentage_denominator')->nullable();
            $table->boolean('is_complete')->default(false);
            $table->text('availability');
            $table->unique(['declared_nutrition_calculation_id', 'vdr_code'], 'declared_percentages_vdr_unique');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE declared_nutrition_percentages
                ALTER COLUMN vdr_numerator TYPE NUMERIC,
                ALTER COLUMN vdr_denominator TYPE NUMERIC,
                ALTER COLUMN source_derived_percentage_numerator TYPE NUMERIC,
                ALTER COLUMN source_derived_percentage_denominator TYPE NUMERIC,
                ALTER COLUMN input_declared_numerator TYPE NUMERIC,
                ALTER COLUMN input_declared_denominator TYPE NUMERIC,
                ALTER COLUMN unrounded_percentage_numerator TYPE NUMERIC,
                ALTER COLUMN unrounded_percentage_denominator TYPE NUMERIC,
                ALTER COLUMN rounding_step_numerator TYPE NUMERIC,
                ALTER COLUMN rounding_step_denominator TYPE NUMERIC,
                ALTER COLUMN declared_percentage_numerator TYPE NUMERIC,
                ALTER COLUMN declared_percentage_denominator TYPE NUMERIC
            SQL);

        DB::statement("ALTER TABLE declared_nutrition_percentages ADD CONSTRAINT declared_percentages_status_check CHECK (vdr_technical_status IN ('AUTHORIZED','BLOCKED','NO_NORMATIVE_VDR','UNAVAILABLE') AND availability IN ('AVAILABLE','UNAVAILABLE','NO_NORMATIVE_VDR'))");

        foreach ([
            'vdr',
            'source_derived_percentage',
            'input_declared',
            'unrounded_percentage',
            'rounding_step',
            'declared_percentage',
        ] as $prefix) {
            DB::statement("ALTER TABLE declared_nutrition_percentages ADD CONSTRAINT declared_percentages_{$prefix}_ratio_check CHECK (({$prefix}_numerator IS NULL AND {$prefix}_denominator IS NULL) OR ({$prefix}_numerator IS NOT NULL AND {$prefix}_denominator IS NOT NULL AND {$prefix}_numerator NOT IN ('Infinity'::numeric,'-Infinity'::numeric,'NaN'::numeric) AND {$prefix}_denominator NOT IN ('Infinity'::numeric,'NaN'::numeric) AND {$prefix}_denominator > 0 AND scale({$prefix}_numerator)=0 AND scale({$prefix}_denominator)=0 AND ({$prefix}_numerator <> 0 OR {$prefix}_denominator=1)))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('declared_nutrition_percentages');
    }
};
