<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('recipes', function (Blueprint $table) {
            $table->id();
            $table->text('name');
            $table->string('yield_unit', 2);
            $table->timestamps();
        });

        DB::statement('ALTER TABLE recipes ADD COLUMN yield_value NUMERIC NOT NULL');
        DB::statement(<<<'SQL'
            ALTER TABLE recipes
            ADD CONSTRAINT recipes_yield_value_positive_finite_check
            CHECK (
                yield_value > 0
                AND yield_value <> 'Infinity'::numeric
                AND yield_value <> 'NaN'::numeric
            )
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE recipes
            ADD CONSTRAINT recipes_yield_unit_check
            CHECK (yield_unit IN ('g', 'ml'))
            SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recipes');
    }
};
