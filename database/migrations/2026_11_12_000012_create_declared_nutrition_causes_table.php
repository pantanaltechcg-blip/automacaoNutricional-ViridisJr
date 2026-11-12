<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('declared_nutrition_causes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('declared_nutrition_calculation_id')
                ->constrained('declared_nutrition_calculations')
                ->restrictOnDelete();
            $table->text('scope');
            $table->text('scope_key');
            $table->text('category');
            $table->text('code');
            $table->text('detail')->nullable();
            $table->text('source_reference')->nullable();
            $table->unique(['declared_nutrition_calculation_id', 'scope', 'scope_key', 'code'], 'declared_causes_unique');
        });

        DB::statement("ALTER TABLE declared_nutrition_causes ADD CONSTRAINT declared_causes_scope_check CHECK (scope IN ('COMPONENT_REFERENCE_100','COMPONENT_PORTION','ENERGY_REFERENCE_100','ENERGY_PORTION','DAILY_VALUE','LABEL_READINESS') AND category IN ('REGULATORY_BLOCK','MISSING_SOURCE','INCOMPLETE_NUTRITION','QUALITATIVE_STATE_BLOCK','MISSING_RELATION_BLOCK','NO_NORMATIVE_VDR','UNKNOWN_APPLICABILITY','DOUBLE_COUNTING_RISK','ROUNDING_RANGE_MISMATCH','POLICY_BLOCK','PROFILE_MISMATCH'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('declared_nutrition_causes');
    }
};
