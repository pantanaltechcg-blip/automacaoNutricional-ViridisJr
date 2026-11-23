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
        Schema::create('added_sugars_evaluation_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('evaluation_id')->constrained('added_sugars_evaluations')->restrictOnDelete();
            $table->foreignId('nutrition_calculation_ingredient_id')->constrained('nutrition_calculation_ingredients')->restrictOnDelete();
            $table->foreignId('formulation_ingredient_id')->nullable()->constrained('added_sugars_formulation_ingredients')->restrictOnDelete();
            $table->integer('position');
            $table->unsignedBigInteger('food_id');
            $table->text('food_source');
            $table->decimal('ingredient_quantity', 14, 6);
            $table->text('ingredient_unit');
            $table->text('ingredient_type')->nullable();
            $table->text('classification');
            $table->text('amount_basis')->nullable();
            $table->decimal('amount_value', 14, 6)->nullable();
            $table->text('contribution_numerator')->nullable();
            $table->text('contribution_denominator')->nullable();
            $table->boolean('is_excluded_normative')->default(false);
            $table->boolean('is_complete');
            $table->integer('evidence_count')->default(0);
            $table->timestamps();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE added_sugars_evaluation_items
            ADD CONSTRAINT added_sugars_evaluation_items_classification_check
            CHECK (classification IN ('INCLUDED', 'EXCLUDED_NORMATIVE', 'MISSING', 'CONFLICTING', 'UNCOVERED'))
            SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE added_sugars_evaluation_items
            ADD CONSTRAINT added_sugars_evaluation_items_contribution_pair_check
            CHECK ((contribution_numerator IS NULL) = (contribution_denominator IS NULL))
            SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('added_sugars_evaluation_items');
    }
};
