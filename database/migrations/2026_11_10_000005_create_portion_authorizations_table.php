<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portion_authorizations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('nutrition_calculation_id')->constrained('nutrition_calculations')->restrictOnDelete();
            $table->text('reference_code');
            $table->text('reference_version');
            $table->text('reference_source');
            $table->text('technical_status');
            $table->decimal('portion_value');
            $table->text('portion_unit');
            $table->text('household_measure_mode');
            $table->text('household_measure')->nullable();
            $table->text('justification')->nullable();
            foreach (['reference', 'source', 'version', 'scope', 'kind'] as $name) {
                $table->text("evidence_{$name}")->nullable();
            }
            $table->text('validated_by')->nullable();
            $table->date('validated_on')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
        DB::statement('ALTER TABLE portion_authorizations ALTER COLUMN portion_value TYPE NUMERIC');
        DB::statement("ALTER TABLE portion_authorizations ADD CONSTRAINT portion_authorizations_status_check CHECK (technical_status IN ('AUTHORIZED','BLOCKED') AND portion_unit IN ('g','ml') AND household_measure_mode IN ('PROVIDED','WAIVED','NOT_APPLICABLE') AND portion_value > 0 AND portion_value NOT IN ('Infinity'::numeric,'NaN'::numeric))");
        DB::statement("ALTER TABLE portion_authorizations ADD CONSTRAINT portion_authorizations_measure_check CHECK ((household_measure_mode = 'PROVIDED' AND household_measure IS NOT NULL AND btrim(household_measure) <> '' AND justification IS NULL) OR (household_measure_mode IN ('WAIVED','NOT_APPLICABLE') AND household_measure IS NULL AND justification IS NOT NULL AND btrim(justification) <> ''))");
        DB::statement("ALTER TABLE portion_authorizations ADD CONSTRAINT portion_authorizations_evidence_check CHECK (technical_status <> 'AUTHORIZED' OR (evidence_reference IS NOT NULL AND evidence_source IS NOT NULL AND evidence_version IS NOT NULL AND evidence_scope IS NOT NULL AND evidence_kind IN ('DOCUMENTED','TEST_FIXTURE') AND validated_by IS NOT NULL AND validated_on IS NOT NULL))");
        DB::statement("ALTER TABLE portion_authorizations ADD CONSTRAINT portion_authorizations_evidence_shape_check CHECK ((evidence_reference IS NULL AND evidence_source IS NULL AND evidence_version IS NULL AND evidence_scope IS NULL AND evidence_kind IS NULL AND validated_by IS NULL AND validated_on IS NULL) OR (evidence_reference IS NOT NULL AND evidence_source IS NOT NULL AND evidence_version IS NOT NULL AND evidence_scope IS NOT NULL AND evidence_kind IN ('DOCUMENTED','TEST_FIXTURE') AND validated_by IS NOT NULL AND validated_on IS NOT NULL))");
    }

    public function down(): void
    {
        Schema::dropIfExists('portion_authorizations');
    }
};
