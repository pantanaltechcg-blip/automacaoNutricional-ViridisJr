<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nutrition_calculations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipe_id')->constrained('recipes');
            $table->string('yield_unit', 2);
            $table->text('rules_version');
            $table->timestamp('created_at');
        });

        DB::statement('ALTER TABLE nutrition_calculations ADD COLUMN yield_value NUMERIC NOT NULL');
        DB::statement(<<<'SQL'
            ALTER TABLE nutrition_calculations
            ADD CONSTRAINT nutrition_calculations_yield_value_positive_finite_check
            CHECK (
                yield_value > 0
                AND yield_value <> 'Infinity'::numeric
                AND yield_value <> 'NaN'::numeric
            )
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE nutrition_calculations
            ADD CONSTRAINT nutrition_calculations_yield_unit_check
            CHECK (yield_unit IN ('g', 'ml'))
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('nutrition_calculations');
    }
};
