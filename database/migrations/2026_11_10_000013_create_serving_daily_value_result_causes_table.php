<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('serving_daily_value_result_causes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('serving_daily_value_calculation_id')->constrained('serving_daily_value_calculations')->restrictOnDelete();
            $table->text('scope');
            $table->text('scope_key');
            $table->text('category');
            $table->text('code');
            $table->text('detail')->nullable();
            $table->text('reference')->nullable();
            $table->unique(['serving_daily_value_calculation_id', 'scope', 'scope_key', 'code'], 'serving_result_cause_unique');
        });
        DB::statement("ALTER TABLE serving_daily_value_result_causes ADD CONSTRAINT serving_result_cause_scope_check CHECK (scope IN ('REGULATORY_VALUE','ENERGY','DAILY_VALUE') AND category IN ('REGULATORY_BLOCK','MISSING_SOURCE','UNKNOWN_APPLICABILITY','UNIT_INCOMPATIBLE','UNAUTHORIZED_CONVERSION','MISSING_NUMERATOR','NO_NORMATIVE_VDR','INCOMPLETE_NUTRITION','DOUBLE_COUNTING_RISK'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('serving_daily_value_result_causes');
    }
};
