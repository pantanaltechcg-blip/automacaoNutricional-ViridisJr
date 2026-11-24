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
        Schema::create('added_sugars_formulation_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('formulation_id')->constrained('added_sugars_formulations')->restrictOnDelete();
            $table->foreignId('recipe_id')->constrained('recipes')->restrictOnDelete();
            $table->integer('version_number');
            $table->text('normative_source');
            $table->text('normative_version');
            $table->text('registered_by');
            $table->date('registered_on');
            $table->text('change_reason')->nullable();
            $table->timestamps();

            $table->unique(['formulation_id', 'version_number'], 'added_sugars_formulation_versions_unique');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE added_sugars_formulation_versions
            ADD CONSTRAINT added_sugars_formulation_versions_version_number_check
            CHECK (version_number >= 1)
            SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('added_sugars_formulation_versions');
    }
};
