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
        Schema::create('added_sugars_evaluations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('nutrition_calculation_id')->constrained('nutrition_calculations')->restrictOnDelete();
            $table->foreignId('formulation_id')->constrained('added_sugars_formulations')->restrictOnDelete();
            $table->foreignId('formulation_version_id')->constrained('added_sugars_formulation_versions')->restrictOnDelete();
            $table->foreignId('validation_id')->nullable()->constrained('added_sugars_formulation_validations')->restrictOnDelete();
            $table->unsignedBigInteger('source_recipe_id');
            $table->text('source_rules_version');
            $table->decimal('source_yield_value', 14, 6);
            $table->text('source_yield_unit');
            $table->integer('formulation_version_number');
            $table->text('normative_source');
            $table->text('normative_version');
            $table->text('validation_status');
            $table->text('validation_evidence_type')->nullable();
            $table->text('validation_evidence_reference')->nullable();
            $table->text('validation_evidence_source')->nullable();
            $table->text('validation_evidence_version')->nullable();
            $table->text('validation_evidence_scope')->nullable();
            $table->text('validated_by')->nullable();
            $table->date('validated_on')->nullable();
            $table->text('total_numerator')->nullable();
            $table->text('total_denominator')->nullable();
            $table->text('total_unit')->nullable();
            $table->text('reference_basis')->default('100');
            $table->text('reference_unit');
            $table->text('reference_numerator')->nullable();
            $table->text('reference_denominator')->nullable();
            $table->boolean('is_complete');
            $table->boolean('is_regulatory_eligible');
            $table->boolean('is_confirmed_zero');
            $table->text('rules_version');
            $table->timestamps();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE added_sugars_evaluations
            ADD CONSTRAINT added_sugars_evaluations_yield_unit_check
            CHECK (source_yield_unit IN ('g', 'ml'))
            SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE added_sugars_evaluations
            ADD CONSTRAINT added_sugars_evaluations_reference_unit_check
            CHECK (reference_unit IN ('g', 'ml'))
            SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE added_sugars_evaluations
            ADD CONSTRAINT added_sugars_evaluations_total_unit_check
            CHECK (total_unit IS NULL OR total_unit = 'g')
            SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE added_sugars_evaluations
            ADD CONSTRAINT added_sugars_evaluations_validation_status_check
            CHECK (validation_status IN ('PENDING', 'AUTHORIZED', 'REJECTED', 'REVOKED'))
            SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE added_sugars_evaluations
            ADD CONSTRAINT added_sugars_evaluations_total_pair_check
            CHECK ((total_numerator IS NULL) = (total_denominator IS NULL))
            SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE added_sugars_evaluations
            ADD CONSTRAINT added_sugars_evaluations_reference_pair_check
            CHECK ((reference_numerator IS NULL) = (reference_denominator IS NULL))
            SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE added_sugars_evaluations
            ADD CONSTRAINT added_sugars_evaluations_eligibility_coherence_check
            CHECK (
                (is_regulatory_eligible = true AND is_complete = true AND total_numerator IS NOT NULL AND reference_numerator IS NOT NULL AND total_unit = 'g')
                OR (is_regulatory_eligible = false AND total_numerator IS NULL AND reference_numerator IS NULL AND total_unit IS NULL AND is_confirmed_zero = false)
            )
            SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('added_sugars_evaluations');
    }
};
