<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nutrition_calculation_ingredients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nutrition_calculation_id')
                ->constrained('nutrition_calculations')
                ->cascadeOnDelete();
            $table->unsignedBigInteger('recipe_ingredient_id');
            $table->bigInteger('position');
            $table->string('unit', 1);
            $table->unsignedBigInteger('food_id');
            $table->text('food_source');
            $table->text('food_source_code')->nullable();

            $table->unique(
                ['nutrition_calculation_id', 'recipe_ingredient_id'],
                'nutrition_calc_ingredients_recipe_ingredient_unique',
            );
            $table->unique(
                ['nutrition_calculation_id', 'position'],
                'nutrition_calc_ingredients_position_unique',
            );
        });

        DB::statement('ALTER TABLE nutrition_calculation_ingredients ADD COLUMN quantity NUMERIC NOT NULL');
        DB::statement(<<<'SQL'
            ALTER TABLE nutrition_calculation_ingredients
            ADD CONSTRAINT nutrition_calculation_ingredients_position_positive_check
            CHECK (position > 0)
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE nutrition_calculation_ingredients
            ADD CONSTRAINT nutrition_calc_ingredients_quantity_positive_finite_check
            CHECK (
                quantity > 0
                AND quantity <> 'Infinity'::numeric
                AND quantity <> 'NaN'::numeric
            )
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE nutrition_calculation_ingredients
            ADD CONSTRAINT nutrition_calculation_ingredients_unit_check
            CHECK (unit = 'g')
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('nutrition_calculation_ingredients');
    }
};
