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
        Schema::create('added_sugars_formulations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('recipe_id')->unique()->constrained('recipes')->restrictOnDelete();
            $table->integer('current_version_number');
            $table->timestamps();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE added_sugars_formulations
            ADD CONSTRAINT added_sugars_formulations_current_version_number_check
            CHECK (current_version_number >= 1)
            SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('added_sugars_formulations');
    }
};
