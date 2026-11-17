<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('declared_nutrition_component_states', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('declared_nutrition_component_id')
                ->constrained('declared_nutrition_components')
                ->restrictOnDelete();
            $table->text('state');
            $table->unique(['declared_nutrition_component_id', 'state'], 'declared_component_states_unique');
        });

        DB::statement("ALTER TABLE declared_nutrition_component_states ADD CONSTRAINT declared_component_states_check CHECK (state IN ('TRACE','NA','ABSENT','REEVALUATION'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('declared_nutrition_component_states');
    }
};
