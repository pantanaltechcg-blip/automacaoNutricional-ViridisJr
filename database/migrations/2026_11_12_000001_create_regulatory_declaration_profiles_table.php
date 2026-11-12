<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regulatory_declaration_profiles', function (Blueprint $table): void {
            $table->id();
            $table->text('code');
            $table->text('version');
            $table->text('title');
            $table->text('regulatory_profile_code');
            $table->text('regulatory_profile_version');
            $table->text('normative_source');
            $table->text('normative_version');
            $table->date('consulted_on');
            $table->unsignedBigInteger('scope_recipe_id')->nullable();
            $table->unsignedBigInteger('scope_nutrition_calculation_id')->nullable();
            $table->text('technical_status');
            foreach (['reference', 'source', 'version', 'scope', 'kind'] as $name) {
                $table->text("evidence_{$name}")->nullable();
            }
            $table->text('validated_by')->nullable();
            $table->date('validated_on')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['code', 'version']);
        });

        DB::statement("ALTER TABLE regulatory_declaration_profiles ADD CONSTRAINT regulatory_declaration_profiles_status_check CHECK (technical_status IN ('AUTHORIZED','BLOCKED'))");
        DB::statement("ALTER TABLE regulatory_declaration_profiles ADD CONSTRAINT regulatory_declaration_profiles_evidence_check CHECK (((evidence_reference IS NULL AND evidence_source IS NULL AND evidence_version IS NULL AND evidence_scope IS NULL AND evidence_kind IS NULL AND validated_by IS NULL AND validated_on IS NULL) OR (evidence_reference IS NOT NULL AND trim(evidence_reference) <> '' AND evidence_source IS NOT NULL AND trim(evidence_source) <> '' AND evidence_version IS NOT NULL AND trim(evidence_version) <> '' AND evidence_scope IS NOT NULL AND trim(evidence_scope) <> '' AND evidence_kind IN ('DOCUMENTED','TEST_FIXTURE') AND validated_by IS NOT NULL AND trim(validated_by) <> '' AND validated_on IS NOT NULL)) AND (technical_status <> 'AUTHORIZED' OR evidence_reference IS NOT NULL))");
    }

    public function down(): void
    {
        Schema::dropIfExists('regulatory_declaration_profiles');
    }
};
