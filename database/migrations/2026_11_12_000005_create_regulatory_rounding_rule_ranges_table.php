<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regulatory_rounding_rule_ranges', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('rounding_rule_id')
                ->constrained('regulatory_rounding_rules')
                ->restrictOnDelete();
            $table->unsignedInteger('range_order');
            $table->decimal('min_numerator')->nullable();
            $table->decimal('min_denominator')->nullable();
            $table->boolean('min_inclusive')->default(true);
            $table->decimal('max_numerator')->nullable();
            $table->decimal('max_denominator')->nullable();
            $table->boolean('max_inclusive')->default(false);
            $table->decimal('step_numerator');
            $table->decimal('step_denominator');
            $table->unsignedInteger('decimal_places');
            $table->unique(['rounding_rule_id', 'range_order'], 'regulatory_rounding_range_order_unique');
        });

        DB::statement('ALTER TABLE regulatory_rounding_rule_ranges ALTER COLUMN min_numerator TYPE NUMERIC, ALTER COLUMN min_denominator TYPE NUMERIC, ALTER COLUMN max_numerator TYPE NUMERIC, ALTER COLUMN max_denominator TYPE NUMERIC, ALTER COLUMN step_numerator TYPE NUMERIC, ALTER COLUMN step_denominator TYPE NUMERIC');
        DB::statement('ALTER TABLE regulatory_rounding_rule_ranges ADD CONSTRAINT regulatory_rounding_ranges_bounds_check CHECK (range_order >= 1 AND decimal_places >= 0)');

        foreach (['min', 'max'] as $prefix) {
            DB::statement("ALTER TABLE regulatory_rounding_rule_ranges ADD CONSTRAINT regulatory_rounding_ranges_{$prefix}_ratio_check CHECK (({$prefix}_numerator IS NULL AND {$prefix}_denominator IS NULL) OR ({$prefix}_numerator IS NOT NULL AND {$prefix}_denominator IS NOT NULL AND {$prefix}_numerator NOT IN ('Infinity'::numeric,'-Infinity'::numeric,'NaN'::numeric) AND {$prefix}_denominator NOT IN ('Infinity'::numeric,'NaN'::numeric) AND {$prefix}_numerator >= 0 AND {$prefix}_denominator > 0 AND scale({$prefix}_numerator)=0 AND scale({$prefix}_denominator)=0 AND ({$prefix}_numerator <> 0 OR {$prefix}_denominator=1)))");
        }

        DB::statement("ALTER TABLE regulatory_rounding_rule_ranges ADD CONSTRAINT regulatory_rounding_ranges_step_ratio_check CHECK (step_numerator NOT IN ('Infinity'::numeric,'-Infinity'::numeric,'NaN'::numeric) AND step_denominator NOT IN ('Infinity'::numeric,'NaN'::numeric) AND step_numerator > 0 AND step_denominator > 0 AND scale(step_numerator)=0 AND scale(step_denominator)=0)");
    }

    public function down(): void
    {
        Schema::dropIfExists('regulatory_rounding_rule_ranges');
    }
};
