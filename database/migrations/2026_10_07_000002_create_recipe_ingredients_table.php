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
        Schema::create('recipe_ingredients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipe_id')->constrained('recipes')->cascadeOnDelete();
            $table->foreignId('food_id')->constrained('foods');
            $table->string('unit', 1);
            $table->bigInteger('position');
            $table->timestamps();

            $table->index('food_id');
            $table->unique(['recipe_id', 'position']);
        });

        DB::statement('ALTER TABLE recipe_ingredients ADD COLUMN quantity NUMERIC NOT NULL');
        DB::statement(<<<'SQL'
            ALTER TABLE recipe_ingredients
            ADD CONSTRAINT recipe_ingredients_quantity_positive_finite_check
            CHECK (
                quantity > 0
                AND quantity <> 'Infinity'::numeric
                AND quantity <> 'NaN'::numeric
            )
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE recipe_ingredients
            ADD CONSTRAINT recipe_ingredients_unit_check
            CHECK (unit = 'g')
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE recipe_ingredients
            ADD CONSTRAINT recipe_ingredients_position_positive_check
            CHECK (position > 0)
            SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recipe_ingredients');
    }
};
