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
        Schema::create('added_sugars_formulation_ingredients', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('formulation_version_id')->constrained('added_sugars_formulation_versions')->restrictOnDelete();
            $table->foreignId('food_id')->constrained('foods')->restrictOnDelete();
            $table->integer('position');
            $table->text('ingredient_type');
            $table->text('classification');
            $table->text('amount_basis')->nullable();
            $table->decimal('amount_value', 14, 6)->nullable();
            $table->text('amount_unit')->nullable();
            $table->text('exclusion_justification')->nullable();
            $table->text('conflict_notes')->nullable();
            $table->timestamps();

            $table->unique(['formulation_version_id', 'position'], 'added_sugars_formulation_ingredients_position_unique');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE added_sugars_formulation_ingredients
            ADD CONSTRAINT added_sugars_formulation_ingredients_position_check
            CHECK (position >= 1)
            SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE added_sugars_formulation_ingredients
            ADD CONSTRAINT added_sugars_formulation_ingredients_type_check
            CHECK (ingredient_type IN ('SIMPLE', 'COMPOUND_INDUSTRIALIZED'))
            SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE added_sugars_formulation_ingredients
            ADD CONSTRAINT added_sugars_formulation_ingredients_classification_check
            CHECK (classification IN ('INCLUDED', 'EXCLUDED_NORMATIVE', 'MISSING', 'CONFLICTING'))
            SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE added_sugars_formulation_ingredients
            ADD CONSTRAINT added_sugars_formulation_ingredients_amount_basis_check
            CHECK (amount_basis IS NULL OR amount_basis IN ('PER_100G', 'DIRECT_BATCH_GRAMS'))
            SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE added_sugars_formulation_ingredients
            ADD CONSTRAINT added_sugars_formulation_ingredients_amount_value_check
            CHECK (amount_value IS NULL OR amount_value >= 0)
            SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE added_sugars_formulation_ingredients
            ADD CONSTRAINT added_sugars_formulation_ingredients_amount_unit_check
            CHECK (amount_unit IS NULL OR amount_unit = 'g')
            SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE added_sugars_formulation_ingredients
            ADD CONSTRAINT added_sugars_formulation_ingredients_included_coherence_check
            CHECK (
                (classification = 'INCLUDED' AND amount_basis IS NOT NULL AND amount_value IS NOT NULL AND amount_unit = 'g')
                OR (classification <> 'INCLUDED' AND amount_basis IS NULL AND amount_value IS NULL AND amount_unit IS NULL)
            )
            SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE added_sugars_formulation_ingredients
            ADD CONSTRAINT added_sugars_formulation_ingredients_per_100g_bound_check
            CHECK (amount_basis IS DISTINCT FROM 'PER_100G' OR amount_value <= 100)
            SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE added_sugars_formulation_ingredients
            ADD CONSTRAINT added_sugars_formulation_ingredients_exclusion_check
            CHECK (classification <> 'EXCLUDED_NORMATIVE' OR (exclusion_justification IS NOT NULL AND BTRIM(exclusion_justification) <> ''))
            SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE added_sugars_formulation_ingredients
            ADD CONSTRAINT added_sugars_formulation_ingredients_conflict_check
            CHECK (classification <> 'CONFLICTING' OR (conflict_notes IS NOT NULL AND BTRIM(conflict_notes) <> ''))
            SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('added_sugars_formulation_ingredients');
    }
};
