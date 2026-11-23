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
        Schema::create('added_sugars_formulation_validations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('formulation_version_id')->constrained('added_sugars_formulation_versions')->restrictOnDelete();
            $table->text('decision_status');
            $table->text('normative_source');
            $table->text('normative_version');
            $table->text('evidence_type');
            $table->text('evidence_reference');
            $table->text('evidence_source');
            $table->text('evidence_version');
            $table->text('evidence_scope');
            $table->text('validated_by');
            $table->date('validated_on');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE added_sugars_formulation_validations
            ADD CONSTRAINT added_sugars_formulation_validations_status_check
            CHECK (decision_status IN ('AUTHORIZED', 'REJECTED', 'REVOKED'))
            SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE added_sugars_formulation_validations
            ADD CONSTRAINT added_sugars_formulation_validations_evidence_type_check
            CHECK (evidence_type IN ('DOCUMENT', 'TECHNICAL_REVIEW', 'TEST_FIXTURE'))
            SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('added_sugars_formulation_validations');
    }
};
