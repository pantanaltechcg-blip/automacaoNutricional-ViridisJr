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
        Schema::create('added_sugars_formulation_evidences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('formulation_ingredient_id')->constrained('added_sugars_formulation_ingredients')->restrictOnDelete();
            $table->text('source_type');
            $table->text('source_name');
            $table->text('document_reference');
            $table->text('reported_amount_basis')->nullable();
            $table->decimal('reported_amount_value', 14, 6)->nullable();
            $table->text('reported_amount_unit')->nullable();
            $table->boolean('only_total_sugars_reported')->default(false);
            $table->text('recorded_by');
            $table->date('recorded_on');
            $table->timestamps();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE added_sugars_formulation_evidences
            ADD CONSTRAINT added_sugars_formulation_evidences_source_type_check
            CHECK (source_type IN ('MANUFACTURER_SPEC', 'LAB_REPORT', 'OPERATIONAL_FORM', 'NORMATIVE_REFERENCE', 'TECHNICAL_TABLE', 'TEST_FIXTURE'))
            SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE added_sugars_formulation_evidences
            ADD CONSTRAINT added_sugars_formulation_evidences_basis_check
            CHECK (reported_amount_basis IS NULL OR reported_amount_basis IN ('PER_100G', 'DIRECT_BATCH_GRAMS'))
            SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE added_sugars_formulation_evidences
            ADD CONSTRAINT added_sugars_formulation_evidences_value_check
            CHECK (reported_amount_value IS NULL OR reported_amount_value >= 0)
            SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE added_sugars_formulation_evidences
            ADD CONSTRAINT added_sugars_formulation_evidences_unit_check
            CHECK (reported_amount_unit IS NULL OR reported_amount_unit = 'g')
            SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('added_sugars_formulation_evidences');
    }
};
