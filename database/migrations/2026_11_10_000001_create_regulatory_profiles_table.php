<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regulatory_profiles', function (Blueprint $table): void {
            $table->id();
            $table->text('code');
            $table->text('version');
            $table->text('title');
            $table->text('normative_source');
            $table->text('normative_version');
            $table->date('consulted_on');
            $table->text('technical_status');
            $table->text('evidence_reference')->nullable();
            $table->text('evidence_source')->nullable();
            $table->text('evidence_version')->nullable();
            $table->text('evidence_scope')->nullable();
            $table->text('evidence_kind')->nullable();
            $table->text('validated_by')->nullable();
            $table->date('validated_on')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['code', 'version']);
        });
        DB::statement("ALTER TABLE regulatory_profiles ADD CONSTRAINT regulatory_profiles_status_check CHECK (technical_status IN ('AUTHORIZED','BLOCKED'))");
        DB::statement("ALTER TABLE regulatory_profiles ADD CONSTRAINT regulatory_profiles_evidence_check CHECK (((evidence_reference IS NULL AND evidence_source IS NULL AND evidence_version IS NULL AND evidence_scope IS NULL AND evidence_kind IS NULL AND validated_by IS NULL AND validated_on IS NULL) OR (evidence_reference IS NOT NULL AND evidence_source IS NOT NULL AND evidence_version IS NOT NULL AND evidence_scope IS NOT NULL AND evidence_kind IN ('DOCUMENTED','TEST_FIXTURE') AND validated_by IS NOT NULL AND validated_on IS NOT NULL)) AND (technical_status <> 'AUTHORIZED' OR evidence_reference IS NOT NULL))");
    }

    public function down(): void
    {
        Schema::dropIfExists('regulatory_profiles');
    }
};
