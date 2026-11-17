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
        Schema::create('manual_food_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('manual_food_registration_id')
                ->constrained('manual_food_registrations')
                ->restrictOnDelete();
            $table->integer('version_number');
            $table->text('name');
            $table->text('category')->nullable();
            $table->text('source');
            $table->text('source_code');
            $table->string('reference_unit', 2);
            $table->text('evidence_reference');
            $table->text('evidence_source');
            $table->text('registered_by');
            $table->date('registered_on');
            $table->text('change_reason')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(
                ['manual_food_registration_id', 'version_number'],
                'manual_food_versions_registration_version_unique',
            );
        });

        DB::statement(<<<'SQL'
            ALTER TABLE manual_food_versions
            ADD CONSTRAINT manual_food_versions_version_number_check
            CHECK (version_number >= 1)
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE manual_food_versions
            ADD CONSTRAINT manual_food_versions_name_check
            CHECK (BTRIM(name) <> '')
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE manual_food_versions
            ADD CONSTRAINT manual_food_versions_category_check
            CHECK (category IS NULL OR BTRIM(category) <> '')
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE manual_food_versions
            ADD CONSTRAINT manual_food_versions_source_check
            CHECK (BTRIM(source) <> '' AND UPPER(BTRIM(source)) <> 'TACO')
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE manual_food_versions
            ADD CONSTRAINT manual_food_versions_source_code_check
            CHECK (BTRIM(source_code) <> '')
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE manual_food_versions
            ADD CONSTRAINT manual_food_versions_reference_unit_check
            CHECK (reference_unit = 'g')
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE manual_food_versions
            ADD CONSTRAINT manual_food_versions_evidence_reference_check
            CHECK (BTRIM(evidence_reference) <> '')
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE manual_food_versions
            ADD CONSTRAINT manual_food_versions_evidence_source_check
            CHECK (BTRIM(evidence_source) <> '')
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE manual_food_versions
            ADD CONSTRAINT manual_food_versions_registered_by_check
            CHECK (BTRIM(registered_by) <> '')
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE manual_food_versions
            ADD CONSTRAINT manual_food_versions_change_reason_check
            CHECK (version_number = 1 OR (change_reason IS NOT NULL AND BTRIM(change_reason) <> ''))
            SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('manual_food_versions');
    }
};
